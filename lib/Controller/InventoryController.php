<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\RestockSyncService;
use OCA\Pantry\Service\StockRules;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;

/**
 * The inventory resource: lists of what the house keeps on hand. Inventory
 * items are never checked off and have no recurrence.
 *
 * @psalm-import-type PantryInventory from ResponseDefinitions
 * @psalm-import-type PantryInventoryItem from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 * @psalm-import-type PantryInventorySuggestions from ResponseDefinitions
 * @psalm-import-type PantryListTemplates from ResponseDefinitions
 * @psalm-import-type PantryInventoryDrawResult from ResponseDefinitions
 * @psalm-import-type PantryStockLinkRow from ResponseDefinitions
 */
final class InventoryController extends ListKindOCSController {
	use TranslatesDomainExceptions;

	/** Checklist-only list fields: completion and the per-column recurrence default. */
	private const CHECKLIST_LIST_FIELDS = [
		'deleteOnDoneDefault',
		'defaultRecurrenceMode',
		'defaultRecurrenceKind',
		'defaultRrule',
		'defaultRepeatFromCompletion',
		'lastCompletedAt',
	];

	/** Checklist-only item fields: the done state and the recurrence. */
	private const CHECKLIST_ITEM_FIELDS = ['done', 'doneAt', 'doneBy', 'nextDueAt', 'rrule', 'repeatFromCompletion', 'deleteOnDone', 'quantityPinned'];

	protected function kind(): string {
		return Checklist::KIND_INVENTORY;
	}

	protected function shapeList(array $data): array {
		$data = array_diff_key($data, array_flip(self::CHECKLIST_LIST_FIELDS));
		$defaults = $data['itemDefaults'] ?? null;
		if (is_array($defaults)) {
			unset($defaults['recurrence']);
			$data['itemDefaults'] = $defaults;
		}
		return $data;
	}

	/** @var array<int, int> Expiry warning per inventory, memoized while shaping its items. */
	private array $expiryLeadDays = [];

	protected function listExtras(array $lists): array {
		$counts = $this->lists->countNeedsRestock($lists);
		$extras = [];
		foreach ($lists as $list) {
			$extras[(int)$list->getId()] = [
				'needsRestock' => $counts[(int)$list->getId()] ?? 0,
				'expiryLeadDays' => $list->expiryLeadDays(),
				'restockChecklistId' => $this->stockLinks->restockChecklist($list)?->getId(),
			];
		}
		return $extras;
	}

	private function expiryLeadDays(int $listId): int {
		if (!isset($this->expiryLeadDays[$listId])) {
			try {
				$this->expiryLeadDays[$listId] = $this->lists->getList($listId, includeDeleted: true)->expiryLeadDays();
			} catch (\Throwable) {
				$this->expiryLeadDays[$listId] = Checklist::DEFAULT_EXPIRY_LEAD_DAYS;
			}
		}
		return $this->expiryLeadDays[$listId];
	}

	/** @var array<int, array{autoLink: bool, stockLink: array<string, mixed>|null, linkCandidates: list<array<string, mixed>>}> Link fields per item, loaded by {@see prepareItems()}. */
	private array $links = [];

	protected function prepareItems(array $items): void {
		$this->links = $this->stockLinks->describe($items) + $this->links;
	}

	protected function shapeItem(array $data): array {
		$data = array_diff_key($data, array_flip(self::CHECKLIST_ITEM_FIELDS));
		$text = static function (string $key) use ($data): ?string {
			$value = $data[$key] ?? null;
			return is_string($value) ? $value : null;
		};
		$level = $text('stockLevel');
		$stock = [
			'stockMode' => $text('stockMode') === StockRules::MODE_LEVEL ? StockRules::MODE_LEVEL : StockRules::MODE_COUNT,
			'stockLevel' => $level !== null && in_array($level, StockRules::LEVELS, true) ? $level : StockRules::LEVEL_FULL,
			'quantity' => $text('quantity'),
			'target' => $text('target'),
			'reorderPoint' => $text('reorderPoint'),
		];
		$int = static function (string $key) use ($data): ?int {
			$value = $data[$key] ?? null;
			return is_int($value) ? $value : null;
		};
		$expiry = [
			'expiresAt' => $int('expiresAt'),
			'shelfLifeDays' => $int('shelfLifeDays'),
		];
		$listId = $int('listId') ?? 0;
		$links = $this->links[$int('id') ?? 0] ?? ['autoLink' => true, 'stockLink' => null, 'linkCandidates' => []];
		return array_merge($data, $stock, StockRules::evaluate($stock), $expiry, [
			'expiryStatus' => StockRules::expiryStatus($stock + $expiry, $this->expiryLeadDays($listId), StockRules::today()),
		], $links);
	}

	protected function afterItemWrite(ChecklistItem $item, ?ChecklistItem $before): ChecklistItem {
		$linked = $item->getLinkedItemId() !== null && $item->getLinkedItemId() !== $before?->getLinkedItemId();
		return $this->withRestockEvents($item, $this->restockSync->afterWrite(
			$item,
			$before !== null ? RestockSyncService::snapshot($before) : null,
			$linked,
			$this->requireUid(),
		));
	}

	protected function afterListUpdated(Checklist $list, Checklist $before): void {
		if ($list->getRestockChecklistId() !== $before->getRestockChecklistId()) {
			$this->restockSync->backfill($list);
		}
	}

	/**
	 * List all inventories in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, name_asc, name_desc).
	 * @param int<1, 500> $limit Maximum number of inventories to return.
	 * @param int<0, max> $offset Number of inventories to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventory>, array{}>
	 *
	 * 200: Inventories returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexInventories(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->doIndexLists($houseId, $sortBy, $limit, $offset);
	}

	/**
	 * Create an inventory in a house
	 *
	 * @param int $houseId House id.
	 * @param string $name Inventory name.
	 * @param string|null $description Optional description.
	 * @param string|null $icon Optional icon key.
	 * @param string|null $color Optional accent color (hex, e.g. "#03a9f4"). Null clears.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories')]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function createInventory(int $houseId, string $name, ?string $description = null, ?string $icon = null, ?string $color = null): DataResponse {
		return $this->doCreateList($houseId, $name, $description, $icon, $color);
	}

	/**
	 * List soft-deleted inventories in a house (trash)
	 *
	 * Returns inventories whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of inventories to return.
	 * @param int<0, max> $offset Number of inventories to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventory>, array{}>
	 *
	 * 200: Deleted inventories returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedInventories(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedLists($houseId, $limit, $offset);
	}

	/**
	 * List archived inventories in a house
	 *
	 * Returns inventories whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of inventories to return.
	 * @param int<0, max> $offset Number of inventories to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventory>, array{}>
	 *
	 * 200: Archived inventories returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedInventories(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedLists($houseId, $limit, $offset);
	}

	/**
	 * Empty the house's inventories trash, permanently deleting every soft-deleted inventory
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function emptyInventoriesTrash(int $houseId): DataResponse {
		return $this->doEmptyListsTrash($houseId);
	}

	/**
	 * Get an inventory
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function showInventory(int $houseId, int $listId): DataResponse {
		return $this->doShowList($houseId, $listId);
	}

	/**
	 * Update an inventory
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param string|null $name New name.
	 * @param string|null $description New description.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New accent color (hex). Pass an empty string to clear.
	 * @param int|null $sortOrder New sort order.
	 * @param int<0, 365>|null $expiryLeadDays Expiry warning: how many days before its expiry date an item counts as expiring. 0 warns on the date itself.
	 * @param int|null $restockChecklistId Id of an active checklist in the same house that low stock goes to (0 clears). Setting it links the inventory's unlinked items to same-name items there.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/inventories/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateInventory(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?string $icon = null, ?string $color = null, ?int $sortOrder = null, ?int $expiryLeadDays = null, ?int $restockChecklistId = null): DataResponse {
		$patch = array_filter([
			'name' => $name,
			'description' => $description,
			'icon' => $icon,
			'color' => $color,
			'sortOrder' => $sortOrder,
			'expiryLeadDays' => $expiryLeadDays,
			'restockChecklistId' => $restockChecklistId,
		], static fn ($v) => $v !== null);
		return $this->doUpdateList($houseId, $listId, $patch);
	}

	/**
	 * Update the values new items on an inventory start with
	 *
	 * Each key is merged on its own; omitted keys are left as they are, and a
	 * mode of "none" clears a key. Custom fields merge by field id, where "none"
	 * returns the field to its own default. Without edit rights on the inventory,
	 * only keys already in "remember" mode can be updated, and only their value.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $stores Stores new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: int|null}|null $category Category new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $labels Labels new items start with.
	 * @param array{mode: 'none'|'fixed', value?: string|null}|null $quantity Quantity new items start with.
	 * @param list<array{fieldId: int, mode: 'none'|'fixed'|'remember', value?: array{valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null}|null}>|null $fields Custom-field values new items start with.
	 * @param bool|null $fillFromDefaults Whether a template item drawn here fills the fields it leaves empty from these defaults. Needs edit rights.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: 'count'|'level'|null}|null $stockMode Stock mode new items start in. Items arriving from a checklist or template start in it too.
	 * @param array{mode: 'none'|'fixed', value?: string|null}|null $target Target new count items start with.
	 * @param array{mode: 'none'|'fixed', value?: string|null}|null $reorderPoint Reorder point new count items start with.
	 * @param array{mode: 'none'|'fixed', value?: int|null}|null $shelfLifeDays Shelf life new items start with; they expire that many days after they are added.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Item defaults updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/inventories/{listId}/item-defaults', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function updateInventoryItemDefaults(int $houseId, int $listId, ?array $stores = null, ?array $category = null, ?array $labels = null, ?array $quantity = null, ?array $fields = null, ?bool $fillFromDefaults = null, ?array $stockMode = null, ?array $target = null, ?array $reorderPoint = null, ?array $shelfLifeDays = null): DataResponse {
		$patch = array_filter([
			'stores' => $stores,
			'category' => $category,
			'labels' => $labels,
			'quantity' => $quantity,
			'stockMode' => $stockMode,
			'target' => $target,
			'reorderPoint' => $reorderPoint,
			'shelfLifeDays' => $shelfLifeDays,
			'fields' => $fields,
		], static fn (?array $entry): bool => $entry !== null);
		return $this->doUpdateItemDefaults($houseId, $listId, $patch, $fillFromDefaults);
	}

	/**
	 * Delete an inventory
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Inventory deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function deleteInventory(int $houseId, int $listId): DataResponse {
		return $this->doDeleteList($houseId, $listId);
	}

	/**
	 * Restore a soft-deleted inventory back into the active index
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/restore', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function restoreInventory(int $houseId, int $listId): DataResponse {
		return $this->doRestoreList($houseId, $listId);
	}

	/**
	 * Archive an inventory, moving it out of the active index into the archive view
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/archive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveInventory(int $houseId, int $listId): DataResponse {
		return $this->doArchiveList($houseId, $listId);
	}

	/**
	 * Unarchive an inventory back into the active index
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/unarchive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveInventory(int $houseId, int $listId): DataResponse {
		return $this->doUnarchiveList($houseId, $listId);
	}

	/**
	 * Duplicate an inventory, copying its items into a new inventory
	 *
	 * Items in the inventory's trash or archive are not copied. Each copied
	 * item's image file is duplicated so the two inventories never share a file.
	 * Categories, labels and custom fields scoped to the source inventory are
	 * duplicated too, and the copied items point at the new ones.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the inventory to duplicate.
	 * @param string $name Name for the new inventory.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory duplicated
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/duplicate', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateInventory(int $houseId, int $listId, string $name): DataResponse {
		return $this->doDuplicateList($houseId, $listId, $name, true);
	}

	/**
	 * Duplicate a list of any kind as a new inventory
	 *
	 * Copies what inventory items have and drops the rest: done state,
	 * recurrence and delete-on-done, along with the source's recurrence item
	 * default. Otherwise copies like duplicating an inventory.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the list to duplicate, of any kind.
	 * @param string $name Name for the new inventory.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventory, array{}>
	 *
	 * 200: Inventory created from the list
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/from/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateAsInventory(int $houseId, int $listId, string $name): DataResponse {
		return $this->doDuplicateFrom($houseId, $listId, $name, true);
	}

	/**
	 * Permanently delete an inventory, bypassing the trash
	 *
	 * Works on both live inventories and inventories already in trash. Also wipes
	 * every item that belongs to the inventory, including its own item-trash.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Inventory permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/permanent', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function permanentlyDeleteInventory(int $houseId, int $listId): DataResponse {
		return $this->doPermanentlyDeleteList($houseId, $listId);
	}

	/**
	 * List items in an inventory
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, name_asc, name_desc, category).
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventoryItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexInventoryItems(int $houseId, int $listId, string $sortBy = 'custom', int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexItems($houseId, $listId, $sortBy, $limit, $offset);
	}

	/**
	 * List soft-deleted items in an inventory (trash)
	 *
	 * Returns items whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventoryItem>, array{}>
	 *
	 * 200: Deleted items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedInventoryItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * List archived items in an inventory
	 *
	 * Returns items whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryInventoryItem>, array{}>
	 *
	 * 200: Archived items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/items/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedInventoryItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * Suggestion candidates for an inventory's add box
	 *
	 * The inventory's archived items, when asked for, and the items of the
	 * templates attached to it — explicitly or as every-list templates — that
	 * the caller can access. Archived and trashed templates are left out.
	 * Template items with the same name (ignoring case and spacing) collapse
	 * into one row naming every template it is in, taken from the most
	 * recently edited copy. Matching what was typed is up to the client.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param bool $archived Whether to include the inventory's archived items.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventorySuggestions, array{}>
	 *
	 * 200: Suggestions returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/suggestions', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function inventorySuggestions(int $houseId, int $listId, bool $archived = false): DataResponse {
		return $this->doSuggestions($houseId, $listId, $archived);
	}

	/**
	 * Draw a template item onto an inventory
	 *
	 * Copies the template item and leaves the template unchanged. The copy
	 * carries every field an inventory item has, and fills what the template
	 * left empty from the inventory's item defaults. Categories, labels and
	 * custom fields scoped to the template are matched by name on the
	 * inventory, or copied onto it. When the inventory already has an item of
	 * that name (ignoring case and spacing), that item is only unarchived,
	 * keeping its own values, and `reused` is true.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $templateItemId Id of an item on a template in the same house.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryDrawResult, array{}>
	 *
	 * 200: Item drawn or reused
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/draw', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function drawInventoryItem(int $houseId, int $listId, int $templateItemId): DataResponse {
		return $this->doDrawItem($houseId, $listId, $templateItemId);
	}

	/**
	 * Templates attached to an inventory
	 *
	 * `templateIds` are the templates attached to this inventory;
	 * `everyListTemplateIds` are the templates attached to every list, this one
	 * included. Only active templates are named.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListTemplates, array{}>
	 *
	 * 200: Attached templates returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/templates', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function inventoryTemplates(int $houseId, int $listId): DataResponse {
		return $this->doListTemplates($houseId, $listId);
	}

	/**
	 * Set the templates attached to an inventory
	 *
	 * Replaces the inventory's own attachments. Every-list templates stay
	 * attached regardless, and attachments to templates the caller cannot
	 * access, or that are archived or trashed, are kept.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param list<int> $templateIds Ids of templates in the same house.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryListTemplates, array{}>
	 *
	 * 200: Attached templates updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/inventories/{listId}/templates', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function setInventoryTemplates(int $houseId, int $listId, array $templateIds = []): DataResponse {
		return $this->doSetListTemplates($houseId, $listId, $templateIds);
	}

	/**
	 * Add an item to an inventory
	 *
	 * A count item added without an on-hand amount starts full: its quantity is
	 * filled from the target.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param string $name Item name.
	 * @param string|null $description Optional description.
	 * @param int|null $categoryId Optional category id (must belong to the same house).
	 * @param string|null $quantity On hand for a count item, the restock amount for a level item.
	 * @param int|null $sortOrder Optional sort order.
	 * @param list<int> $storeIds Optional store ids to attach (must belong to the same house).
	 * @param string|null $barcode Optional barcode (EAN/UPC) the item was created from.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}> $prices Optional prices. A null store id is the store-less (default) price; at most one per store and one store-less.
	 * @param list<int> $labelIds Optional label ids to attach (must belong to the same house).
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}> $customFields Optional custom-field values. Each field must belong to the house and apply to this inventory; at most one value per field.
	 * @param 'count'|'level'|null $stockMode How stock is tracked; count when omitted.
	 * @param 'full'|'low'|'out'|null $stockLevel A level item's stock level; full when omitted.
	 * @param string|null $target A count item's target, the amount to buy back up to.
	 * @param string|null $reorderPoint A count item's reorder point: low at or below it, instead of below the target.
	 * @param int|null $expiresAt Expiry date (midnight timestamp). A date after today also sets the shelf life to match.
	 * @param int<1, 36500>|null $shelfLifeDays Shelf life in days. Without a date alongside, the item expires today plus the shelf life.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item added
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function addInventoryItem(
		int $houseId,
		int $listId,
		string $name,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?int $sortOrder = null,
		array $storeIds = [],
		?string $barcode = null,
		array $prices = [],
		array $labelIds = [],
		array $customFields = [],
		?string $stockMode = null,
		?string $stockLevel = null,
		?string $target = null,
		?string $reorderPoint = null,
		?int $expiresAt = null,
		?int $shelfLifeDays = null,
	): DataResponse {
		return $this->doAddItem($houseId, $listId, [
			'name' => $name,
			'description' => $description,
			'categoryId' => $categoryId,
			'quantity' => $quantity,
			'rrule' => null,
			'repeatFromCompletion' => false,
			'deleteOnDone' => false,
			'sortOrder' => $sortOrder,
			'storeIds' => $storeIds,
			'barcode' => $barcode,
			'prices' => $prices,
			'labelIds' => $labelIds,
			'customFields' => $customFields,
			'stockMode' => $stockMode,
			'stockLevel' => $stockLevel,
			'target' => $target,
			'reorderPoint' => $reorderPoint,
			'expiresAt' => $expiresAt,
			'shelfLifeDays' => $shelfLifeDays,
		]);
	}

	/**
	 * Update an inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 * @param string|null $name New name.
	 * @param string|null $description New description (empty string clears).
	 * @param int|null $categoryId New category id (0 or negative clears).
	 * @param string|null $quantity New on hand for a count item, or restock amount for a level item (empty string clears).
	 * @param int|null $imageFileId File id of attached image (0 or negative clears).
	 * @param int|null $sortOrder New sort order.
	 * @param int|null $targetListId Move item to a different inventory (must belong to the same house).
	 * @param list<int>|null $storeIds New set of store ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param string|null $barcode New barcode (EAN/UPC); empty string clears.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}>|null $prices New full set of prices (empty array clears all; null leaves them unchanged). A null store id is the store-less price; at most one per store and one store-less.
	 * @param list<int>|null $labelIds New set of label ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}>|null $customFields New full set of custom-field values (empty array clears all; null leaves them unchanged). Each field must belong to the house and apply to the item's inventory.
	 * @param 'count'|'level'|null $stockMode New stock mode. Switching converts the item's values: count to level takes the level from the stock status and restocks the shortfall when low, else the target; level to count makes the restock amount the target and fills on hand to match the level. Values sent alongside win over the conversion.
	 * @param 'full'|'low'|'out'|null $stockLevel New stock level of a level item.
	 * @param string|null $target New target of a count item (empty string clears).
	 * @param string|null $reorderPoint New reorder point of a count item (empty string clears).
	 * @param int|null $expiresAt New expiry date (midnight timestamp; 0 clears). Without a shelf life alongside, a date after today also sets the shelf life to match.
	 * @param int<0, 36500>|null $shelfLifeDays New shelf life in days (0 clears). Without a date alongside, a shelf life also sets the date to today plus the shelf life.
	 * @param bool|null $autoLink Whether an unlinked item is linked to the same-name item on the restock checklist. Turning it on links it when exactly one matches.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateInventoryItem(
		int $houseId,
		int $listId,
		int $itemId,
		?string $name = null,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?int $imageFileId = null,
		?int $sortOrder = null,
		?int $targetListId = null,
		?array $storeIds = null,
		?string $barcode = null,
		?array $prices = null,
		?array $labelIds = null,
		?array $customFields = null,
		?string $stockMode = null,
		?string $stockLevel = null,
		?string $target = null,
		?string $reorderPoint = null,
		?int $expiresAt = null,
		?int $shelfLifeDays = null,
		?bool $autoLink = null,
	): DataResponse {
		$stock = array_filter([
			'stockMode' => $stockMode,
			'stockLevel' => $stockLevel,
			'target' => $target,
			'reorderPoint' => $reorderPoint,
			'expiresAt' => $expiresAt,
			'shelfLifeDays' => $shelfLifeDays,
			'autoLink' => $autoLink,
		], static fn (string|int|bool|null $v): bool => $v !== null);
		return $this->doUpdateItem($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, null, null, null, $imageFileId, $sortOrder, $targetListId, $storeIds, $barcode, $prices, $labelIds, $customFields, $stock);
	}

	/**
	 * Link an inventory item to the checklist item that restocks it
	 *
	 * Replaces any link the item has. The checklist item can be on any active
	 * checklist in the house the caller can edit, but not one another item on
	 * this inventory is linked to.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Inventory item id.
	 * @param int $targetItemId Id of the checklist item to link to.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item linked
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/link', requirements: ['listId' => '\d+', 'itemId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function linkInventoryItem(int $houseId, int $listId, int $itemId, int $targetItemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId, $targetItemId): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			[$item] = $this->loadItem($houseId, $listId, $itemId);
			$row = $this->lists->getItem($targetItemId);
			$checklist = $this->lists->getList($row->getListId(), kind: Checklist::KIND_CHECKLIST);
			$this->assertListInHouse($checklist->getHouseId(), $houseId);
			if ($checklist->getArchivedAt() !== null) {
				throw new \InvalidArgumentException('Cannot link to an item on an archived checklist');
			}
			if (!$this->editCheck($houseId, $uid)($checklist)) {
				throw new ForbiddenException('Linking needs edit rights on the checklist');
			}
			$before = clone $item;
			$linked = $this->stockLinks->link($item, $row);
			return new DataResponse($this->serializeItem($this->afterItemWrite($linked, $before)));
		});
	}

	/**
	 * Unlink an inventory item from its checklist item
	 *
	 * Also turns auto-link off, so the item is not linked by name again.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Inventory item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item unlinked
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/link', requirements: ['listId' => '\d+', 'itemId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unlinkInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $itemId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			[$item] = $this->loadItem($houseId, $listId, $itemId);
			return new DataResponse($this->serializeItem($this->stockLinks->unlink($item)));
		});
	}

	/**
	 * Checklist items an inventory's items can be linked to
	 *
	 * Live and archived items on the house's active checklists the caller can
	 * edit, leaving out those already linked from this inventory. Sorted by
	 * name, then checklist.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param string $search Only items whose name contains this, ignoring case and spacing.
	 * @param int<1, 200> $limit Maximum number of items to return.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryStockLinkRow>, array{}>
	 *
	 * 200: Link targets returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/inventories/{listId}/link-targets', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function inventoryLinkTargets(int $houseId, int $listId, string $search = '', int $limit = 50): DataResponse {
		return $this->runAction(function () use ($houseId, $listId, $search, $limit): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$inventory = $this->loadList($houseId, $listId, includeDeleted: true);
			return new DataResponse($this->stockLinks->linkTargets($inventory, $this->editCheck($houseId, $uid), $search, min(200, max(1, $limit))));
		});
	}

	/**
	 * Copy an inventory item to another inventory
	 *
	 * If the source has an image, the underlying file is duplicated so deleting
	 * either side's image will not affect the other.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id the source item lives on.
	 * @param int $itemId Source item id.
	 * @param int $targetListId Destination inventory id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item copied
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/copy')]
	#[NoAdminRequired]
	#[Permission(['canCopyItems'])]
	public function copyInventoryItem(int $houseId, int $listId, int $itemId, int $targetListId): DataResponse {
		return $this->doCopyItem($houseId, $listId, $itemId, $targetListId);
	}

	/**
	 * Empty an inventory's trash, permanently deleting every soft-deleted item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function emptyInventoryItemsTrash(int $houseId, int $listId): DataResponse {
		return $this->doEmptyTrash($houseId, $listId);
	}

	/**
	 * Delete an inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}', requirements: ['itemId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function deleteInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Restore a soft-deleted inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/restore')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function restoreInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doRestoreItem($houseId, $listId, $itemId);
	}

	/**
	 * Permanently delete an inventory item, bypassing the trash
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/permanent')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function permanentlyDeleteInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doPermanentlyDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Archive an inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/archive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doArchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Unarchive an inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Item unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/unarchive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveInventoryItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUnarchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Batch reorder items in an inventory
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Items reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorderInventoryItems(int $houseId, int $listId, array $items = []): DataResponse {
		return $this->doReorderItems($houseId, $listId, $items);
	}

	/**
	 * Upload an image for an inventory item
	 *
	 * Expects a multipart/form-data request with the image file in a field
	 * named **image**.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Image uploaded and attached
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function uploadInventoryItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUploadItemImage($houseId, $listId, $itemId);
	}

	/**
	 * Clear the image attached to an inventory item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Inventory id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryInventoryItem, array{}>
	 *
	 * 200: Image cleared
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/inventories/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function clearInventoryItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doClearItemImage($houseId, $listId, $itemId);
	}
}
