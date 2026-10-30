<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;

/**
 * The template resource: lists of reusable items that are never checked off.
 * Template items keep a recurrence and delete-on-done, but neither ever runs.
 *
 * @psalm-import-type PantryTemplate from ResponseDefinitions
 * @psalm-import-type PantryTemplateItem from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 * @psalm-import-type PantryTemplateLists from ResponseDefinitions
 */
final class TemplateController extends ListKindOCSController {
	/** Checklist-only list fields: completion and the per-column recurrence default. */
	private const CHECKLIST_LIST_FIELDS = [
		'deleteOnDoneDefault',
		'defaultRecurrenceMode',
		'defaultRecurrenceKind',
		'defaultRrule',
		'defaultRepeatFromCompletion',
		'lastCompletedAt',
		// Templates are drawn from, never into.
		'fillFromDefaults',
	];

	protected function kind(): string {
		return Checklist::KIND_TEMPLATE;
	}

	protected function shapeList(array $data): array {
		return $this->withoutStockDefaults(array_diff_key($data, array_flip(self::CHECKLIST_LIST_FIELDS)));
	}

	protected function shapeItem(array $data): array {
		return array_diff_key($data, array_flip(self::TEMPLATE_DROPPED_ITEM_FIELDS));
	}

	/**
	 * List all templates in a house
	 *
	 * @param int $houseId House id.
	 * @param string $sortBy Sort mode (custom, name_asc, name_desc).
	 * @param int<1, 500> $limit Maximum number of templates to return.
	 * @param int<0, max> $offset Number of templates to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplate>, array{}>
	 *
	 * 200: Templates returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexTemplates(int $houseId, string $sortBy = 'custom', int $limit = 100, int $offset = 0): DataResponse {
		return $this->doIndexLists($houseId, $sortBy, $limit, $offset);
	}

	/**
	 * Create a template in a house
	 *
	 * @param int $houseId House id.
	 * @param string $name Template name.
	 * @param string|null $description Optional description.
	 * @param string|null $icon Optional icon key.
	 * @param string|null $color Optional accent color (hex, e.g. "#03a9f4"). Null clears.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates')]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function createTemplate(int $houseId, string $name, ?string $description = null, ?string $icon = null, ?string $color = null): DataResponse {
		return $this->doCreateList($houseId, $name, $description, $icon, $color);
	}

	/**
	 * List soft-deleted templates in a house (trash)
	 *
	 * Returns templates whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of templates to return.
	 * @param int<0, max> $offset Number of templates to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplate>, array{}>
	 *
	 * 200: Deleted templates returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedTemplates(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedLists($houseId, $limit, $offset);
	}

	/**
	 * List archived templates in a house
	 *
	 * Returns templates whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int<1, 500> $limit Maximum number of templates to return.
	 * @param int<0, max> $offset Number of templates to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplate>, array{}>
	 *
	 * 200: Archived templates returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedTemplates(int $houseId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedLists($houseId, $limit, $offset);
	}

	/**
	 * Empty the house's templates trash, permanently deleting every soft-deleted template
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function emptyTemplatesTrash(int $houseId): DataResponse {
		return $this->doEmptyListsTrash($houseId);
	}

	/**
	 * Get a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function showTemplate(int $houseId, int $listId): DataResponse {
		return $this->doShowList($houseId, $listId);
	}

	/**
	 * Update a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param string|null $name New name.
	 * @param string|null $description New description.
	 * @param string|null $icon New icon key.
	 * @param string|null $color New accent color (hex). Pass an empty string to clear.
	 * @param int|null $sortOrder New sort order.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/templates/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateTemplate(int $houseId, int $listId, ?string $name = null, ?string $description = null, ?string $icon = null, ?string $color = null, ?int $sortOrder = null): DataResponse {
		$patch = array_filter([
			'name' => $name,
			'description' => $description,
			'icon' => $icon,
			'color' => $color,
			'sortOrder' => $sortOrder,
		], static fn ($v) => $v !== null);
		return $this->doUpdateList($houseId, $listId, $patch);
	}

	/**
	 * Update the values new items on a template start with
	 *
	 * Each key is merged on its own; omitted keys are left as they are, and a
	 * mode of "none" clears a key. Custom fields merge by field id, where "none"
	 * returns the field to its own default. Without edit rights on the template,
	 * only keys already in "remember" mode can be updated, and only their value.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: array{kind: 'none'|'once'|'recurring', rrule?: string|null, repeatFromCompletion?: bool}|null}|null $recurrence Recurrence new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $stores Stores new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: int|null}|null $category Category new items start with.
	 * @param array{mode: 'none'|'fixed'|'remember', value?: list<int>|null}|null $labels Labels new items start with.
	 * @param array{mode: 'none'|'fixed', value?: string|null}|null $quantity Quantity new items start with.
	 * @param list<array{fieldId: int, mode: 'none'|'fixed'|'remember', value?: array{valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null}|null}>|null $fields Custom-field values new items start with.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Item defaults updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/templates/{listId}/item-defaults', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function updateTemplateItemDefaults(int $houseId, int $listId, ?array $recurrence = null, ?array $stores = null, ?array $category = null, ?array $labels = null, ?array $quantity = null, ?array $fields = null): DataResponse {
		$patch = array_filter([
			'recurrence' => $recurrence,
			'stores' => $stores,
			'category' => $category,
			'labels' => $labels,
			'quantity' => $quantity,
			'fields' => $fields,
		], static fn (?array $entry): bool => $entry !== null);
		return $this->doUpdateItemDefaults($houseId, $listId, $patch);
	}

	/**
	 * Delete a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Template deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function deleteTemplate(int $houseId, int $listId): DataResponse {
		return $this->doDeleteList($houseId, $listId);
	}

	/**
	 * Restore a soft-deleted template back into the active index
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/restore', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function restoreTemplate(int $houseId, int $listId): DataResponse {
		return $this->doRestoreList($houseId, $listId);
	}

	/**
	 * Archive a template, moving it out of the active index into the archive view
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/archive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveTemplate(int $houseId, int $listId): DataResponse {
		return $this->doArchiveList($houseId, $listId);
	}

	/**
	 * Unarchive a template back into the active index
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/unarchive', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveTemplate(int $houseId, int $listId): DataResponse {
		return $this->doUnarchiveList($houseId, $listId);
	}

	/**
	 * Duplicate a template, copying its items into a new template
	 *
	 * Items in the template's trash or archive are not copied. Each copied
	 * item's image file is duplicated so the two templates never share a file.
	 * Categories, labels and custom fields scoped to the source template are
	 * duplicated too, and the copied items point at the new ones.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the template to duplicate.
	 * @param string $name Name for the new template.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template duplicated
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/duplicate', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateTemplate(int $houseId, int $listId, string $name): DataResponse {
		return $this->doDuplicateList($houseId, $listId, $name, true);
	}

	/**
	 * Duplicate a list of any kind as a new template
	 *
	 * Copies what template items have and drops the rest: items start
	 * unchecked, and keep their recurrence and delete-on-done, which a template
	 * stores but never runs. Otherwise copies like duplicating a template.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Id of the list to duplicate, of any kind.
	 * @param string $name Name for the new template.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplate, array{}>
	 *
	 * 200: Template created from the list
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/from/{listId}', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canCreateLists'])]
	public function duplicateAsTemplate(int $houseId, int $listId, string $name): DataResponse {
		return $this->doDuplicateFrom($houseId, $listId, $name, true);
	}

	/**
	 * Permanently delete a template, bypassing the trash
	 *
	 * Works on both live templates and templates already in trash. Also wipes
	 * every item that belongs to the template, including its own item-trash.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Template permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}/permanent', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteLists'])]
	public function permanentlyDeleteTemplate(int $houseId, int $listId): DataResponse {
		return $this->doPermanentlyDeleteList($houseId, $listId);
	}

	/**
	 * List items in a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param string $sortBy Sort mode (custom, newest, oldest, name_asc, name_desc, category).
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplateItem>, array{}>
	 *
	 * 200: Items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexTemplateItems(int $houseId, int $listId, string $sortBy = 'custom', int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexItems($houseId, $listId, $sortBy, $limit, $offset);
	}

	/**
	 * List soft-deleted items in a template (trash)
	 *
	 * Returns items whose deleted_at is set, most recently deleted first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplateItem>, array{}>
	 *
	 * 200: Deleted items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexDeletedTemplateItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexDeletedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * List archived items in a template
	 *
	 * Returns items whose archived_at is set, most recently archived first.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int<1, 1000> $limit Maximum number of items to return.
	 * @param int<0, max> $offset Number of items to skip.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryTemplateItem>, array{}>
	 *
	 * 200: Archived items returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/{listId}/items/archive')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function indexArchivedTemplateItems(int $houseId, int $listId, int $limit = 200, int $offset = 0): DataResponse {
		return $this->doIndexArchivedItems($houseId, $listId, $limit, $offset);
	}

	/**
	 * Lists a template is attached to
	 *
	 * `everyList` attaches the template to every checklist and inventory in the
	 * house, including ones made later; `listIds` are the lists it is attached
	 * to by name. Archived and trashed lists are left out.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateLists, array{}>
	 *
	 * 200: Attached lists returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/templates/{listId}/lists', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function templateLists(int $houseId, int $listId): DataResponse {
		return $this->doTemplateLists($houseId, $listId);
	}

	/**
	 * Set the lists a template is attached to
	 *
	 * Replaces the template's attachments and its every-list switch.
	 * Attachments to lists the caller cannot access, or that are archived or
	 * trashed, are kept. Templates never attach to templates.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param bool $everyList Whether the template is attached to every checklist and inventory in the house.
	 * @param list<int> $listIds Ids of checklists and inventories in the same house.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateLists, array{}>
	 *
	 * 200: Attached lists updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/templates/{listId}/lists', requirements: ['listId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function setTemplateLists(int $houseId, int $listId, bool $everyList = false, array $listIds = []): DataResponse {
		return $this->doSetTemplateLists($houseId, $listId, $everyList, $listIds);
	}

	/**
	 * Add an item to a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param string $name Item name.
	 * @param string|null $description Optional description.
	 * @param int|null $categoryId Optional category id (must belong to the same house).
	 * @param string|null $quantity Optional quantity string.
	 * @param string|null $rrule Optional RFC 5545 RRULE, kept for items drawn from the template.
	 * @param bool $repeatFromCompletion Recurrence anchor mode, kept with the rule.
	 * @param bool $deleteOnDone Delete-on-done flag, kept for items drawn from the template.
	 * @param int|null $sortOrder Optional sort order.
	 * @param list<int> $storeIds Optional store ids to attach (must belong to the same house).
	 * @param string|null $barcode Optional barcode (EAN/UPC) the item was created from.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}> $prices Optional prices. A null store id is the store-less (default) price; at most one per store and one store-less.
	 * @param list<int> $labelIds Optional label ids to attach (must belong to the same house).
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}> $customFields Optional custom-field values. Each field must belong to the house and apply to this template; at most one value per field.
	 * @param 'count'|'level'|null $stockMode How the item is stocked when drawn into an inventory; the inventory's default when omitted.
	 * @param string|null $target The target an inventory count item starts with.
	 * @param string|null $reorderPoint The reorder point an inventory count item starts with.
	 * @param int<1, 36500>|null $shelfLifeDays Shelf life in days; drawn into an inventory, the item expires that many days later.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item added
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items')]
	#[NoAdminRequired]
	#[Permission(['canAddItems'])]
	public function addTemplateItem(
		int $houseId,
		int $listId,
		string $name,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?string $rrule = null,
		bool $repeatFromCompletion = false,
		bool $deleteOnDone = false,
		?int $sortOrder = null,
		array $storeIds = [],
		?string $barcode = null,
		array $prices = [],
		array $labelIds = [],
		array $customFields = [],
		?string $stockMode = null,
		?string $target = null,
		?string $reorderPoint = null,
		?int $shelfLifeDays = null,
	): DataResponse {
		return $this->doAddItem($houseId, $listId, [
			'name' => $name,
			'description' => $description,
			'categoryId' => $categoryId,
			'quantity' => $quantity,
			'rrule' => $rrule,
			'repeatFromCompletion' => $repeatFromCompletion,
			'deleteOnDone' => $deleteOnDone,
			'sortOrder' => $sortOrder,
			'storeIds' => $storeIds,
			'barcode' => $barcode,
			'prices' => $prices,
			'labelIds' => $labelIds,
			'customFields' => $customFields,
			'stockMode' => $stockMode,
			'target' => $target,
			'reorderPoint' => $reorderPoint,
			'shelfLifeDays' => $shelfLifeDays,
		]);
	}

	/**
	 * Update a template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 * @param string|null $name New name.
	 * @param string|null $description New description (empty string clears).
	 * @param int|null $categoryId New category id (0 or negative clears).
	 * @param string|null $quantity New quantity (empty string clears).
	 * @param string|null $rrule New RRULE (empty string clears).
	 * @param bool|null $repeatFromCompletion New recurrence anchor mode.
	 * @param bool|null $deleteOnDone New delete-on-done flag.
	 * @param int|null $imageFileId File id of attached image (0 or negative clears).
	 * @param int|null $sortOrder New sort order.
	 * @param int|null $targetListId Move item to a different template (must belong to the same house).
	 * @param list<int>|null $storeIds New set of store ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param string|null $barcode New barcode (EAN/UPC); empty string clears.
	 * @param list<array{storeId?: int|null, priceType?: string|null, priceMin?: float|null, priceMax?: float|null, priceCurrency?: string|null}>|null $prices New full set of prices (empty array clears all; null leaves them unchanged). A null store id is the store-less price; at most one per store and one store-less.
	 * @param list<int>|null $labelIds New set of label ids (empty array clears; null leaves unchanged). All must belong to the same house.
	 * @param list<array{fieldId: int, valueText?: string|null, valueNumber?: float|null, valueBool?: bool, valueDate?: int|null, valueOptionId?: int|null, offsetDays?: int|null, notifyOverride?: bool, notifyEnabled?: bool, notifyLeadDays?: int|null}>|null $customFields New full set of custom-field values (empty array clears all; null leaves them unchanged). Each field must belong to the house and apply to the item's template.
	 * @param 'count'|'level'|''|null $stockMode New stock mode for drawing into an inventory (empty string clears, leaving it to the inventory's default).
	 * @param string|null $target New target (empty string clears).
	 * @param string|null $reorderPoint New reorder point (empty string clears).
	 * @param int<0, 36500>|null $shelfLifeDays New shelf life in days (0 clears).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function updateTemplateItem(
		int $houseId,
		int $listId,
		int $itemId,
		?string $name = null,
		?string $description = null,
		?int $categoryId = null,
		?string $quantity = null,
		?string $rrule = null,
		?bool $repeatFromCompletion = null,
		?bool $deleteOnDone = null,
		?int $imageFileId = null,
		?int $sortOrder = null,
		?int $targetListId = null,
		?array $storeIds = null,
		?string $barcode = null,
		?array $prices = null,
		?array $labelIds = null,
		?array $customFields = null,
		?string $stockMode = null,
		?string $target = null,
		?string $reorderPoint = null,
		?int $shelfLifeDays = null,
	): DataResponse {
		$stock = array_filter([
			'stockMode' => $stockMode,
			'target' => $target,
			'reorderPoint' => $reorderPoint,
			'shelfLifeDays' => $shelfLifeDays,
		], static fn (string|int|null $v): bool => $v !== null);
		return $this->doUpdateItem($houseId, $listId, $itemId, $name, $description, $categoryId, $quantity, $rrule, $repeatFromCompletion, $deleteOnDone, $imageFileId, $sortOrder, $targetListId, $storeIds, $barcode, $prices, $labelIds, $customFields, $stock);
	}

	/**
	 * Copy a template item to another template
	 *
	 * If the source has an image, the underlying file is duplicated so deleting
	 * either side's image will not affect the other.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id the source item lives on.
	 * @param int $itemId Source item id.
	 * @param int $targetListId Destination template id (must belong to the same house).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item copied
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/copy')]
	#[NoAdminRequired]
	#[Permission(['canCopyItems'])]
	public function copyTemplateItem(int $houseId, int $listId, int $itemId, int $targetListId): DataResponse {
		return $this->doCopyItem($houseId, $listId, $itemId, $targetListId);
	}

	/**
	 * Empty a template's trash, permanently deleting every soft-deleted item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Trash emptied
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}/items/trash')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function emptyTemplateItemsTrash(int $houseId, int $listId): DataResponse {
		return $this->doEmptyTrash($houseId, $listId);
	}

	/**
	 * Delete a template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}', requirements: ['itemId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function deleteTemplateItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Restore a soft-deleted template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item restored
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/restore')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function restoreTemplateItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doRestoreItem($houseId, $listId, $itemId);
	}

	/**
	 * Permanently delete a template item, bypassing the trash
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Item permanently deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/permanent')]
	#[NoAdminRequired]
	#[Permission(['canDeleteItems'])]
	public function permanentlyDeleteTemplateItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doPermanentlyDeleteItem($houseId, $listId, $itemId);
	}

	/**
	 * Archive a template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item archived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/archive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function archiveTemplateItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doArchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Unarchive a template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Item unarchived
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/unarchive')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function unarchiveTemplateItem(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUnarchiveItem($houseId, $listId, $itemId);
	}

	/**
	 * Batch reorder items in a template
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Items reordered
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorderTemplateItems(int $houseId, int $listId, array $items = []): DataResponse {
		return $this->doReorderItems($houseId, $listId, $items);
	}

	/**
	 * Upload an image for a template item
	 *
	 * Expects a multipart/form-data request with the image file in a field
	 * named **image**.
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Image uploaded and attached
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function uploadTemplateItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doUploadItemImage($houseId, $listId, $itemId);
	}

	/**
	 * Clear the image attached to a template item
	 *
	 * @param int $houseId House id.
	 * @param int $listId Template id.
	 * @param int $itemId Item id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryTemplateItem, array{}>
	 *
	 * 200: Image cleared
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/templates/{listId}/items/{itemId}/image')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function clearTemplateItemImage(int $houseId, int $listId, int $itemId): DataResponse {
		return $this->doClearItemImage($houseId, $listId, $itemId);
	}
}
