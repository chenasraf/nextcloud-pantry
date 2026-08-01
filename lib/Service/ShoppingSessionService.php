<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Db\ShoppingSessionItem;
use OCA\Pantry\Db\ShoppingSessionItemMapper;
use OCA\Pantry\Db\ShoppingSessionListMapper;
use OCA\Pantry\Db\ShoppingSessionMapper;
use OCA\Pantry\Db\ShoppingSessionStore;
use OCA\Pantry\Db\ShoppingSessionStoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Exception\ShoppingSessionConflictException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Orchestrates the shopping-session aggregate: the session row plus its scope
 * (checklists), its ordered store sequence, and the aggregated shopping list.
 * Lifecycle is timestamp-driven and enforced here.
 */
class ShoppingSessionService {
	public function __construct(
		private ShoppingSessionMapper $sessions,
		private ShoppingSessionListMapper $sessionLists,
		private ShoppingSessionStoreMapper $sessionStores,
		private ShoppingSessionItemMapper $sessionItems,
		private ChecklistItemMapper $items,
		private ItemStoreMapper $itemStores,
		private ChecklistService $checklists,
	) {
	}

	public function get(int $sessionId): ShoppingSession {
		try {
			return $this->sessions->findById($sessionId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Shopping session not found');
		}
	}

	/**
	 * The caller's live session, across all houses, or null.
	 */
	public function findCurrentForUser(string $uid): ?ShoppingSession {
		return $this->sessions->findLiveByUser($uid);
	}

	/**
	 * Start a trip. One live session per user globally: if one already exists,
	 * throws {@see ShoppingSessionConflictException} carrying it (→ 409) rather
	 * than auto-resuming — the client decides resume vs end-then-recreate.
	 *
	 * Scope validity (lists/stores in the house, list access) is enforced by the
	 * caller before this runs.
	 *
	 * @param int[] $listIds Non-empty set of checklist ids in scope.
	 * @param int[] $storeIds Ordered store sequence (may be empty = no narrowing).
	 *
	 * @throws ShoppingSessionConflictException when a live session already exists.
	 */
	public function create(int $houseId, string $uid, array $listIds, array $storeIds, bool $includeUnassigned): ShoppingSession {
		$existing = $this->sessions->findLiveByUser($uid);
		if ($existing !== null) {
			throw new ShoppingSessionConflictException($existing, 'A live shopping session already exists');
		}

		$listIds = array_values(array_unique(array_map('intval', $listIds)));
		if ($listIds === []) {
			throw new \InvalidArgumentException('Pick at least one checklist to shop');
		}
		$storeIds = array_values(array_unique(array_map('intval', $storeIds)));

		$now = time();
		$session = new ShoppingSession();
		$session->setHouseId($houseId);
		$session->setUserId($uid);
		// Active store is the first in the planned sequence, or none.
		$session->setActiveStoreId($storeIds[0] ?? null);
		$session->setLastSeenAt($now);
		$session->setClosedAt(null);
		$session->setIncludeUnassigned($includeUnassigned);
		$session->setIsPrivate(false);
		$session->setCreatedAt($now);
		$session->setUpdatedAt($now);
		/** @var ShoppingSession $saved */
		$saved = $this->sessions->insert($session);

		$sessionId = (int)$saved->getId();
		$this->sessionLists->setListsForSession($sessionId, $listIds);
		$this->sessionStores->setStoresForSession($sessionId, $storeIds);

		return $saved;
	}

	/**
	 * Set the active store to a member of the session's sequence. The client may
	 * move forward or back; "next" is computed client-side.
	 *
	 * @throws \InvalidArgumentException when $storeId is not in the sequence.
	 */
	public function advance(ShoppingSession $session, int $storeId): ShoppingSession {
		$sequence = $this->storeIdsForSession((int)$session->getId());
		if (!in_array($storeId, $sequence, true)) {
			throw new \InvalidArgumentException('Store is not part of this session');
		}
		$now = time();
		$session->setActiveStoreId($storeId);
		$session->setLastSeenAt($now);
		$session->setUpdatedAt($now);
		$this->sessions->update($session);
		return $session;
	}

	/**
	 * Stamp `closed_at`. Closing an already-closed session is a 409 (no reopen).
	 *
	 * @throws ShoppingSessionConflictException when already closed.
	 */
	public function close(ShoppingSession $session): ShoppingSession {
		if ($session->getClosedAt() !== null) {
			throw new ShoppingSessionConflictException($session, 'Shopping session is already closed');
		}
		$now = time();
		$session->setClosedAt($now);
		$session->setLastSeenAt($now);
		$session->setUpdatedAt($now);
		$this->sessions->update($session);
		return $session;
	}

	/**
	 * The unchecked, in-scope, active-store-narrowed items to shop, ordered.
	 * Flat array; the client groups by category.
	 *
	 * @return ChecklistItem[]
	 */
	public function itemsForSession(ShoppingSession $session): array {
		$listIds = $this->sessionLists->findListIdsForSession((int)$session->getId());
		return $this->items->findForShoppingScope(
			$listIds,
			$session->getActiveStoreId(),
			$session->getIncludeUnassigned(),
		);
	}

	/**
	 * Check an item off during the trip: mark it done (reusing the normal check
	 * behavior — recurrence rescheduling, delete-on-done) and record it in the
	 * session log against the currently active store. Idempotent.
	 */
	public function checkItem(ShoppingSession $session, int $itemId, string $uid): void {
		$item = $this->checklists->getItem($itemId);
		if (!$item->getDone()) {
			$this->checklists->toggleItem($itemId, $uid);
		}

		$sessionId = (int)$session->getId();
		$now = time();
		$existing = $this->sessionItems->findBySessionAndItem($sessionId, $itemId);
		if ($existing === null) {
			$row = new ShoppingSessionItem();
			$row->setSessionId($sessionId);
			$row->setItemId($itemId);
			$row->setStoreId($session->getActiveStoreId());
			$row->setCheckedAt($now);
			$this->sessionItems->insert($row);
		} else {
			$existing->setStoreId($session->getActiveStoreId());
			$existing->setCheckedAt($now);
			$this->sessionItems->update($existing);
		}
	}

	/**
	 * Undo a check: drop the log row and mark the item not-done again. A
	 * delete-on-done item soft-deleted by the check is restored.
	 */
	public function uncheckItem(ShoppingSession $session, int $itemId): void {
		$this->sessionItems->deleteBySessionAndItem((int)$session->getId(), $itemId);

		try {
			$item = $this->items->findById($itemId, includeDeleted: true);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$item->getDone()) {
			return;
		}
		$now = time();
		$item->setDone(false);
		$item->setDoneAt(null);
		$item->setDoneBy(null);
		$item->setNextDueAt(null);
		// Restore an item the check soft-deleted via delete-on-done.
		if ($item->getDeletedAt() !== null && $item->getDeleteOnDone()) {
			$item->setDeletedAt(null);
		}
		$item->setUpdatedAt($now);
		$this->items->update($item);
	}

	/**
	 * The review payload: items checked this trip grouped by the store they were
	 * checked at, each group carrying a per-currency estimate (range-aware) with a
	 * no-price count and any amended billed total; plus the blended grand total
	 * (billed where set, otherwise estimate) and a soft count of items still
	 * unchecked in scope.
	 *
	 * @return array<string, mixed>
	 */
	public function review(ShoppingSession $session): array {
		$sessionId = (int)$session->getId();
		$logs = $this->sessionItems->findBySession($sessionId);
		$itemIds = array_map(static fn (ShoppingSessionItem $l) => (int)$l->getItemId(), $logs);
		$itemsById = [];
		foreach ($this->items->findByIds($itemIds) as $item) {
			$itemsById[(int)$item->getId()] = $item;
		}

		// Bucket the checked items by the store they were checked at.
		/** @var array<int|string, ChecklistItem[]> $byStore */
		$byStore = [];
		foreach ($logs as $log) {
			$item = $itemsById[(int)$log->getItemId()] ?? null;
			// Drop items hard-deleted since, or un-done outside shopping mode
			// (which leaves the log row stale) — the review reflects the real cart.
			if ($item === null || !$item->getDone()) {
				continue;
			}
			$key = $log->getStoreId() === null ? 'none' : (int)$log->getStoreId();
			$byStore[$key][] = $item;
		}

		$sequence = $this->sessionStores->findBySession($sessionId);
		$grand = [];
		$groups = [];

		foreach ($sequence as $store) {
			$sid = (int)$store->getStoreId();
			$items = $byStore[$sid] ?? [];
			unset($byStore[$sid]);
			$groups[] = $this->buildReviewGroup($sid, $items, $store, $grand);
		}

		// Checks with no active store (storeless sessions). The session-level
		// billed total is the fallback here.
		if (isset($byStore['none'])) {
			$groups[] = $this->buildReviewGroup(null, $byStore['none'], null, $grand, $session);
			unset($byStore['none']);
		}

		// Any remaining buckets are stores dropped from the sequence; show them
		// last with an estimate (no billed override target).
		foreach ($byStore as $key => $items) {
			$sid = $key === 'none' ? null : (int)$key;
			$groups[] = $this->buildReviewGroup($sid, $items, null, $grand);
		}

		return [
			'stores' => $groups,
			'grandTotal' => $this->formatEstimate($grand),
			'uncheckedCount' => $this->uncheckedCount($session),
		];
	}

	/**
	 * Build one review group and fold its contribution into the running grand
	 * total (billed amount when set, otherwise the estimate).
	 *
	 * @param ChecklistItem[] $items
	 * @param array<string, array{min: float, max: float}> $grand accumulator, by reference
	 * @return array<string, mixed>
	 */
	private function buildReviewGroup(?int $storeId, array $items, ?ShoppingSessionStore $store, array &$grand, ?ShoppingSession $session = null): array {
		$estimate = $this->estimateForItems($items);
		$billedTotal = $store?->getBilledTotal() ?? $session?->getBilledTotal();
		$billedCurrency = $store?->getBilledCurrency() ?? $session?->getBilledCurrency();

		if ($billedTotal !== null) {
			$this->addToTotal($grand, $billedCurrency ?? '', (float)$billedTotal, (float)$billedTotal);
		} else {
			foreach ($estimate['byCurrency'] as $currency => $range) {
				$this->addToTotal($grand, (string)$currency, $range['min'], $range['max']);
			}
		}

		return [
			'storeId' => $storeId,
			'items' => $this->serializeItems($items),
			'estimate' => $this->formatEstimate($estimate['byCurrency']),
			'noPriceCount' => $estimate['noPrice'],
			'billedTotal' => $billedTotal !== null ? (float)$billedTotal : null,
			'billedCurrency' => $billedCurrency,
		];
	}

	/**
	 * My shopping-mode checks logged within [from, to) (epoch seconds), newest
	 * first, with a per-currency estimate. Powers the live "Done today" surface.
	 *
	 * @return array<string, mixed>
	 */
	public function doneToday(string $uid, int $houseId, int $from, int $to): array {
		$logs = $this->sessionItems->findForUserBetween($uid, $houseId, $from, $to);
		$itemIds = array_map(static fn (ShoppingSessionItem $l) => (int)$l->getItemId(), $logs);
		$itemsById = [];
		foreach ($this->items->findByIds($itemIds) as $item) {
			$itemsById[(int)$item->getId()] = $item;
		}
		$items = [];
		foreach ($logs as $log) {
			$item = $itemsById[(int)$log->getItemId()] ?? null;
			// Skip items un-done since the check (stale log rows).
			if ($item !== null && $item->getDone()) {
				$items[] = $item;
			}
		}
		$estimate = $this->estimateForItems($items);
		return [
			'items' => $this->serializeItems($items),
			'estimate' => $this->formatEstimate($estimate['byCurrency']),
			'noPriceCount' => $estimate['noPrice'],
			'count' => count($items),
		];
	}

	/**
	 * Amend the actual paid amount for a store in the session's sequence.
	 */
	public function amendStoreBilled(ShoppingSession $session, int $storeId, ?float $billedTotal, ?string $billedCurrency): ShoppingSessionStore {
		$store = null;
		foreach ($this->sessionStores->findBySession((int)$session->getId()) as $candidate) {
			if ((int)$candidate->getStoreId() === $storeId) {
				$store = $candidate;
				break;
			}
		}
		if ($store === null) {
			throw new NotFoundException('Store is not part of this session');
		}
		$store->setBilledTotal($this->normalizeBilledTotal($billedTotal));
		$store->setBilledCurrency($this->normalizeCurrency($billedCurrency));
		$this->sessionStores->update($store);
		return $store;
	}

	/**
	 * Amend the storeless-session grand total (used when the trip has no stores).
	 */
	public function amendSessionBilled(ShoppingSession $session, ?float $billedTotal, ?string $billedCurrency): ShoppingSession {
		$session->setBilledTotal($this->normalizeBilledTotal($billedTotal));
		$session->setBilledCurrency($this->normalizeCurrency($billedCurrency));
		$session->setUpdatedAt(time());
		$this->sessions->update($session);
		return $session;
	}

	/**
	 * Count of items still unchecked in the session's scope (across all stores).
	 */
	private function uncheckedCount(ShoppingSession $session): int {
		$listIds = $this->sessionLists->findListIdsForSession((int)$session->getId());
		return count($this->items->findForShoppingScope($listIds, null, true));
	}

	/**
	 * Per-currency, range-aware estimate over a set of items. `'set'` prices are
	 * a point; `'range'` prices spread min..max. Quantity is ignored (free-text).
	 * Items with no usable price are excluded and counted.
	 *
	 * @param ChecklistItem[] $items
	 * @return array{byCurrency: array<string, array{min: float, max: float}>, noPrice: int}
	 */
	private function estimateForItems(array $items): array {
		$byCurrency = [];
		$noPrice = 0;
		foreach ($items as $item) {
			$type = $item->getPriceType();
			$min = $item->getPriceMin();
			if (($type !== 'set' && $type !== 'range') || $min === null) {
				$noPrice++;
				continue;
			}
			$max = ($type === 'range' && $item->getPriceMax() !== null) ? (float)$item->getPriceMax() : (float)$min;
			$this->addToTotal($byCurrency, $item->getPriceCurrency() ?? '', (float)$min, $max);
		}
		return ['byCurrency' => $byCurrency, 'noPrice' => $noPrice];
	}

	/**
	 * @param array<string, array{min: float, max: float}> $totals by reference
	 */
	private function addToTotal(array &$totals, string $currency, float $min, float $max): void {
		if (!isset($totals[$currency])) {
			$totals[$currency] = ['min' => 0.0, 'max' => 0.0];
		}
		$totals[$currency]['min'] += $min;
		$totals[$currency]['max'] += $max;
	}

	/**
	 * Shape a per-currency total map into a stable list for the API.
	 *
	 * @param array<string, array{min: float, max: float}> $totals
	 * @return list<array{currency: string, min: float, max: float}>
	 */
	private function formatEstimate(array $totals): array {
		$out = [];
		foreach ($totals as $currency => $range) {
			$out[] = ['currency' => (string)$currency, 'min' => $range['min'], 'max' => $range['max']];
		}
		return $out;
	}

	private function normalizeBilledTotal(?float $value): ?float {
		if ($value === null || $value < 0) {
			return null;
		}
		return $value;
	}

	private function normalizeCurrency(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : strtoupper($value);
	}

	/**
	 * Serialize items with their attached store ids (batched), mirroring the
	 * checklist item shape.
	 *
	 * @param ChecklistItem[] $items
	 * @return list<array<string, mixed>>
	 */
	private function serializeItems(array $items): array {
		$ids = array_map(static fn (ChecklistItem $i) => (int)$i->getId(), $items);
		try {
			$storeMap = $this->itemStores->findStoreIdsForItems($ids);
		} catch (\Throwable) {
			$storeMap = [];
		}
		return array_values(array_map(static function (ChecklistItem $item) use ($storeMap) {
			$data = $item->jsonSerialize();
			$data['storeIds'] = $storeMap[(int)$item->getId()] ?? [];
			return $data;
		}, $items));
	}

	/**
	 * @return int[] The session's scope list ids.
	 */
	public function listIdsForSession(int $sessionId): array {
		return $this->sessionLists->findListIdsForSession($sessionId);
	}

	/**
	 * @return int[] The session's ordered store id sequence.
	 */
	public function storeIdsForSession(int $sessionId): array {
		return array_map(
			static fn ($s) => (int)$s->getStoreId(),
			$this->sessionStores->findBySession($sessionId),
		);
	}

	/**
	 * The session payload: identity + scope + lifecycle. Item collections live
	 * behind their own endpoints and are never inlined.
	 *
	 * @return array<string, mixed>
	 */
	public function composeDto(ShoppingSession $session): array {
		$sessionId = (int)$session->getId();
		$data = $session->jsonSerialize();
		$data['listIds'] = $this->sessionLists->findListIdsForSession($sessionId);
		$data['stores'] = array_map(
			static fn ($s) => $s->jsonSerialize(),
			$this->sessionStores->findBySession($sessionId),
		);
		return $data;
	}
}
