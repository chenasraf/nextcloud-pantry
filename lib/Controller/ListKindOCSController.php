<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Activity\ActivityPublisher;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Share;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Service\CategoryService;
use OCA\Pantry\Service\ChecklistService;
use OCA\Pantry\Service\FieldDefinitionService;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\ImageService;
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\LabelService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\ShareService;
use OCA\Pantry\Service\StoreService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * The list and item operations every list kind shares. Lists of all kinds live
 * in one table, but each kind is served from its own resource tree, so each
 * subclass serves one kind and a list of another kind reads as missing.
 *
 * Subclasses declare the routes (and their API docs) and delegate here.
 */
abstract class ListKindOCSController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		protected ChecklistService $lists,
		protected CategoryService $categories,
		protected LabelService $labels,
		protected StoreService $stores,
		protected ItemStoreMapper $itemStores,
		protected ItemLabelMapper $itemLabels,
		protected ItemPriceMapper $itemPrices,
		protected FieldValueMapper $fieldValues,
		protected FieldDefinitionService $fields,
		protected HouseAuthService $auth,
		protected HouseService $houses,
		protected ImageService $images,
		protected NotificationService $notifications,
		protected ActivityPublisher $activity,
		protected PrefsService $prefs,
		protected PermissionService $permissions,
		protected ShareService $shares,
		protected ItemDefaultsService $itemDefaults,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return 'checklist'|'template'
	 */
	abstract protected function kind(): string;

	/**
	 * Drop the fields this kind's lists do not have from a serialized list.
	 *
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	protected function shapeList(array $data): array {
		return $data;
	}

	/**
	 * Drop the fields this kind's items do not have from a serialized item.
	 *
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	protected function shapeItem(array $data): array {
		return $data;
	}

	/**
	 * Serialize a batch of items, embedding each item's attached store ids.
	 * The store-id lookup is batched into a single query to avoid N+1.
	 *
	 * Stores are an optional feature layered on top of items. If the lookup
	 * fails (e.g. the item-stores table is missing because its migration did
	 * not apply), we must not let that sink the whole item list — items render
	 * with no attached stores rather than the endpoint returning a 500.
	 *
	 * @param ChecklistItem[] $items
	 * @return list<array<string, mixed>>
	 */
	protected function serializeItems(array $items): array {
		$ids = array_map(static fn ($i) => (int)$i->getId(), $items);
		try {
			$storeMap = $this->itemStores->findStoreIdsForItems($ids);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load store ids for items; serializing without stores', ['exception' => $e]);
			$storeMap = [];
		}
		try {
			$priceMap = $this->itemPrices->findForItems($ids);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load prices for items; serializing without prices', ['exception' => $e]);
			$priceMap = [];
		}
		try {
			$labelMap = $this->itemLabels->findLabelIdsForItems($ids);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load label ids for items; serializing without labels', ['exception' => $e]);
			$labelMap = [];
		}
		try {
			$fieldMap = $this->fieldValues->findForItems($ids);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load custom fields for items; serializing without them', ['exception' => $e]);
			$fieldMap = [];
		}
		return array_values(array_map(function ($i) use ($storeMap, $priceMap, $labelMap, $fieldMap) {
			$data = $i->jsonSerialize();
			$data['storeIds'] = $storeMap[(int)$i->getId()] ?? [];
			$data['prices'] = $priceMap[(int)$i->getId()] ?? [];
			$data['labelIds'] = $labelMap[(int)$i->getId()] ?? [];
			$data['customFields'] = $fieldMap[(int)$i->getId()] ?? [];
			return $this->shapeItem($data);
		}, $items));
	}

	/**
	 * Serialize a single item, embedding its attached store ids.
	 *
	 * As with {@see serializeItems()}, a store-lookup failure degrades to an
	 * empty store list rather than failing the request.
	 *
	 * @return array<string, mixed>
	 */
	protected function serializeItem(ChecklistItem $item): array {
		$data = $item->jsonSerialize();
		try {
			$data['storeIds'] = $this->itemStores->findStoreIdsForItem((int)$item->getId());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load store ids for item; serializing without stores', ['exception' => $e]);
			$data['storeIds'] = [];
		}
		try {
			$data['prices'] = $this->itemPrices->findForItem((int)$item->getId());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load prices for item; serializing without prices', ['exception' => $e]);
			$data['prices'] = [];
		}
		try {
			$data['labelIds'] = $this->itemLabels->findLabelIdsForItem((int)$item->getId());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load label ids for item; serializing without labels', ['exception' => $e]);
			$data['labelIds'] = [];
		}
		try {
			$data['customFields'] = $this->fieldValues->findForItem((int)$item->getId());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to load custom fields for item; serializing without them', ['exception' => $e]);
			$data['customFields'] = [];
		}
		return $this->shapeItem($data);
	}

	// ----- Lists -----

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexLists(int $houseId, string $sortBy, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->lists->listForHouse($houseId, $sortBy, $this->kind());
			$all = array_values(array_filter(
				$all,
				fn ($l) => $this->permissions->canAccessList($houseId, $uid, (int)$l->getId()),
			));
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->listsJson($sliced, $houseId, $uid));
		});
	}

	/**
	 * @param array<string, mixed> $recurrenceDefault
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doCreateList(int $houseId, string $name, ?string $description, ?string $icon, ?string $color, array $recurrenceDefault = []): DataResponse {
		return $this->runAction(function () use ($houseId, $name, $description, $icon, $color, $recurrenceDefault): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->lists->createList($houseId, $name, $description, $icon, $color, $recurrenceDefault, $this->kind());
			$this->activity->publishListCreated(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse($this->oneListJson($list, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexDeletedLists(int $houseId, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->lists->listDeletedForHouse($houseId, $this->kind());
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->listsJson($sliced, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexArchivedLists(int $houseId, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$all = $this->lists->listArchivedForHouse($houseId, $this->kind());
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->listsJson($sliced, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doEmptyListsTrash(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->lists->emptyListsTrash($houseId, $this->kind());
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doShowList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			// Trashed and archived lists open into the normal list view, so fetch
			// them regardless of their soft-delete state.
			$list = $this->loadList($houseId, $listId, includeDeleted: true);
			return new DataResponse($this->oneListJson($list, $houseId, $uid));
		});
	}

	/**
	 * @param array<string, mixed> $patch Only the keys being changed.
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUpdateList(int $houseId, int $listId, array $patch): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $patch): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadList($houseId, $listId);
			$list = $this->lists->updateList($listId, $patch);
			$contentChanged = array_intersect(['name', 'description', 'icon', 'color'], array_keys($patch)) !== [];
			if ($contentChanged) {
				$this->activity->publishListUpdated(
					$houseId,
					$this->houses->get($houseId)->getName(),
					$uid,
					$listId,
					$list->getName(),
				);
			}
			return new DataResponse($this->oneListJson($list, $houseId, $uid));
		});
	}

	/**
	 * @param array<string, array<string, mixed>|list<array<string, mixed>>> $patch Only the keys being changed.
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUpdateItemDefaults(int $houseId, int $listId, array $patch): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $patch): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);

			$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
			$shareMap = $this->shares->userShareMap($houseId, $uid);
			$roleAccess = $this->permissions->hasRoleAccessToList($houseId, $uid, $listId);
			$canEdit = $this->canEditList($list, $canEditLists, $roleAccess, $shareMap);

			$list = $this->lists->setItemDefaults($list, $this->itemDefaults->merge($list, $patch, $canEdit));
			return new DataResponse($this->listJson($list, $canEditLists, $houseId, $uid, $shareMap));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doDeleteList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$existing = $this->loadList($houseId, $listId);
			$listName = $existing->getName();
			$this->lists->deleteList($listId);
			$this->activity->publishListDeleted(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$listId,
				$listName,
			);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doRestoreList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadList($houseId, $listId, includeDeleted: true);
			$restored = $this->lists->restoreList($listId);
			return new DataResponse($this->oneListJson($restored, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doArchiveList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadList($houseId, $listId);
			$archived = $this->lists->archiveList($listId);
			return new DataResponse($this->oneListJson($archived, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUnarchiveList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadList($houseId, $listId, includeDeleted: true);
			$unarchived = $this->lists->unarchiveList($listId);
			return new DataResponse($this->oneListJson($unarchived, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doDuplicateList(int $houseId, int $listId, string $name, bool $resetDone): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $resetDone): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadList($houseId, $listId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $listId)) {
				throw new ForbiddenException('No access to this list');
			}

			$copy = $this->lists->duplicateList(
				$listId,
				$name,
				$resetDone,
				$uid,
				fn (int $fileId, string $ownerUid): ?int => $this->images->duplicateItemImage(
					$ownerUid,
					$fileId,
					$uid,
					$houseId,
				),
			);

			$this->activity->publishListCreated(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$copy->getId(),
				$copy->getName(),
			);
			return new DataResponse($this->oneListJson($copy, $houseId, $uid));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doPermanentlyDeleteList(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadList($houseId, $listId, includeDeleted: true);
			$this->lists->permanentlyDeleteList($listId);
			return new DataResponse(['success' => true]);
		});
	}

	// ----- Items -----

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexItems(int $houseId, int $listId, string $sortBy, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $sortBy, $limit, $offset): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			// A trashed or archived list still opens into the normal item view.
			$this->loadList($houseId, $listId, includeDeleted: true);
			$categorySort = $this->prefs->getCategorySort($uid, $houseId);
			$all = $this->lists->listItems($listId, $sortBy, $categorySort);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->serializeItems($sliced));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexDeletedItems(int $houseId, int $listId, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadList($houseId, $listId);
			$all = $this->lists->listDeletedItems($listId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->serializeItems($sliced));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 */
	protected function doIndexArchivedItems(int $houseId, int $listId, int $limit, int $offset): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $limit, $offset): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadList($houseId, $listId);
			$all = $this->lists->listArchivedItems($listId);
			$sliced = array_slice($all, max(0, $offset), max(0, $limit));
			return new DataResponse($this->serializeItems($sliced));
		});
	}

	/**
	 * @param array{name: string, description: string|null, categoryId: int|null, quantity: string|null, rrule: string|null, repeatFromCompletion: bool, deleteOnDone: bool, sortOrder: int|null, storeIds: list<int>, barcode: string|null, prices: list<array<string, mixed>>, labelIds: list<int>, customFields: list<array<string, mixed>>} $data
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doAddItem(int $houseId, int $listId, array $data): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $data): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);
			if ($data['categoryId'] !== null) {
				$this->categories->assertInHouse($data['categoryId'], $houseId);
			}
			$storeIds = array_map('intval', $data['storeIds']);
			$this->stores->assertStoresInHouse($houseId, $storeIds);
			$item = $this->lists->addItem($listId, [
				'name' => $data['name'],
				'description' => $data['description'],
				'categoryId' => $data['categoryId'],
				'quantity' => $data['quantity'],
				'rrule' => $data['rrule'],
				'repeatFromCompletion' => $data['repeatFromCompletion'],
				'deleteOnDone' => $data['deleteOnDone'],
				'sortOrder' => $data['sortOrder'] ?? 0,
				'storeIds' => $storeIds,
				'barcode' => $data['barcode'],
				'prices' => $data['prices'],
				'labelIds' => $this->labels->assertLabelsInHouse($houseId, $data['labelIds']),
				'customFields' => $this->fields->sanitizeValuesForItem($houseId, $listId, $data['customFields']),
			], $uid);
			$this->notifications->notifyItemAdded($houseId, $uid, $item->getName(), $list->getName());
			$this->activity->publishItemAdded(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$item->getId(),
				$item->getName(),
				$listId,
				$list->getName(),
			);
			return new DataResponse($this->serializeItem($item));
		});
	}

	/**
	 * @param list<int>|null $storeIds
	 * @param list<array<string, mixed>>|null $prices
	 * @param list<int>|null $labelIds
	 * @param list<array<string, mixed>>|null $customFields
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUpdateItem(
		int $houseId,
		int $listId,
		int $itemId,
		?string $name,
		?string $description,
		?int $categoryId,
		?string $quantity,
		?string $rrule,
		?bool $repeatFromCompletion,
		?bool $deleteOnDone,
		?int $imageFileId,
		?int $sortOrder,
		?int $targetListId,
		?array $storeIds,
		?string $barcode,
		?array $prices,
		?array $labelIds,
		?array $customFields,
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $imageFileId, $sortOrder, $targetListId, $storeIds, $barcode, $prices, $labelIds, $customFields): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[, $list] = $this->loadItem($houseId, $listId, $itemId);
			$patch = [];
			if ($name !== null) {
				$patch['name'] = $name;
			}
			if ($description !== null) {
				$patch['description'] = $description;
			}
			if ($categoryId !== null) {
				if ($categoryId > 0) {
					$this->categories->assertInHouse($categoryId, $houseId);
					$patch['categoryId'] = $categoryId;
				} else {
					$patch['categoryId'] = null;
				}
			}
			if ($quantity !== null) {
				$patch['quantity'] = $quantity;
			}
			if ($rrule !== null) {
				$patch['rrule'] = $rrule;
			}
			if ($repeatFromCompletion !== null) {
				$patch['repeatFromCompletion'] = $repeatFromCompletion;
			}
			if ($deleteOnDone !== null) {
				$patch['deleteOnDone'] = $deleteOnDone;
			}
			if ($imageFileId !== null) {
				$patch['imageFileId'] = $imageFileId > 0 ? $imageFileId : null;
			}
			if ($sortOrder !== null) {
				$patch['sortOrder'] = $sortOrder;
			}
			if ($barcode !== null) {
				$patch['barcode'] = $barcode;
			}
			// Prices are replaced as a whole set. A null value leaves them
			// unchanged; an empty array clears them.
			if ($prices !== null) {
				$patch['prices'] = $prices;
			}
			if ($storeIds !== null) {
				$ids = array_map('intval', $storeIds);
				$this->stores->assertStoresInHouse($houseId, $ids);
				$patch['storeIds'] = $ids;
			}
			if ($labelIds !== null) {
				$patch['labelIds'] = $this->labels->assertLabelsInHouse($houseId, $labelIds);
			}
			// Values are validated against the destination list when the item is
			// being moved, otherwise its current list.
			if ($customFields !== null) {
				$effectiveListId = ($targetListId !== null && $targetListId !== $listId) ? $targetListId : $listId;
				$patch['customFields'] = $this->fields->sanitizeValuesForItem($houseId, $effectiveListId, $customFields);
			}
			$targetList = null;
			if ($targetListId !== null && $targetListId !== $listId) {
				// Moving an item to another list needs the move capability and
				// access to the destination list (admins bypass both).
				if (!$this->permissions->isAdmin($houseId, $uid)) {
					if (!$this->permissions->can($houseId, $uid, 'canMoveItems')) {
						throw new ForbiddenException('Missing permission: canMoveItems');
					}
					if (!$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
						throw new ForbiddenException('No access to the destination list');
					}
				}
				$targetList = $this->loadList($houseId, $targetListId);
				$patch['listId'] = $targetListId;
			}
			$updated = $this->lists->updateItem($itemId, $patch);

			$houseName = $this->houses->get($houseId)->getName();
			if ($targetList !== null && $targetList->getId() !== $list->getId()) {
				$this->activity->publishItemMoved(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$updated->getName(),
					(int)$list->getId(),
					$list->getName(),
					(int)$targetList->getId(),
					$targetList->getName(),
				);
			} elseif ($name !== null || $description !== null || $categoryId !== null || $quantity !== null || $rrule !== null || $repeatFromCompletion !== null || $deleteOnDone !== null || $imageFileId !== null || $storeIds !== null || $labelIds !== null || $prices !== null || $customFields !== null) {
				$this->activity->publishItemUpdated(
					$houseId,
					$houseName,
					$uid,
					$itemId,
					$updated->getName(),
					(int)$list->getId(),
					$list->getName(),
				);
			}
			return new DataResponse($this->serializeItem($updated));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doCopyItem(int $houseId, int $listId, int $itemId, int $targetListId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $targetListId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[$source, $sourceList] = $this->loadItem($houseId, $listId, $itemId);
			$targetList = $this->loadList($houseId, $targetListId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $targetListId)) {
				throw new ForbiddenException('No access to the destination list');
			}

			[$newImageFileId, $newImageOwner] = $this->duplicateImageFor($source, $houseId, $uid);
			$copy = $this->lists->copyItem($itemId, $targetListId, $uid, $newImageFileId, $newImageOwner);

			$this->activity->publishItemCopied(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				(int)$copy->getId(),
				$copy->getName(),
				(int)$sourceList->getId(),
				$sourceList->getName(),
				(int)$targetList->getId(),
				$targetList->getName(),
			);
			return new DataResponse($this->serializeItem($copy));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doEmptyTrash(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadList($houseId, $listId);
			$this->lists->emptyTrash($listId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doDeleteItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[$item, $list] = $this->loadItem($houseId, $listId, $itemId);
			$itemName = $item->getName();
			$this->lists->deleteItem($itemId);
			$this->activity->publishItemDeleted(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$itemId,
				$itemName,
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doRestoreItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[, $list] = $this->loadItem($houseId, $listId, $itemId, includeDeleted: true);
			$restored = $this->lists->restoreItem($itemId);
			$this->activity->publishItemRestored(
				$houseId,
				$this->houses->get($houseId)->getName(),
				$uid,
				$itemId,
				$restored->getName(),
				(int)$list->getId(),
				$list->getName(),
			);
			return new DataResponse($this->serializeItem($restored));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doPermanentlyDeleteItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadItem($houseId, $listId, $itemId, includeDeleted: true);
			$this->lists->permanentlyDeleteItem($itemId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doArchiveItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadItem($houseId, $listId, $itemId);
			return new DataResponse($this->serializeItem($this->lists->archiveItem($itemId)));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUnarchiveItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadItem($houseId, $listId, $itemId);
			return new DataResponse($this->serializeItem($this->lists->unarchiveItem($itemId)));
		});
	}

	/**
	 * @param list<array{id: int, sortOrder: int}> $items
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 */
	protected function doReorderItems(int $houseId, int $listId, array $items): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadList($houseId, $listId);
			$this->lists->reorderItems($listId, $items);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUploadItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->loadItem($houseId, $listId, $itemId);

			$data = $this->request->getUploadedFile('image');
			if ($data === null || !is_array($data) || ($data['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new \InvalidArgumentException('No image uploaded');
			}
			$tmp = (string)($data['tmp_name'] ?? '');
			if ($tmp === '' || !is_uploaded_file($tmp)) {
				throw new \InvalidArgumentException('Invalid upload');
			}
			$bytes = file_get_contents($tmp);
			if ($bytes === false) {
				throw new \RuntimeException('Could not read uploaded file');
			}
			$original = (string)($data['name'] ?? 'image.jpg');
			$fileId = $this->images->uploadForUser($uid, $houseId, $original, $bytes);

			$updated = $this->lists->updateItem($itemId, ['imageFileId' => $fileId, 'imageUploadedBy' => $uid]);
			return new DataResponse($this->serializeItem($updated));
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doClearItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->loadItem($houseId, $listId, $itemId);
			$updated = $this->lists->updateItem($itemId, ['imageFileId' => null, 'imageUploadedBy' => null]);
			return new DataResponse($this->serializeItem($updated));
		});
	}

	// ----- Helpers -----

	/**
	 * Duplicate a source item's image file for a copy, so deleting either
	 * side's image never affects the other.
	 *
	 * @return array{0: int|null, 1: string|null} The new file id and its owner.
	 */
	protected function duplicateImageFor(ChecklistItem $source, int $houseId, string $uid): array {
		$sourceImageFileId = $source->getImageFileId();
		$sourceImageOwner = $source->getImageUploadedBy();
		if ($sourceImageFileId === null || $sourceImageOwner === null) {
			return [null, null];
		}
		$newImageFileId = $this->images->duplicateItemImage(
			$sourceImageOwner,
			$sourceImageFileId,
			$uid,
			$houseId,
		);
		return [$newImageFileId, $newImageFileId !== null ? $uid : null];
	}

	/**
	 * Load a list of this resource's kind in the route's house.
	 */
	protected function loadList(int $houseId, int $listId, bool $includeDeleted = false): Checklist {
		$list = $this->lists->getList($listId, $includeDeleted, $this->kind());
		$this->assertListInHouse($list->getHouseId(), $houseId);
		return $list;
	}

	/**
	 * Load an item and its list, checking the item sits on the route's list.
	 *
	 * @return array{0: ChecklistItem, 1: Checklist}
	 */
	protected function loadItem(int $houseId, int $listId, int $itemId, bool $includeDeleted = false): array {
		$item = $this->lists->getItem($itemId, $includeDeleted);
		$list = $this->loadList($houseId, $item->getListId());
		if ($item->getListId() !== $listId) {
			throw new NotFoundException('Item does not belong to this list');
		}
		return [$item, $list];
	}

	/**
	 * @param Checklist[] $lists
	 * @return list<array<string, mixed>>
	 */
	private function listsJson(array $lists, int $houseId, string $uid): array {
		$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
		$shareMap = $this->shares->userShareMap($houseId, $uid);
		return array_values(array_map(fn ($l) => $this->listJson($l, $canEditLists, $houseId, $uid, $shareMap), $lists));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function oneListJson(Checklist $list, int $houseId, string $uid): array {
		$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
		return $this->listJson($list, $canEditLists, $houseId, $uid, $this->shares->userShareMap($houseId, $uid));
	}

	/**
	 * Serialize a list with the current user's effective sharing flags:
	 * - `canEdit`: an editor share, or role access to the list plus the
	 *   edit-lists capability.
	 * - `sharedOnly`: the user reaches this list only through a per-user share
	 *   (no role grants access). A viewer share then bounds writes to read-only,
	 *   while role-based access keeps the granular per-action capabilities.
	 *
	 * @param array<string, array<int, string>> $shareMap
	 * @return array<string, mixed>
	 */
	private function listJson(Checklist $list, bool $canEditLists, int $houseId, string $uid, array $shareMap): array {
		$roleAccess = $this->permissions->hasRoleAccessToList($houseId, $uid, (int)$list->getId());
		return $this->shapeList(array_merge($list->jsonSerialize(), [
			'itemDefaults' => $this->itemDefaults->forList($list),
			'canEdit' => $this->canEditList($list, $canEditLists, $roleAccess, $shareMap),
			'sharedOnly' => !$roleAccess,
		]));
	}

	/**
	 * @param array<string, array<int, string>> $shareMap
	 */
	private function canEditList(Checklist $list, bool $canEditLists, bool $roleAccess, array $shareMap): bool {
		return $this->shares->canEditFromMap(Share::TYPE_CHECKLIST, (int)$list->getId(), $canEditLists && $roleAccess, $shareMap);
	}

	protected function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}

	protected function assertListInHouse(int $listHouseId, int $routeHouseId): void {
		if ($listHouseId !== $routeHouseId) {
			throw new NotFoundException('List does not belong to this house');
		}
	}
}
