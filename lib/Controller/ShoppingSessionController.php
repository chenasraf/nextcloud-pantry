<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\ShoppingSession;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Exception\ShoppingSessionConflictException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\ShoppingSessionService;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDateTimeZone;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Shopping Mode — session lifecycle (create/current/advance/close), the live
 * store-narrowed shopping list, per-store check logging, and the review + price
 * totals. Checking an item off marks it done and records it in the session log
 * against the active store.
 *
 * @psalm-import-type PantryShoppingSession from ResponseDefinitions
 * @psalm-import-type PantryListItem from ResponseDefinitions
 * @psalm-import-type PantryShoppingReview from ResponseDefinitions
 * @psalm-import-type PantryShoppingDoneToday from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class ShoppingSessionController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShoppingSessionService $sessions,
		private ChecklistService $lists,
		private StoreService $stores,
		private PermissionService $permissions,
		private HouseAuthService $auth,
		private ItemStoreMapper $itemStores,
		private IUserSession $userSession,
		private IDateTimeZone $dateTimeZone,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the caller's live shopping session (across all houses)
	 *
	 * Global discovery for the one-live-session-per-user guard: lets the client
	 * offer resume without provoking a failed create. Returns null when none.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingSession|null, array{}>
	 *
	 * 200: Live session (or null) returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/shopping-sessions/current')]
	#[NoAdminRequired]
	public function current(): DataResponse {
		return $this->runAction(function (): DataResponse {
			$uid = $this->requireUid();
			$session = $this->sessions->findCurrentForUser($uid);
			return new DataResponse($session !== null ? $this->sessions->composeDto($session) : null);
		});
	}

	/**
	 * Start a shopping session in a house
	 *
	 * One live session per user, globally: if one already exists this returns
	 * 409 with that session in the body so the client can offer resume/end.
	 *
	 * @param int $houseId House id.
	 * @param list<int> $listIds Checklist ids in scope (at least one).
	 * @param list<int> $storeIds Ordered store sequence (may be empty).
	 * @param bool $includeUnassigned Keep buy-anywhere items when narrowing by store.
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CONFLICT, PantryShoppingSession, array{}>
	 *
	 * 200: Session started
	 * 409: A live session already exists
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping-sessions')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function create(int $houseId, array $listIds = [], array $storeIds = [], bool $includeUnassigned = true): DataResponse {
		return $this->runAction(function () use ($houseId, $listIds, $storeIds, $includeUnassigned): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);

			$listIds = array_values(array_unique(array_map('intval', $listIds)));
			foreach ($listIds as $listId) {
				$list = $this->lists->getList($listId);
				$this->assertInHouse($list->getHouseId(), $houseId);
				if (!$this->permissions->canAccessList($houseId, $uid, $listId)) {
					throw new ForbiddenException('No access to list ' . $listId);
				}
			}
			$storeIds = array_map('intval', $storeIds);
			$this->stores->assertStoresInHouse($houseId, $storeIds);

			try {
				$session = $this->sessions->create($houseId, $uid, $listIds, $storeIds, $includeUnassigned);
			} catch (ShoppingSessionConflictException $e) {
				return new DataResponse($this->sessions->composeDto($e->getSession()), Http::STATUS_CONFLICT);
			}
			return new DataResponse($this->sessions->composeDto($session));
		});
	}

	/**
	 * Set the active store of a shopping session
	 *
	 * The target store must be part of the session's planned sequence. The
	 * client may move forward or back; "next" is computed client-side.
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 * @param int $storeId Store to make active.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingSession, array{}>
	 *
	 * 200: Active store set
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/advance')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function advance(int $houseId, int $sessionId, int $storeId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId, $storeId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$updated = $this->sessions->advance($session, $storeId);
			return new DataResponse($this->sessions->composeDto($updated));
		});
	}

	/**
	 * Close a shopping session
	 *
	 * Empty body; stamps `closed_at`. Closing an already-closed session returns
	 * 409 (no reopen).
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CONFLICT, PantryShoppingSession, array{}>
	 *
	 * 200: Session closed
	 * 409: Session was already closed
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/close')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function close(int $houseId, int $sessionId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			try {
				$closed = $this->sessions->close($session);
			} catch (ShoppingSessionConflictException $e) {
				return new DataResponse($this->sessions->composeDto($e->getSession()), Http::STATUS_CONFLICT);
			}
			return new DataResponse($this->sessions->composeDto($closed));
		});
	}

	/**
	 * List the items to shop for a session
	 *
	 * Unchecked, in-scope items narrowed by the active store, ordered by
	 * category then item sort order (uncategorized last). Flat array — the
	 * client groups by category. Re-query on each poll and after advancing.
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryListItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/items')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function items(int $houseId, int $sessionId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$items = $this->sessions->itemsForSession($session);
			return new DataResponse($this->serializeItems($items));
		});
	}

	/**
	 * Check an item off during a shopping session
	 *
	 * Marks the item done and records it in the session log against the active
	 * store.
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 * @param int $itemId Item id (must be in the session's scope).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item checked
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/items/{itemId}/check')]
	#[NoAdminRequired]
	#[Permission(['canCheckItems'])]
	public function checkItem(int $houseId, int $sessionId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId, $itemId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$this->assertItemInScope($session, $itemId);
			$this->sessions->checkItem($session, $itemId, $this->requireUid());
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Undo an item check during a shopping session
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item unchecked
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/items/{itemId}/uncheck')]
	#[NoAdminRequired]
	#[Permission(['canCheckItems'])]
	public function uncheckItem(int $houseId, int $sessionId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId, $itemId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$this->sessions->uncheckItem($session, $itemId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Review a shopping session
	 *
	 * Items checked this trip grouped by the store they were checked at, each
	 * with a per-currency estimate, no-price count and any amended billed total;
	 * plus the blended grand total and a soft count of items still unchecked.
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingReview, array{}>
	 *
	 * 200: Review returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/review')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function review(int $houseId, int $sessionId): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			return new DataResponse($this->sessions->review($session));
		});
	}

	/**
	 * My shopping-mode checks logged today
	 *
	 * Per-user, house-scoped, within the caller's timezone day; carries a
	 * per-currency estimate. Polled live in the dense view.
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingDoneToday, array{}>
	 *
	 * 200: Done-today returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/shopping-sessions/done-today')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function doneToday(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$tz = $this->dateTimeZone->getTimeZone();
			$startOfDay = (new \DateTimeImmutable('today', $tz))->getTimestamp();
			$startOfTomorrow = (new \DateTimeImmutable('tomorrow', $tz))->getTimestamp();
			return new DataResponse($this->sessions->doneToday($uid, $houseId, $startOfDay, $startOfTomorrow));
		});
	}

	/**
	 * Amend the actual paid amount for a store in a session
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 * @param int $storeId Store id (must be in the session's sequence).
	 * @param float|null $billedTotal Actual paid; null (or negative) clears it.
	 * @param string|null $billedCurrency Currency of the amount.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingSession, array{}>
	 *
	 * 200: Store billed amount amended
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}/stores/{storeId}')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function amendStoreBilled(int $houseId, int $sessionId, int $storeId, ?float $billedTotal = null, ?string $billedCurrency = null): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId, $storeId, $billedTotal, $billedCurrency): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$this->sessions->amendStoreBilled($session, $storeId, $billedTotal, $billedCurrency);
			return new DataResponse($this->sessions->composeDto($session));
		});
	}

	/**
	 * Amend the storeless-session grand total
	 *
	 * @param int $houseId House id.
	 * @param int $sessionId Session id.
	 * @param float|null $billedTotal Actual paid; null (or negative) clears it.
	 * @param string|null $billedCurrency Currency of the amount.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingSession, array{}>
	 *
	 * 200: Session billed amount amended
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/shopping-sessions/{sessionId}')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function amendSessionBilled(int $houseId, int $sessionId, ?float $billedTotal = null, ?string $billedCurrency = null): DataResponse {
		return $this->runAction(function () use ($houseId, $sessionId, $billedTotal, $billedCurrency): DataResponse {
			$session = $this->loadOwnedSession($sessionId, $houseId);
			$updated = $this->sessions->amendSessionBilled($session, $billedTotal, $billedCurrency);
			return new DataResponse($this->sessions->composeDto($updated));
		});
	}

	/**
	 * Assert the item belongs to one of the session's scope lists.
	 */
	private function assertItemInScope(ShoppingSession $session, int $itemId): void {
		$item = $this->lists->getItem($itemId);
		$listIds = $this->sessions->listIdsForSession((int)$session->getId());
		if (!in_array((int)$item->getListId(), $listIds, true)) {
			throw new NotFoundException('Item is not part of this session');
		}
	}

	/**
	 * Load a session and assert it belongs to this house and to the caller.
	 * Members can only act on their own session.
	 */
	private function loadOwnedSession(int $sessionId, int $houseId): ShoppingSession {
		$uid = $this->requireUid();
		$this->auth->requireMember($houseId, $uid);
		$session = $this->sessions->get($sessionId);
		if ($session->getHouseId() !== $houseId || $session->getUserId() !== $uid) {
			throw new NotFoundException('Shopping session not found');
		}
		return $session;
	}

	private function assertInHouse(int $entityHouseId, int $houseId): void {
		if ($entityHouseId !== $houseId) {
			throw new NotFoundException('Resource does not belong to this house');
		}
	}

	/**
	 * Serialize items, embedding each item's attached store ids in one batched
	 * query. A store-lookup failure degrades to empty store lists rather than
	 * failing the request (mirrors ChecklistController).
	 *
	 * @param ChecklistItem[] $items
	 * @return list<array<string, mixed>>
	 */
	private function serializeItems(array $items): array {
		$ids = array_map(static fn ($i) => (int)$i->getId(), $items);
		try {
			$storeMap = $this->itemStores->findStoreIdsForItems($ids);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load store ids for shopping items; serializing without stores', ['exception' => $e]);
			$storeMap = [];
		}
		return array_values(array_map(static function ($i) use ($storeMap) {
			$data = $i->jsonSerialize();
			$data['storeIds'] = $storeMap[(int)$i->getId()] ?? [];
			return $data;
		}, $items));
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}
}
