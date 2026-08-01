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
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Shopping Mode — session lifecycle (create/current/advance/close) plus the
 * live, store-narrowed shopping list. Checking an item off reuses the existing
 * item toggle endpoint; a checked item leaves scope on the next `items` fetch.
 *
 * @psalm-import-type PantryShoppingSession from ResponseDefinitions
 * @psalm-import-type PantryListItem from ResponseDefinitions
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
