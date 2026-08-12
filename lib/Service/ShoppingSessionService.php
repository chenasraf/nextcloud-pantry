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
use OCA\Pantry\Db\ShoppingSessionSkip;
use OCA\Pantry\Db\ShoppingSessionSkipMapper;
use OCA\Pantry\Db\ShoppingSessionStore;
use OCA\Pantry\Db\ShoppingSessionStoreMapper;
use OCA\Pantry\Db\StoreMapper;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Exception\ShoppingSessionConflictException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Orchestrates the shopping-session aggregate: the session row plus its scope
 * (checklists), its ordered store sequence, and the aggregated shopping list.
 * Lifecycle is timestamp-driven and enforced here.
 */
class ShoppingSessionService {
	/**
	 * Presence staleness cutoff: a live session is "present" only if
	 * its last_seen_at is within this window. Chosen forgiving (~15 min) so a
	 * ~1-min heartbeat never flickers from dropped beats, yet a genuinely-gone
	 * shopper clears well before the ~4h abandonment auto-close.
	 */
	public const PRESENCE_STALE_SECONDS = 900;

	public function __construct(
		private ShoppingSessionMapper $sessions,
		private ShoppingSessionListMapper $sessionLists,
		private ShoppingSessionStoreMapper $sessionStores,
		private ShoppingSessionItemMapper $sessionItems,
		private ShoppingSessionSkipMapper $sessionSkips,
		private ChecklistItemMapper $items,
		private ItemStoreMapper $itemStores,
		private ChecklistService $checklists,
		private StoreMapper $storeMapper,
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
	 * Presence heartbeat: stamp the caller's live session as seen now
	 * if it belongs to this house. Pure liveness — it does not touch
	 * `active_store_id` (advancing is a separate explicit action). A caller with
	 * no live session here (or whose only live session is in another house) simply
	 * doesn't stamp; the presence read still returns.
	 */
	public function heartbeat(int $houseId, string $uid, ?int $now = null): void {
		$session = $this->sessions->findLiveByUser($uid);
		if ($session === null || $session->getHouseId() !== $houseId) {
			return;
		}
		$now ??= time();
		$session->setLastSeenAt($now);
		$session->setUpdatedAt($now);
		$this->sessions->update($session);
	}

	/**
	 * Derived house presence: the live, fresh, opted-in sessions in the
	 * house, each attributed to its active store. Not self-filtered — the caller's
	 * own session is included; the client decides whether to render it.
	 *
	 * @return list<array{userId: string, activeStoreId: int|null, lastSeenAt: int}>
	 */
	public function presence(int $houseId, ?int $now = null): array {
		$now ??= time();
		$cutoff = $now - self::PRESENCE_STALE_SECONDS;
		$out = [];
		foreach ($this->sessions->findPresentInHouse($houseId, $cutoff) as $session) {
			$out[] = [
				'userId' => $session->getUserId(),
				'activeStoreId' => $session->getActiveStoreId(),
				'lastSeenAt' => (int)$session->getLastSeenAt(),
			];
		}
		return $out;
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
		$this->snapshotSessionItems($session);
		$now = time();
		$session->setClosedAt($now);
		$session->setLastSeenAt($now);
		$session->setUpdatedAt($now);
		$this->sessions->update($session);
		return $session;
	}

	/**
	 * Auto-close abandoned live sessions: those whose last activity
	 * (`last_seen_at`) predates now − $idleSeconds. The trip is stamped closed at
	 * its last-seen time (the truthful end), so its history duration reflects real
	 * activity rather than when the job happened to run. Runs from the lifecycle
	 * {@see \OCA\Pantry\BackgroundJob\ShoppingSessionLifecycleJob}.
	 *
	 * @return int Number of sessions closed.
	 */
	public function closeIdleSessions(int $idleSeconds, ?int $now = null): int {
		$now ??= time();
		$cutoff = $now - $idleSeconds;
		$stale = $this->sessions->findIdleLive($cutoff);
		foreach ($stale as $session) {
			$this->snapshotSessionItems($session);
			$session->setClosedAt((int)$session->getLastSeenAt());
			$session->setUpdatedAt($now);
			$this->sessions->update($session);
		}
		return count($stale);
	}

	/**
	 * Retention purge: permanently delete closed sessions whose
	 * `closed_at` predates now − $retentionDays, cascading their child rows
	 * (lists/stores/items). A retention of 0 (or less) means "keep forever" and
	 * short-circuits. Runs as the second pass of the lifecycle job.
	 *
	 * @return int Number of sessions purged.
	 */
	public function purgeAgedSessions(int $retentionDays, ?int $now = null): int {
		if ($retentionDays <= 0) {
			return 0;
		}
		$now ??= time();
		$cutoff = $now - $retentionDays * 86400;
		$aged = $this->sessions->findClosedBefore($cutoff);
		foreach ($aged as $session) {
			$sessionId = (int)$session->getId();
			$this->sessionItems->deleteBySession($sessionId);
			$this->sessionSkips->deleteBySession($sessionId);
			$this->sessionStores->deleteBySession($sessionId);
			$this->sessionLists->deleteBySession($sessionId);
			$this->sessions->delete($session);
		}
		return count($aged);
	}

	/**
	 * The unchecked, in-scope, active-store-narrowed items to shop, ordered.
	 * Flat array; the client groups by category.
	 *
	 * @return ChecklistItem[]
	 */
	public function itemsForSession(ShoppingSession $session): array {
		$listIds = $this->sessionLists->findListIdsForSession((int)$session->getId());
		$items = $this->items->findForShoppingScope(
			$listIds,
			$session->getActiveStoreId(),
			$session->getIncludeUnassigned(),
		);
		// Drop items the shopper skipped from this trip (kept on the list, not
		// marked done — the skip is session-scoped and reversible).
		$skipped = $this->sessionSkips->findItemIdsForSession((int)$session->getId());
		if ($skipped === []) {
			return $items;
		}
		$skippedSet = array_fill_keys($skipped, true);
		return array_values(array_filter(
			$items,
			static fn (ChecklistItem $item) => !isset($skippedSet[(int)$item->getId()]),
		));
	}

	/**
	 * Skip an item for this trip only: hide it from the session's shopping list
	 * without marking it done or removing it from the checklist. Idempotent.
	 */
	public function skipItem(ShoppingSession $session, int $itemId): void {
		$sessionId = (int)$session->getId();
		if ($this->sessionSkips->findBySessionAndItem($sessionId, $itemId) !== null) {
			return;
		}
		$row = new ShoppingSessionSkip();
		$row->setSessionId($sessionId);
		$row->setItemId($itemId);
		$row->setCreatedAt(time());
		$this->sessionSkips->insert($row);
	}

	/**
	 * Undo a skip: the item reappears in the session's shopping list. Idempotent.
	 */
	public function unskipItem(ShoppingSession $session, int $itemId): void {
		$this->sessionSkips->deleteBySessionAndItem((int)$session->getId(), $itemId);
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
		$byStore = $this->bucketCheckedItems($session);
		$grand = [];
		$groups = [];

		foreach ($this->sessionStores->findBySession($sessionId) as $store) {
			$sid = (int)$store->getStoreId();
			$groups[] = $this->buildReviewGroup($sid, $byStore[$sid] ?? [], $store, null, $grand);
			unset($byStore[$sid]);
		}

		// Checks with no active store (storeless sessions). The session-level
		// billed total is the fallback here.
		if (isset($byStore['none'])) {
			$groups[] = $this->buildReviewGroup(null, $byStore['none'], null, $session, $grand);
			unset($byStore['none']);
		}

		// Any remaining buckets are stores dropped from the sequence; show them
		// last with an estimate (no billed override target).
		foreach ($byStore as $key => $items) {
			$sid = $key === 'none' ? null : (int)$key;
			$groups[] = $this->buildReviewGroup($sid, $items, null, null, $grand);
		}

		return [
			'stores' => $groups,
			'grandTotal' => $this->formatEstimate($grand),
			// "Still to buy" is a live, mid-trip prompt computed from the scope's
			// currently-unchecked items. It is meaningless once the trip is closed
			// (recurring items reappear, lists change), so a finished trip reports 0.
			'uncheckedCount' => $session->getClosedAt() !== null ? 0 : $this->uncheckedCount($session),
		];
	}

	/**
	 * Read-only history of closed trips, newest first. Each row carries
	 * cheap roll-ups: the store-sequence names, the checked-item count, and the
	 * blended grand total collapsed to a single figure per currency (the ≈X–Y
	 * range and no-price count are detail-view only, so a row stays glanceable).
	 *
	 * @param 'mine'|'house' $scope
	 * @return list<array<string, mixed>>
	 */
	public function history(int $houseId, string $uid, string $scope, int $limit, int $offset): array {
		$sessions = $this->sessions->findClosedForHistory($houseId, $uid, $scope, $limit, $offset);
		if ($sessions === []) {
			return [];
		}
		$storeNames = $this->storeNamesForHouse($houseId);
		$rows = [];
		foreach ($sessions as $session) {
			$sessionId = (int)$session->getId();
			$names = [];
			foreach ($this->sessionStores->findBySession($sessionId) as $store) {
				$name = $storeNames[(int)$store->getStoreId()] ?? null;
				// A store deleted since the trip drops out of the sequence label.
				if ($name !== null) {
					$names[] = $name;
				}
			}
			$rows[] = [
				'id' => $sessionId,
				'userId' => $session->getUserId(),
				'createdAt' => $session->getCreatedAt(),
				'closedAt' => $session->getClosedAt(),
				'stores' => $names,
				'itemCount' => $this->sessionItems->countBySession($sessionId),
				'grandTotal' => $this->collapseTotal($this->computeGrandTotal($session)),
			];
		}
		return $rows;
	}

	/**
	 * Bucket a session's checked items by the store they were checked at. Keys are
	 * store ids; storeless checks bucket under `'none'`.
	 *
	 * A **closed** trip is frozen history: it renders from the per-row snapshot
	 * captured at close, independent of the live item (which may since have been
	 * edited, un-done, or hard-deleted). A **live** trip reads the live items and
	 * drops any un-done outside shopping mode (a stale log row) so the roll-up
	 * reflects the real cart.
	 *
	 * @return array<int|string, ChecklistItem[]>
	 */
	private function bucketCheckedItems(ShoppingSession $session): array {
		$logs = $this->sessionItems->findBySession((int)$session->getId());
		/** @var array<int|string, ChecklistItem[]> $byStore */
		$byStore = [];

		if ($session->getClosedAt() !== null) {
			foreach ($logs as $log) {
				// A row with no snapshot (checked before this feature and its item
				// already gone at migration time) has nothing to render.
				if ($log->getItemName() === null) {
					continue;
				}
				$key = $log->getStoreId() === null ? 'none' : (int)$log->getStoreId();
				$byStore[$key][] = $this->hydrateFromSnapshot($log);
			}
			return $byStore;
		}

		$itemIds = array_map(static fn (ShoppingSessionItem $l) => (int)$l->getItemId(), $logs);
		$itemsById = [];
		foreach ($this->items->findByIds($itemIds) as $item) {
			$itemsById[(int)$item->getId()] = $item;
		}
		foreach ($logs as $log) {
			$item = $itemsById[(int)$log->getItemId()] ?? null;
			if ($item === null || !$item->getDone()) {
				continue;
			}
			$key = $log->getStoreId() === null ? 'none' : (int)$log->getStoreId();
			$byStore[$key][] = $item;
		}
		return $byStore;
	}

	/**
	 * Freeze an item snapshot (name/quantity/price) onto each of the session's
	 * check-log rows, from the live item at this moment. Called when a trip closes
	 * (normal close or idle auto-close) so the finished trip stands alone. A log
	 * row whose item is already gone is left as-is.
	 */
	private function snapshotSessionItems(ShoppingSession $session): void {
		$logs = $this->sessionItems->findBySession((int)$session->getId());
		if ($logs === []) {
			return;
		}
		$itemIds = array_map(static fn (ShoppingSessionItem $l) => (int)$l->getItemId(), $logs);
		$itemsById = [];
		foreach ($this->items->findByIds($itemIds) as $item) {
			$itemsById[(int)$item->getId()] = $item;
		}
		foreach ($logs as $log) {
			$item = $itemsById[(int)$log->getItemId()] ?? null;
			if ($item === null) {
				continue;
			}
			$log->setItemName($item->getName());
			$log->setQuantity($item->getQuantity());
			$log->setPriceType($item->getPriceType());
			$log->setPriceMin($item->getPriceMin());
			$log->setPriceMax($item->getPriceMax());
			$log->setPriceCurrency($item->getPriceCurrency());
			$this->sessionItems->update($log);
		}
	}

	/**
	 * Build a display-only {@see ChecklistItem} from a closed trip's frozen log
	 * snapshot, so the existing serialize/estimate paths work uniformly whether or
	 * not the underlying item still exists. Marked done — it is historical.
	 */
	private function hydrateFromSnapshot(ShoppingSessionItem $log): ChecklistItem {
		$item = new ChecklistItem();
		(new \ReflectionProperty($item, 'id'))->setValue($item, (int)$log->getItemId());
		$item->setName((string)$log->getItemName());
		$item->setQuantity($log->getQuantity());
		$item->setPriceType($log->getPriceType());
		$item->setPriceMin($log->getPriceMin());
		$item->setPriceMax($log->getPriceMax());
		$item->setPriceCurrency($log->getPriceCurrency());
		$item->setDone(true);
		return $item;
	}

	/**
	 * The blended grand total for a session (billed where set, otherwise the
	 * range-aware estimate), per currency. Shared by review() and the history
	 * roll-up; does not serialize items.
	 *
	 * @return array<string, array{min: float, max: float}>
	 */
	private function computeGrandTotal(ShoppingSession $session): array {
		$sessionId = (int)$session->getId();
		$byStore = $this->bucketCheckedItems($session);
		$grand = [];
		foreach ($this->sessionStores->findBySession($sessionId) as $store) {
			$this->foldGroupIntoTotal($byStore[(int)$store->getStoreId()] ?? [], $store, null, $grand);
			unset($byStore[(int)$store->getStoreId()]);
		}
		if (isset($byStore['none'])) {
			$this->foldGroupIntoTotal($byStore['none'], null, $session, $grand);
			unset($byStore['none']);
		}
		foreach ($byStore as $items) {
			$this->foldGroupIntoTotal($items, null, null, $grand);
		}
		return $grand;
	}

	/**
	 * Build one review group and fold its contribution into the running grand
	 * total.
	 *
	 * @param ChecklistItem[] $items
	 * @param array<string, array{min: float, max: float}> $grand accumulator, by reference
	 * @return array<string, mixed>
	 */
	private function buildReviewGroup(?int $storeId, array $items, ?ShoppingSessionStore $store, ?ShoppingSession $session, array &$grand): array {
		$folded = $this->foldGroupIntoTotal($items, $store, $session, $grand);
		return [
			'storeId' => $storeId,
			'items' => $this->serializeItems($items),
			'estimate' => $this->formatEstimate($folded['estimate']['byCurrency']),
			'noPriceCount' => $folded['estimate']['noPrice'],
			'billedTotal' => $folded['billedTotal'] !== null ? (float)$folded['billedTotal'] : null,
			'billedCurrency' => $folded['billedCurrency'],
		];
	}

	/**
	 * Fold one store/storeless group's contribution into the running grand total:
	 * the billed amount when set (a point), otherwise the range-aware estimate.
	 * Returns the group's estimate + resolved billed fields for callers that also
	 * render the group.
	 *
	 * @param ChecklistItem[] $items
	 * @param array<string, array{min: float, max: float}> $grand accumulator, by reference
	 * @return array{estimate: array{byCurrency: array<string, array{min: float, max: float}>, noPrice: int}, billedTotal: float|null, billedCurrency: string|null}
	 */
	private function foldGroupIntoTotal(array $items, ?ShoppingSessionStore $store, ?ShoppingSession $session, array &$grand): array {
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
			'estimate' => $estimate,
			'billedTotal' => $billedTotal !== null ? (float)$billedTotal : null,
			'billedCurrency' => $billedCurrency,
		];
	}

	/**
	 * Collapse a per-currency min/max total to a single amount per currency (the
	 * range midpoint) for the glanceable history row.
	 *
	 * @param array<string, array{min: float, max: float}> $totals
	 * @return list<array{currency: string, amount: float}>
	 */
	private function collapseTotal(array $totals): array {
		$out = [];
		foreach ($totals as $currency => $range) {
			$out[] = ['currency' => (string)$currency, 'amount' => ($range['min'] + $range['max']) / 2];
		}
		return $out;
	}

	/**
	 * A house's store id → name map, for cheap sequence labelling.
	 *
	 * @return array<int, string>
	 */
	private function storeNamesForHouse(int $houseId): array {
		$names = [];
		foreach ($this->storeMapper->findByHouse($houseId) as $store) {
			$names[(int)$store->getId()] = $store->getName();
		}
		return $names;
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
	 * Toggle the per-session "shop privately" opt-out. A private trip
	 * is hidden from housemates' presence and from their history.
	 * A contextual, per-trip visibility choice — not a lifecycle transition.
	 */
	public function setPrivacy(ShoppingSession $session, bool $isPrivate): ShoppingSession {
		$session->setIsPrivate($isPrivate);
		$session->setUpdatedAt(time());
		$this->sessions->update($session);
		return $session;
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
