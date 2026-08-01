<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Db\ShoppingSessionListMapper;
use OCA\Pantry\Db\ShoppingSessionMapper;
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
		private ChecklistItemMapper $items,
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
