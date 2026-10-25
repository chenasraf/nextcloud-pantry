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
use OCA\Pantry\Service\ItemDefaults;
use OCA\Pantry\Service\ItemDefaultsService;
use OCA\Pantry\Service\LabelService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\PrefsService;
use OCA\Pantry\Service\RestockService;
use OCA\Pantry\Service\RestockSyncService;
use OCA\Pantry\Service\ShareService;
use OCA\Pantry\Service\StockLinkService;
use OCA\Pantry\Service\StoreService;
use OCA\Pantry\Service\TemplateAttachmentService;
use OCA\Pantry\Service\TemplateCopyService;
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
		protected TemplateAttachmentService $attachments,
		protected TemplateCopyService $templateCopy,
		protected StockLinkService $stockLinks,
		protected RestockSyncService $restockSync,
		protected RestockService $restock,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Item fields a template item does not have: the done state, the reopen
	 * schedule, a pinned quantity, the stock level and the expiry date. A template keeps how its
	 * items are stocked and how long they keep, never how much is on hand.
	 */
	protected const TEMPLATE_DROPPED_ITEM_FIELDS = ['done', 'doneAt', 'doneBy', 'nextDueAt', 'stockLevel', 'expiresAt', 'quantityPinned', 'restock'];

	/** Item fields only an inventory item has. */
	protected const STOCK_ITEM_FIELDS = ['stockMode', 'stockLevel', 'target', 'reorderPoint', 'expiresAt', 'shelfLifeDays'];

	/**
	 * @return 'checklist'|'template'|'inventory'
	 */
	abstract protected function kind(): string;

	/**
	 * Drop the fields this kind's lists do not have from a serialized list.
	 *
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	protected function shapeList(array $data): array {
		return $this->withoutStockDefaults($data);
	}

	/**
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	protected function withoutStockDefaults(array $data): array {
		$defaults = $data['itemDefaults'] ?? null;
		if (is_array($defaults)) {
			$data['itemDefaults'] = array_diff_key($defaults, array_flip(ItemDefaults::STOCK_KEYS));
		}
		return $data;
	}

	/**
	 * Drop the fields this kind's items do not have from a serialized item.
	 *
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	protected function shapeItem(array $data): array {
		return array_diff_key($data, array_flip(self::STOCK_ITEM_FIELDS));
	}

	/**
	 * Load, for a batch of items about to be shaped, what {@see shapeItem()}
	 * adds from elsewhere, so it is looked up once per batch.
	 *
	 * @param ChecklistItem[] $items
	 */
	protected function prepareItems(array $items): void {
	}

	/**
	 * The item's serialized fields, with the stock fields that only an
	 * inventory's shape keeps.
	 *
	 * @return array<string, mixed>
	 */
	private function itemData(ChecklistItem $item): array {
		return $item->jsonSerialize() + [
			'stockMode' => $item->getStockMode(),
			'stockLevel' => $item->getStockLevel(),
			'target' => $item->getTarget(),
			'reorderPoint' => $item->getReorderPoint(),
			'expiresAt' => $item->getExpiresAt(),
			'shelfLifeDays' => $item->getShelfLifeDays(),
		];
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
	 * `$shape` shapes each item; it defaults to this resource's kind.
	 *
	 * @param ChecklistItem[] $items
	 * @param (callable(array<array-key, mixed>): array<array-key, mixed>)|null $shape
	 * @return list<array<string, mixed>>
	 */
	protected function serializeItems(array $items, ?callable $shape = null): array {
		$shape ??= $this->shapeItem(...);
		$this->prepareItems($items);
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
		return array_values(array_map(function ($i) use ($storeMap, $priceMap, $labelMap, $fieldMap, $shape) {
			$data = $this->itemData($i);
			$data['storeIds'] = $storeMap[(int)$i->getId()] ?? [];
			$data['prices'] = $priceMap[(int)$i->getId()] ?? [];
			$data['labelIds'] = $labelMap[(int)$i->getId()] ?? [];
			$data['customFields'] = $fieldMap[(int)$i->getId()] ?? [];
			return $shape($data);
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
		$this->prepareItems([$item]);
		$data = $this->itemData($item);
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
		$data = $this->shapeItem($data);
		$events = $this->restockEvents[(int)$item->getId()] ?? [];
		if ($events !== []) {
			$data['restockSync'] = $events;
		}
		foreach ($this->restockFeedback[(int)$item->getId()] ?? [] as $key => $entries) {
			if ($entries !== []) {
				$data[$key] = $entries;
			}
		}
		return $data;
	}

	/** @var array<int, array{restocked: list<array<string, mixed>>, restockUndone: list<array<string, mixed>>}> What a member's tick or uncheck did to stock, by the checklist item, for its response. */
	private array $restockFeedback = [];

	/**
	 * Remember what a member's tick or uncheck did to stock, and what the
	 * restock sync did after it, so the item's response tells the member.
	 *
	 * @param array{item: ChecklistItem, restocked: list<array<string, mixed>>, undone: list<array<string, mixed>>, events: list<array<string, mixed>>} $result
	 */
	protected function withRestock(array $result): ChecklistItem {
		$item = $result['item'];
		$this->restockFeedback[(int)$item->getId()] = [
			'restocked' => $result['restocked'],
			'restockUndone' => $result['undone'],
		];
		if ($result['events'] !== []) {
			$this->restockEvents[(int)$item->getId()] = $result['events'];
		}
		return $item;
	}

	/**
	 * What the member's tick of the checklist item put back into stock, as
	 * remembered by {@see withRestock()}.
	 *
	 * @return list<array<string, mixed>>
	 */
	protected function restockedBy(int $itemId): array {
		return $this->restockFeedback[$itemId]['restocked'] ?? [];
	}

	/** @var array<int, list<array<string, mixed>>> What restock syncs did, by the item whose write caused them, for its response. */
	private array $restockEvents = [];

	/**
	 * Remember what a restock sync did, so the item's response tells the
	 * member, and re-read the item, which the sync may have linked.
	 *
	 * @param list<array<string, mixed>> $events
	 */
	protected function withRestockEvents(ChecklistItem $item, array $events): ChecklistItem {
		if ($events === []) {
			return $item;
		}
		$this->restockEvents[(int)$item->getId()] = $events;
		return $this->lists->getItem((int)$item->getId(), includeDeleted: true);
	}

	/**
	 * Called after an item is added, updated, copied or drawn here, with the
	 * item as it was before (null for a new one).
	 */
	protected function afterItemWrite(ChecklistItem $item, ?ChecklistItem $before): ChecklistItem {
		return $item;
	}

	/**
	 * Called after a list's settings are updated, with the list as it was
	 * before.
	 */
	protected function afterListUpdated(Checklist $list, Checklist $before): void {
	}

	/**
	 * Called after a list is restored from the trash or the archive.
	 */
	protected function afterListBackInUse(Checklist $list): void {
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
			$before = $this->loadList($houseId, $listId);
			$list = $this->lists->updateList($listId, $patch);
			$this->afterListUpdated($list, $before);
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
	 * @param bool|null $fillFromDefaults Null leaves the list's fill switch as it is.
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doUpdateItemDefaults(int $houseId, int $listId, array $patch, ?bool $fillFromDefaults = null): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $patch, $fillFromDefaults): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);

			$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
			$shareMap = $this->shares->userShareMap($houseId, $uid);
			$roleAccess = $this->permissions->hasRoleAccessToList($houseId, $uid, $listId);
			$canEdit = $this->canEditList($list, $canEditLists, $roleAccess, $shareMap);

			if ($fillFromDefaults !== null && !$canEdit) {
				throw new ForbiddenException('Only list editors can change whether drawn items fill from item defaults');
			}
			$list = $this->lists->setItemDefaults($list, $this->itemDefaults->merge($list, $patch, $canEdit), $fillFromDefaults);
			return new DataResponse($this->listJson($list, $canEditLists, $houseId, $uid, $shareMap, $this->listExtras([$list])[(int)$list->getId()] ?? []));
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
			$this->afterListBackInUse($restored);
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
			$this->afterListBackInUse($unarchived);
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
			return $this->duplicateAs($houseId, $uid, $listId, $name, $resetDone);
		});
	}

	/**
	 * Duplicate a list of any kind as a list of this resource's kind.
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	protected function doDuplicateFrom(int $houseId, int $listId, string $name, bool $resetDone): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $name, $resetDone): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$this->assertListInHouse($this->lists->getList($listId)->getHouseId(), $houseId);
			return $this->duplicateAs($houseId, $uid, $listId, $name, $resetDone);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 */
	private function duplicateAs(int $houseId, string $uid, int $listId, string $name, bool $resetDone): DataResponse {
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
			$this->kind(),
		);
		$this->attachments->copyAttachments($this->lists->getList($listId), $copy);

		$this->activity->publishListCreated(
			$houseId,
			$this->houses->get($houseId)->getName(),
			$uid,
			(int)$copy->getId(),
			$copy->getName(),
		);
		return new DataResponse($this->oneListJson($copy, $houseId, $uid));
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
	 * @param array{name: string, description: string|null, categoryId: int|null, quantity: string|null, rrule: string|null, repeatFromCompletion: bool, deleteOnDone: bool, sortOrder: int|null, storeIds: list<int>, barcode: string|null, prices: list<array<string, mixed>>, labelIds: list<int>, customFields: list<array<string, mixed>>, stockMode?: string|null, stockLevel?: string|null, target?: string|null, reorderPoint?: string|null, expiresAt?: int|null, shelfLifeDays?: int|null} $data
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
				'stockMode' => $data['stockMode'] ?? null,
				'stockLevel' => $data['stockLevel'] ?? null,
				'target' => $data['target'] ?? null,
				'reorderPoint' => $data['reorderPoint'] ?? null,
				'expiresAt' => $data['expiresAt'] ?? null,
				'shelfLifeDays' => $data['shelfLifeDays'] ?? null,
			], $uid);
			$item = $this->afterItemWrite($item, null);
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
	 * @param array<string, string|int|bool> $stock Stock fields to change; an empty string clears a target or reorder point, 0 an expiry date or shelf life.
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
		array $stock = [],
	): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $imageFileId, $sortOrder, $targetListId, $storeIds, $barcode, $prices, $labelIds, $customFields, $stock): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[$before, $list] = $this->loadItem($houseId, $listId, $itemId);
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
			$patch += $stock;
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
			$updated = $this->afterItemWrite($this->lists->updateItem($itemId, $patch), $before);

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
			} elseif ($name !== null || $description !== null || $categoryId !== null || $quantity !== null || $rrule !== null || $repeatFromCompletion !== null || $deleteOnDone !== null || $imageFileId !== null || $storeIds !== null || $labelIds !== null || $prices !== null || $customFields !== null || $stock !== []) {
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
			$copy = $this->afterItemWrite($copy, null);

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

	/**
	 * Draw a template item onto this list under the template-copy rule.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{item: array<string, mixed>, reused: bool}, array{}>
	 */
	protected function doDrawItem(int $houseId, int $listId, int $templateItemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $templateItemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);
			$source = $this->lists->getItem($templateItemId);
			$template = $this->lists->getList($source->getListId());
			$this->assertListInHouse($template->getHouseId(), $houseId);
			$canAccess = $this->accessCheck($houseId, $uid);
			if (!$canAccess($listId) || !$canAccess((int)$template->getId())) {
				throw new ForbiddenException('No access to this list or template');
			}

			$result = $this->templateCopy->draw(
				$source,
				$list,
				$uid,
				fn (int $fileId, string $ownerUid): ?int => $this->images->duplicateItemImage($ownerUid, $fileId, $uid, $houseId),
			);
			$item = $result['item'];
			if (!$result['reused']) {
				$item = $this->afterItemWrite($item, null);
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
			}
			return new DataResponse(['item' => $this->serializeItem($item), 'reused' => $result['reused']]);
		});
	}

	// ----- Attached templates -----

	/**
	 * @return DataResponse<Http::STATUS_OK, array{templateIds: list<int>, everyListTemplateIds: list<int>}, array{}>
	 */
	protected function doListTemplates(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId, includeDeleted: true);
			return new DataResponse($this->attachments->forList($list));
		});
	}

	/**
	 * @param list<int> $templateIds
	 * @return DataResponse<Http::STATUS_OK, array{templateIds: list<int>, everyListTemplateIds: list<int>}, array{}>
	 */
	protected function doSetListTemplates(int $houseId, int $listId, array $templateIds): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $templateIds): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);
			$this->attachments->setForList($list, $templateIds, $this->accessCheck($houseId, $uid));
			return new DataResponse($this->attachments->forList($list));
		});
	}

	/**
	 * The list's archived items, when asked for, and the items of its attached
	 * templates the caller can access, one row per name.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{archived: list<array<string, mixed>>, templates: list<array{item: array<string, mixed>, templates: list<array{id: int, name: string}>}>}, array{}>
	 */
	protected function doSuggestions(int $houseId, int $listId, bool $archived): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $archived): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$list = $this->loadList($houseId, $listId);
			if (!$this->permissions->isAdmin($houseId, $uid)
				&& !$this->permissions->canAccessList($houseId, $uid, $listId)) {
				throw new ForbiddenException('No access to this list');
			}

			$rows = $this->attachments->suggestions($list, $this->accessCheck($houseId, $uid));
			$templateItems = $this->serializeItems(
				array_map(static fn (array $row): ChecklistItem => $row['item'], $rows),
				static fn (array $data): array => array_diff_key($data, array_flip(self::TEMPLATE_DROPPED_ITEM_FIELDS)),
			);
			$templates = [];
			foreach ($rows as $i => $row) {
				$templates[] = ['item' => $templateItems[$i], 'templates' => $row['templates']];
			}

			return new DataResponse([
				'archived' => $archived ? $this->serializeItems($this->lists->listArchivedItems($listId)) : [],
				'templates' => $templates,
			]);
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{everyList: bool, listIds: list<int>}, array{}>
	 */
	protected function doTemplateLists(int $houseId, int $listId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$template = $this->loadList($houseId, $listId, includeDeleted: true);
			return new DataResponse($this->attachments->forTemplate($template));
		});
	}

	/**
	 * @param list<int> $listIds
	 * @return DataResponse<Http::STATUS_OK, array{everyList: bool, listIds: list<int>}, array{}>
	 */
	protected function doSetTemplateLists(int $houseId, int $listId, bool $everyList, array $listIds): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $everyList, $listIds): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$template = $this->loadList($houseId, $listId);
			$this->attachments->setForTemplate($template, $everyList, $listIds, $this->accessCheck($houseId, $uid));
			return new DataResponse($this->attachments->forTemplate($template));
		});
	}

	// ----- Helpers -----

	/**
	 * @return callable(int $listId): bool Whether the caller can access a list.
	 */
	private function accessCheck(int $houseId, string $uid): callable {
		if ($this->permissions->isAdmin($houseId, $uid)) {
			return static fn (int $listId): bool => true;
		}
		$cache = [];
		return function (int $listId) use ($houseId, $uid, &$cache): bool {
			return $cache[$listId] ??= $this->permissions->canAccessList($houseId, $uid, $listId);
		};
	}

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
		$extras = $this->listExtras($lists);
		return array_values(array_map(fn ($l) => $this->listJson($l, $canEditLists, $houseId, $uid, $shareMap, $extras[(int)$l->getId()] ?? []), $lists));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function oneListJson(Checklist $list, int $houseId, string $uid): array {
		$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
		$extras = $this->listExtras([$list])[(int)$list->getId()] ?? [];
		return $this->listJson($list, $canEditLists, $houseId, $uid, $this->shares->userShareMap($houseId, $uid), $extras);
	}

	/**
	 * Fields this kind adds to each serialized list, computed for the whole
	 * batch at once.
	 *
	 * @param Checklist[] $lists
	 * @return array<int, array<string, mixed>> List id → its extra fields.
	 */
	protected function listExtras(array $lists): array {
		return [];
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
	 * @param array<string, mixed> $extras
	 * @return array<string, mixed>
	 */
	private function listJson(Checklist $list, bool $canEditLists, int $houseId, string $uid, array $shareMap, array $extras = []): array {
		$roleAccess = $this->permissions->hasRoleAccessToList($houseId, $uid, (int)$list->getId());
		return $this->shapeList(array_merge($list->jsonSerialize(), [
			'itemDefaults' => $this->itemDefaults->forList($list),
			'canEdit' => $this->canEditList($list, $canEditLists, $roleAccess, $shareMap),
			'sharedOnly' => !$roleAccess,
		], $extras));
	}

	/**
	 * @return callable(Checklist $list): bool Whether the caller can edit a list.
	 */
	protected function editCheck(int $houseId, string $uid): callable {
		$canEditLists = $this->permissions->can($houseId, $uid, 'canEditLists');
		$shareMap = $this->shares->userShareMap($houseId, $uid);
		return fn (Checklist $list): bool => $this->canEditList(
			$list,
			$canEditLists,
			$this->permissions->hasRoleAccessToList($houseId, $uid, (int)$list->getId()),
			$shareMap,
		);
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
