<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ItemLabelMapper;
use OCA\Pantry\Db\ItemStoreMapper;
use OCA\Pantry\Db\LabelMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Keeps a linked inventory item and its checklist item one product.
 *
 * What either says about the product — its name, description, category,
 * barcode, stores and labels — the other says too, and so does every other
 * inventory item linked to the same checklist item. Stock, quantity, the done
 * state and recurrence stay each side's own.
 *
 * A category or label scoped to one list is only carried to an item on that
 * list; an item elsewhere keeps its own.
 */
class LinkedProductService {
	/** The item fields that describe the product rather than one list's view of it. */
	public const FIELDS = ['name', 'description', 'categoryId', 'barcode', 'storeIds', 'labelIds'];

	public function __construct(
		private ChecklistItemMapper $itemMapper,
		private CategoryMapper $categoryMapper,
		private LabelMapper $labelMapper,
		private ItemStoreMapper $itemStoreMapper,
		private ItemLabelMapper $itemLabelMapper,
	) {
	}

	/**
	 * Carry what a write just changed on $item to the items that are the same
	 * product.
	 *
	 * @param list<string> $fields The product fields the write changed.
	 */
	public function afterChange(ChecklistItem $item, array $fields): void {
		$fields = array_values(array_intersect(self::FIELDS, $fields));
		if ($fields === []) {
			return;
		}
		$partners = $this->partnersOf($item);
		if ($partners === []) {
			return;
		}
		$itemId = (int)$item->getId();
		$stores = in_array('storeIds', $fields, true) ? $this->itemStoreMapper->findStoreIdsForItem($itemId) : null;
		$labels = in_array('labelIds', $fields, true) ? $this->itemLabelMapper->findLabelIdsForItem($itemId) : null;
		$now = time();
		foreach ($partners as $partner) {
			$this->copyFields($item, $partner, $fields);
			$partner->setUpdatedAt($now);
			$this->itemMapper->update($partner);
			$partnerId = (int)$partner->getId();
			if ($stores !== null) {
				$this->itemStoreMapper->setStoresForItem($partnerId, $stores);
			}
			if ($labels !== null) {
				$this->itemLabelMapper->setLabelsForItem($partnerId, $this->labelsFor($labels, $partner));
			}
		}
	}

	/**
	 * Fill in, on either side of a link just made, what the other side says
	 * and this one leaves empty. Nothing either side says is overwritten, so
	 * linking to a row the shopper already set up keeps it as it was.
	 */
	public function align(ChecklistItem $item, ChecklistItem $row): void {
		$this->fillGaps($item, $row);
		$this->fillGaps($row, $item);
	}

	/**
	 * The live items that are the same product as $item: an inventory item's
	 * checklist item and that item's other inventory items, or a checklist
	 * item's inventory items.
	 *
	 * @return list<ChecklistItem>
	 */
	private function partnersOf(ChecklistItem $item): array {
		$id = (int)$item->getId();
		$rowId = $item->getLinkedItemId();
		$partners = [];
		if ($rowId !== null) {
			try {
				$partners[] = $this->itemMapper->findById($rowId, true);
			} catch (DoesNotExistException) {
				return [];
			}
			$linked = $this->itemMapper->findLinkedTo([$rowId]);
		} else {
			$linked = $this->itemMapper->findLinkedTo([$id]);
		}
		foreach ($linked as $other) {
			if ((int)$other->getId() !== $id) {
				$partners[] = $other;
			}
		}
		return array_values(array_filter(
			$partners,
			static fn (ChecklistItem $partner): bool => $partner->getDeletedAt() === null,
		));
	}

	/**
	 * @param list<string> $fields
	 */
	private function copyFields(ChecklistItem $from, ChecklistItem $to, array $fields): void {
		foreach ($fields as $field) {
			switch ($field) {
				case 'name':
					$to->setName($from->getName());
					break;
				case 'description':
					$to->setDescription($from->getDescription());
					break;
				case 'barcode':
					$to->setBarcode($from->getBarcode());
					break;
				case 'categoryId':
					$category = $from->getCategoryId();
					if ($this->categoryVisibleOn($category, $to->getListId())) {
						$to->setCategoryId($category);
					}
					break;
			}
		}
	}

	private function fillGaps(ChecklistItem $from, ChecklistItem $to): void {
		$changed = false;
		if ($to->getDescription() === null && $from->getDescription() !== null) {
			$to->setDescription($from->getDescription());
			$changed = true;
		}
		if ($to->getBarcode() === null && $from->getBarcode() !== null) {
			$to->setBarcode($from->getBarcode());
			$changed = true;
		}
		$category = $from->getCategoryId();
		if ($to->getCategoryId() === null && $category !== null && $this->categoryVisibleOn($category, $to->getListId())) {
			$to->setCategoryId($category);
			$changed = true;
		}
		$toId = (int)$to->getId();
		$fromId = (int)$from->getId();
		if ($this->itemStoreMapper->findStoreIdsForItem($toId) === []) {
			$stores = $this->itemStoreMapper->findStoreIdsForItem($fromId);
			if ($stores !== []) {
				$this->itemStoreMapper->setStoresForItem($toId, $stores);
				$changed = true;
			}
		}
		if ($this->itemLabelMapper->findLabelIdsForItem($toId) === []) {
			$labels = $this->labelsFor($this->itemLabelMapper->findLabelIdsForItem($fromId), $to);
			if ($labels !== []) {
				$this->itemLabelMapper->setLabelsForItem($toId, $labels);
				$changed = true;
			}
		}
		if ($changed) {
			$to->setUpdatedAt(time());
			$this->itemMapper->update($to);
		}
	}

	private function categoryVisibleOn(?int $categoryId, int $listId): bool {
		if ($categoryId === null) {
			return true;
		}
		try {
			$scope = $this->categoryMapper->findById($categoryId)->getListId();
		} catch (DoesNotExistException) {
			return false;
		}
		return $scope === null || $scope === $listId;
	}

	/**
	 * The labels $to carries for a product labelled $labelIds: those of them it
	 * can see, and the labels scoped to its own list it already had, which the
	 * other item could not.
	 *
	 * @param int[] $labelIds
	 * @return list<int>
	 */
	private function labelsFor(array $labelIds, ChecklistItem $to): array {
		$listId = $to->getListId();
		$keep = [];
		foreach ($labelIds as $labelId) {
			if ($this->labelScope($labelId) === null) {
				$keep[] = $labelId;
			}
		}
		foreach ($this->itemLabelMapper->findLabelIdsForItem((int)$to->getId()) as $labelId) {
			if ($this->labelScope($labelId) === $listId) {
				$keep[] = $labelId;
			}
		}
		return array_values(array_unique($keep));
	}

	/** The list a label is scoped to; null for a house-wide one. -1 when it is gone. */
	private function labelScope(int $labelId): ?int {
		try {
			return $this->labelMapper->findById($labelId)->getListId();
		} catch (DoesNotExistException) {
			return -1;
		}
	}
}
