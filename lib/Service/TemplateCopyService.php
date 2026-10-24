<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\FieldValueMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemPriceMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;

/**
 * The template-copy rule: how a template item lands on a checklist or
 * inventory, whether drawn one at a time or applied with the rest.
 *
 * - A same-name item already on the target (ignoring case and spacing) is
 *   reused instead of added: unchecked and unarchived on a checklist, only
 *   unarchived on an inventory, its own values left alone.
 * - Otherwise the copy carries every field but recurrence and delete-on-done,
 *   starts unchecked, and fills what the template left empty from the
 *   target's item defaults, as if it had been added by hand. The target list
 *   can switch that filling off, and the member's own preference wins over it.
 * - On an inventory the copy starts full, in the template item's stock mode
 *   ({@see StockRules::startFull()}); on a checklist it keeps the quantity.
 * - Categories, labels and custom fields scoped to the template are matched
 *   by name on the target, or copied onto it when it has none, so later
 *   copies match those instead of piling up.
 * - Nothing links the copy back to its template item.
 */
class TemplateCopyService {
	public function __construct(
		private ChecklistService $lists,
		private ChecklistItemMapper $itemMapper,
		private CategoryMapper $categoryMapper,
		private LabelMapper $labelMapper,
		private FieldDefinitionMapper $fieldDefMapper,
		private FieldOptionMapper $fieldOptionMapper,
		private ItemStoreMapper $itemStoreMapper,
		private ItemLabelMapper $itemLabelMapper,
		private ItemPriceMapper $itemPriceMapper,
		private FieldValueMapper $fieldValueMapper,
		private ItemDefaultsService $itemDefaults,
		private ScopedTaxonomyService $taxonomy,
		private PrefsService $prefs,
	) {
	}

	/**
	 * Copy a template item onto a checklist or inventory, or revive the
	 * same-name item already there.
	 *
	 * An item's image lives in an account's storage, so duplicating the file is
	 * the caller's business: `$duplicateImage` is handed the source file id and
	 * its owner, and returns the new file id (or null when it could not copy it).
	 *
	 * @param callable(int $fileId, string $ownerUid): ?int $duplicateImage
	 * @return array{item: ChecklistItem, reused: bool}
	 */
	public function draw(ChecklistItem $source, Checklist $target, ?string $addedBy, callable $duplicateImage): array {
		$template = $this->lists->getList($source->getListId(), includeDeleted: true);
		if ($template->getKind() !== Checklist::KIND_TEMPLATE) {
			throw new \InvalidArgumentException('Only template items can be drawn');
		}
		if ($target->getKind() === Checklist::KIND_TEMPLATE) {
			throw new \InvalidArgumentException('Template items are drawn into checklists and inventories');
		}
		if ($template->getHouseId() !== $target->getHouseId()) {
			throw new \InvalidArgumentException('The template is in another house');
		}

		$existing = $this->findSameName($target, $source->getName());
		if ($existing !== null) {
			return ['item' => $this->revive($existing, $target), 'reused' => true];
		}
		return ['item' => $this->copy($source, $template, $target, $addedBy, $duplicateImage), 'reused' => false];
	}

	private function findSameName(Checklist $target, string $name): ?ChecklistItem {
		$key = ScopedTaxonomyService::nameKey($name);
		$targetId = (int)$target->getId();
		foreach ([...$this->itemMapper->findByList($targetId), ...$this->itemMapper->findArchivedByList($targetId)] as $item) {
			if (ScopedTaxonomyService::nameKey($item->getName()) === $key) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Bring a same-name item back into play. An inventory item is only
	 * unarchived: it has nothing to uncheck, and its stock is left alone.
	 */
	private function revive(ChecklistItem $item, Checklist $target): ChecklistItem {
		$itemId = (int)$item->getId();
		if ($target->isChecklist() && $item->getDone()) {
			$this->lists->uncheckItems([$itemId]);
		}
		if ($item->getArchivedAt() !== null) {
			$this->lists->unarchiveItem($itemId);
		}
		return $this->lists->getItem($itemId);
	}

	/**
	 * @param callable(int $fileId, string $ownerUid): ?int $duplicateImage
	 */
	private function copy(ChecklistItem $source, Checklist $template, Checklist $target, ?string $addedBy, callable $duplicateImage): ChecklistItem {
		$now = time();
		$houseId = (int)$target->getHouseId();
		$templateId = (int)$template->getId();
		$targetId = (int)$target->getId();
		$sourceId = (int)$source->getId();

		$data = [
			'name' => $source->getName(),
			'description' => $source->getDescription(),
			'categoryId' => $this->targetCategory($source->getCategoryId(), $houseId, $templateId, $targetId, $now),
			'quantity' => $source->getQuantity(),
			'barcode' => $source->getBarcode(),
			'storeIds' => $this->itemStoreMapper->findStoreIdsForItem($sourceId),
			'labelIds' => $this->targetLabels($this->itemLabelMapper->findLabelIdsForItem($sourceId), $houseId, $templateId, $targetId, $now),
			'prices' => $this->itemPriceMapper->findForItem($sourceId),
			'customFields' => $this->targetFieldValues($this->fieldValueMapper->findForItem($sourceId), $houseId, $templateId, $targetId, $now),
			'stockMode' => $source->getStockMode(),
			'target' => $source->getTarget(),
			'reorderPoint' => $source->getReorderPoint(),
		];
		if ($this->fills($target, $addedBy)) {
			$data = $this->fillFromDefaults($data, $target, $houseId);
		}
		if ($target->getKind() === Checklist::KIND_INVENTORY) {
			$text = static function (string $key) use ($data): ?string {
				$value = $data[$key] ?? null;
				return is_string($value) ? $value : null;
			};
			$data = array_merge($data, StockRules::startFull(
				$text('stockMode') ?? $this->lists->defaultStockMode($target),
				$text('quantity'),
				$text('target'),
				$text('reorderPoint'),
			));
		}

		$imageFileId = $source->getImageFileId();
		$imageOwner = $source->getImageUploadedBy();
		if ($imageFileId !== null && $imageOwner !== null) {
			$newFileId = $duplicateImage($imageFileId, $imageOwner);
			if ($newFileId !== null) {
				$data['imageFileId'] = $newFileId;
				$data['imageUploadedBy'] = $addedBy;
			}
		}

		return $this->lists->addItem($targetId, $data, $addedBy);
	}

	// ----- Scoped taxonomy -----

	private function targetCategory(?int $categoryId, int $houseId, int $templateId, int $targetId, int $now): ?int {
		if ($categoryId === null) {
			return null;
		}
		$all = array_values($this->categoryMapper->findByHouse($houseId));
		$category = $this->byId($all, $categoryId);
		if (!$category instanceof Category) {
			return null;
		}
		if ($category->getListId() !== $templateId) {
			return $categoryId;
		}
		$match = $this->matchByName($all, $category->getName(), $targetId);
		return (int)($match ?? $this->taxonomy->copyCategory($category, $targetId, $now))->getId();
	}

	/**
	 * @param int[] $labelIds
	 * @return list<int>
	 */
	private function targetLabels(array $labelIds, int $houseId, int $templateId, int $targetId, int $now): array {
		if ($labelIds === []) {
			return [];
		}
		$all = array_values($this->labelMapper->findByHouse($houseId));
		$out = [];
		foreach ($labelIds as $labelId) {
			$label = $this->byId($all, (int)$labelId);
			if (!$label instanceof Label) {
				continue;
			}
			if ($label->getListId() !== $templateId) {
				$out[] = (int)$labelId;
				continue;
			}
			$match = $this->matchByName($all, $label->getName(), $targetId);
			if ($match === null) {
				$match = $this->taxonomy->copyLabel($label, $targetId, $now);
				$all[] = $match;
			}
			$out[] = (int)$match->getId();
		}
		return array_values(array_unique($out));
	}

	/**
	 * Point the template item's custom-field values at fields the target can
	 * use. A template-scoped field matches a target field of the same name and
	 * type, its select option matched by label; without a match the field is
	 * copied onto the target with its options.
	 *
	 * @param list<array<string, mixed>> $values
	 * @return list<array<string, mixed>>
	 */
	private function targetFieldValues(array $values, int $houseId, int $templateId, int $targetId, int $now): array {
		if ($values === []) {
			return [];
		}
		/** @var array<int, FieldDefinition> $defs */
		$defs = [];
		foreach ($this->fieldDefMapper->findByHouse($houseId) as $def) {
			if ($def->getDeletedAt() === null) {
				$defs[(int)$def->getId()] = $def;
			}
		}

		$out = [];
		foreach ($values as $value) {
			$def = $defs[(int)$value['fieldId']] ?? null;
			if ($def === null) {
				continue;
			}
			if ($def->getListId() !== $templateId) {
				$out[] = $value;
				continue;
			}
			$match = $this->matchField($defs, $def, $targetId);
			if ($match === null) {
				[$copy, $optionMap] = $this->taxonomy->copyField($def, $targetId, $now);
				$defs[(int)$copy->getId()] = $copy;
				$value['fieldId'] = (int)$copy->getId();
				$optionId = $value['valueOptionId'] ?? null;
				if ($optionId !== null) {
					$value['valueOptionId'] = $optionMap[(int)$optionId] ?? null;
				}
				$out[] = $value;
				continue;
			}
			$value['fieldId'] = (int)$match->getId();
			$optionId = $value['valueOptionId'] ?? null;
			if ($optionId !== null) {
				$value['valueOptionId'] = $this->matchOption((int)$optionId, (int)$def->getId(), (int)$match->getId());
			}
			$out[] = $value;
		}
		return $out;
	}

	/**
	 * @param array<int, FieldDefinition> $defs
	 */
	private function matchField(array $defs, FieldDefinition $source, int $targetId): ?FieldDefinition {
		$key = ScopedTaxonomyService::nameKey($source->getName());
		$found = null;
		foreach ($defs as $def) {
			$scope = $def->getListId();
			if (($scope !== null && $scope !== $targetId)
				|| $def->getType() !== $source->getType()
				|| ScopedTaxonomyService::nameKey($def->getName()) !== $key) {
				continue;
			}
			// The target's own field wins over a house-wide one of the same name.
			if ($scope === $targetId) {
				return $def;
			}
			$found ??= $def;
		}
		return $found;
	}

	private function matchOption(int $optionId, int $sourceFieldId, int $targetFieldId): ?int {
		$label = null;
		foreach ($this->fieldOptionMapper->findByField($sourceFieldId) as $option) {
			if ((int)$option->getId() === $optionId) {
				$label = ScopedTaxonomyService::nameKey($option->getLabel());
				break;
			}
		}
		if ($label === null) {
			return null;
		}
		foreach ($this->fieldOptionMapper->findByField($targetFieldId) as $option) {
			if (ScopedTaxonomyService::nameKey($option->getLabel()) === $label) {
				return (int)$option->getId();
			}
		}
		return null;
	}

	/**
	 * @param list<Category>|list<Label> $all
	 */
	private function byId(array $all, int $id): Category|Label|null {
		foreach ($all as $entity) {
			if ((int)$entity->getId() === $id) {
				return $entity;
			}
		}
		return null;
	}

	/**
	 * A category or label the target can use with this name, preferring the
	 * target's own over a house-wide one.
	 *
	 * @param list<Category>|list<Label> $all
	 */
	private function matchByName(array $all, string $name, int $targetId): Category|Label|null {
		$key = ScopedTaxonomyService::nameKey($name);
		$found = null;
		foreach ($all as $entity) {
			$scope = $entity->getListId();
			if (($scope !== null && $scope !== $targetId) || ScopedTaxonomyService::nameKey($entity->getName()) !== $key) {
				continue;
			}
			if ($scope === $targetId) {
				return $entity;
			}
			$found ??= $entity;
		}
		return $found;
	}

	// ----- Item defaults -----

	private function fills(Checklist $target, ?string $uid): bool {
		$listFills = $target->fillsFromDefaults();
		return $uid === null ? $listFills : $this->prefs->fillsFromItemDefaults($uid, (int)$target->getHouseId(), $listFills);
	}

	/**
	 * Fill what the template item left empty from the target's item defaults
	 * and its custom fields' own defaults, the way a new item starts in the add
	 * box.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function fillFromDefaults(array $data, Checklist $target, int $houseId): array {
		$defaults = $this->itemDefaults->forList($target);
		$prefill = static fn (array $entry): mixed => ($entry['mode'] ?? ItemDefaults::MODE_NONE) === ItemDefaults::MODE_NONE
			? null
			: ($entry['value'] ?? null);

		if ($data['categoryId'] === null) {
			$category = $prefill($defaults[ItemDefaults::KEY_CATEGORY]);
			$data['categoryId'] = is_int($category) ? $category : null;
		}
		if ($data['storeIds'] === []) {
			$data['storeIds'] = array_map('intval', (array)($prefill($defaults[ItemDefaults::KEY_STORES]) ?? []));
		}
		if ($data['labelIds'] === []) {
			$data['labelIds'] = array_map('intval', (array)($prefill($defaults[ItemDefaults::KEY_LABELS]) ?? []));
		}
		if ($data['quantity'] === null || trim((string)$data['quantity']) === '') {
			$quantity = $prefill($defaults[ItemDefaults::KEY_QUANTITY]);
			$data['quantity'] = is_string($quantity) && $quantity !== '' ? $quantity : null;
		}
		if ($target->getKind() === Checklist::KIND_INVENTORY) {
			foreach ([ItemDefaults::KEY_TARGET, ItemDefaults::KEY_REORDER_POINT] as $key) {
				if ($data[$key] === null || trim((string)$data[$key]) === '') {
					$value = $prefill($defaults[$key]);
					$data[$key] = is_string($value) && $value !== '' ? $value : null;
				}
			}
		}

		if ($target->keepsRecurrence()) {
			$recurrence = $prefill($defaults[ItemDefaults::KEY_RECURRENCE]);
			$recurrence = is_array($recurrence) ? $recurrence : [];
			$kind = $recurrence['kind'] ?? null;
			if ($kind === Checklist::RECURRENCE_KIND_RECURRING) {
				$data['rrule'] = is_string($recurrence['rrule'] ?? null) ? $recurrence['rrule'] : ItemDefaults::DEFAULT_RRULE;
				$data['repeatFromCompletion'] = (bool)($recurrence['repeatFromCompletion'] ?? false);
			} elseif ($kind === Checklist::RECURRENCE_KIND_ONCE) {
				$data['deleteOnDone'] = true;
			}
		}

		/** @var list<array<string, mixed>> $customFields */
		$customFields = $data['customFields'];
		$set = array_flip(array_map(static fn (array $v): int => (int)$v['fieldId'], $customFields));
		foreach ($defaults[ItemDefaults::KEY_FIELDS] as $field) {
			$value = $prefill($field);
			if (is_array($value) && !isset($set[$field['fieldId']])) {
				$customFields[] = $this->fieldValue($field['fieldId'], $value);
				$set[$field['fieldId']] = true;
			}
		}
		foreach ($this->fieldDefMapper->findByHouse($houseId) as $def) {
			$fieldId = (int)$def->getId();
			$scope = $def->getListId();
			if (isset($set[$fieldId]) || $def->getDeletedAt() !== null || ($scope !== null && $scope !== (int)$target->getId())) {
				continue;
			}
			$value = $this->definitionDefault($def);
			if ($value !== null) {
				$customFields[] = $this->fieldValue($fieldId, $value);
			}
		}
		$data['customFields'] = $customFields;
		return $data;
	}

	/**
	 * The value a field starts with on a new item, or null when it has none.
	 *
	 * @return array<string, mixed>|null
	 */
	private function definitionDefault(FieldDefinition $def): ?array {
		return match ($def->getType()) {
			FieldDefinition::TYPE_TEXT => ($def->getDefaultText() ?? '') !== '' ? ['valueText' => $def->getDefaultText()] : null,
			FieldDefinition::TYPE_NUMBER => $def->getDefaultNumber() !== null ? ['valueNumber' => $def->getDefaultNumber()] : null,
			FieldDefinition::TYPE_CHECKBOX => $def->getDefaultBool() ? ['valueBool' => true] : null,
			FieldDefinition::TYPE_SELECT => $def->getDefaultOptionId() !== null ? ['valueOptionId' => $def->getDefaultOptionId()] : null,
			FieldDefinition::TYPE_DATE => $def->getDateMode() === FieldDefinition::DATE_RELATIVE && $def->getDefaultOffsetDays() !== null
				? ['offsetDays' => $def->getDefaultOffsetDays()]
				: null,
			default => null,
		};
	}

	/**
	 * A default in the shape item values are stored in. A relative date holds
	 * only its offset, so it is anchored to today.
	 *
	 * @param array<array-key, mixed> $value
	 * @return array<string, mixed>
	 */
	private function fieldValue(int $fieldId, array $value): array {
		$offsetDays = isset($value['offsetDays']) ? (int)$value['offsetDays'] : null;
		$date = $value['valueDate'] ?? null;
		if ($date === null && $offsetDays !== null) {
			$date = (new \DateTimeImmutable('today'))->modify(sprintf('%+d days', $offsetDays))->getTimestamp();
		}
		return [
			'fieldId' => $fieldId,
			'valueText' => $value['valueText'] ?? null,
			'valueNumber' => $value['valueNumber'] ?? null,
			'valueBool' => (bool)($value['valueBool'] ?? false),
			'valueDate' => $date,
			'valueOptionId' => $value['valueOptionId'] ?? null,
			'offsetDays' => $offsetDays,
			'notifyOverride' => false,
			'notifyEnabled' => false,
			'notifyLeadDays' => null,
		];
	}
}
