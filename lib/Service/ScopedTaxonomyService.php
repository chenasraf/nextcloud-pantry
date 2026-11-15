<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Category;
use OCA\Pantry\Db\CategoryMapper;
use OCA\Pantry\Db\FieldDefinition;
use OCA\Pantry\Db\FieldDefinitionMapper;
use OCA\Pantry\Db\FieldOption;
use OCA\Pantry\Db\FieldOptionMapper;
use OCA\Pantry\Db\Label;
use OCA\Pantry\Db\LabelMapper;

/**
 * Copies a list-scoped category, label or custom field onto another list, as
 * that list's own. Used wherever items leave a list for one that cannot see
 * the source's scoped taxonomy.
 */
class ScopedTaxonomyService {
	public function __construct(
		private CategoryMapper $categoryMapper,
		private LabelMapper $labelMapper,
		private FieldDefinitionMapper $fieldDefMapper,
		private FieldOptionMapper $fieldOptionMapper,
	) {
	}

	/**
	 * Names match ignoring case and runs of whitespace.
	 */
	public static function nameKey(string $name): string {
		return mb_strtolower((string)preg_replace('/\s+/u', ' ', trim($name)));
	}

	/**
	 * A category or label the target can use with this name, preferring the
	 * target's own over a house-wide one.
	 *
	 * @template T of Category|Label
	 * @param list<T> $all
	 * @return T|null
	 */
	public static function matchByName(array $all, string $name, int $targetId): Category|Label|null {
		$key = self::nameKey($name);
		$found = null;
		foreach ($all as $entity) {
			$scope = $entity->getListId();
			if (($scope !== null && $scope !== $targetId) || self::nameKey($entity->getName()) !== $key) {
				continue;
			}
			if ($scope === $targetId) {
				return $entity;
			}
			$found ??= $entity;
		}
		return $found;
	}

	/**
	 * The category an item on `$listId` uses for `$source`: `$source` itself
	 * when the list can see it, else the one of the same name the list can
	 * see, else a copy made for the list.
	 */
	public function categoryFor(Category $source, int $listId, int $now): Category {
		$scope = $source->getListId();
		if ($scope === null || $scope === $listId) {
			return $source;
		}
		$all = array_values($this->categoryMapper->findByHouse((int)$source->getHouseId()));
		return self::matchByName($all, $source->getName(), $listId) ?? $this->copyCategory($source, $listId, $now);
	}

	// ----- Items leaving a list -----

	/**
	 * The category an item leaving `$sourceListId` uses on `$targetListId`.
	 *
	 * Every reference the target can already see is kept. One scoped to the
	 * source list matches the target's own or a house-wide one of the same name
	 * (a field also by type, its select option by label), or is copied onto the
	 * target when there is none, so later items match that copy instead of
	 * piling up more. {@see labelIdsFor()} and {@see fieldValuesFor()} follow
	 * the same rule.
	 */
	public function categoryIdFor(?int $categoryId, int $houseId, int $sourceListId, int $targetListId, int $now): ?int {
		if ($categoryId === null || $sourceListId === $targetListId) {
			return $categoryId;
		}
		$all = array_values($this->categoryMapper->findByHouse($houseId));
		$category = self::byId($all, $categoryId);
		if (!$category instanceof Category || $category->getListId() !== $sourceListId) {
			return $categoryId;
		}
		$match = self::matchByName($all, $category->getName(), $targetListId);
		return (int)($match ?? $this->copyCategory($category, $targetListId, $now))->getId();
	}

	/**
	 * @param int[] $labelIds
	 * @return list<int>
	 */
	public function labelIdsFor(array $labelIds, int $houseId, int $sourceListId, int $targetListId, int $now): array {
		$labelIds = array_map('intval', $labelIds);
		if ($labelIds === [] || $sourceListId === $targetListId) {
			return array_values($labelIds);
		}
		$all = array_values($this->labelMapper->findByHouse($houseId));
		$out = [];
		foreach ($labelIds as $labelId) {
			$label = self::byId($all, $labelId);
			if (!$label instanceof Label || $label->getListId() !== $sourceListId) {
				$out[] = $labelId;
				continue;
			}
			$match = self::matchByName($all, $label->getName(), $targetListId);
			if ($match === null) {
				$match = $this->copyLabel($label, $targetListId, $now);
				$all[] = $match;
			}
			$out[] = (int)$match->getId();
		}
		return array_values(array_unique($out));
	}

	/**
	 * A matched field without an option of the same label leaves the value's
	 * option empty.
	 *
	 * @param list<array<string, mixed>> $values
	 * @return list<array<string, mixed>>
	 */
	public function fieldValuesFor(array $values, int $houseId, int $sourceListId, int $targetListId, int $now): array {
		if ($values === [] || $sourceListId === $targetListId) {
			return $values;
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
			$def = $defs[(int)($value['fieldId'] ?? 0)] ?? null;
			if ($def === null || $def->getListId() !== $sourceListId) {
				$out[] = $value;
				continue;
			}
			$optionId = isset($value['valueOptionId']) ? (int)$value['valueOptionId'] : null;
			$match = $this->matchField($defs, $def, $targetListId);
			if ($match === null) {
				[$match, $optionMap] = $this->copyField($def, $targetListId, $now);
				$defs[(int)$match->getId()] = $match;
				$value['valueOptionId'] = $optionId !== null ? ($optionMap[$optionId] ?? null) : null;
			} elseif ($optionId !== null) {
				$value['valueOptionId'] = $this->matchOption($optionId, (int)$def->getId(), (int)$match->getId());
			}
			$value['fieldId'] = (int)$match->getId();
			$out[] = $value;
		}
		return $out;
	}

	/**
	 * @param array<int, FieldDefinition> $defs
	 */
	private function matchField(array $defs, FieldDefinition $source, int $targetId): ?FieldDefinition {
		$key = self::nameKey($source->getName());
		$found = null;
		foreach ($defs as $def) {
			$scope = $def->getListId();
			if (($scope !== null && $scope !== $targetId)
				|| $def->getType() !== $source->getType()
				|| self::nameKey($def->getName()) !== $key) {
				continue;
			}
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
				$label = self::nameKey($option->getLabel());
				break;
			}
		}
		if ($label === null) {
			return null;
		}
		foreach ($this->fieldOptionMapper->findByField($targetFieldId) as $option) {
			if (self::nameKey($option->getLabel()) === $label) {
				return (int)$option->getId();
			}
		}
		return null;
	}

	/**
	 * @param list<Category>|list<Label> $all
	 */
	private static function byId(array $all, int $id): Category|Label|null {
		foreach ($all as $entity) {
			if ((int)$entity->getId() === $id) {
				return $entity;
			}
		}
		return null;
	}

	public function copyCategory(Category $source, int $listId, int $now): Category {
		$copy = new Category();
		$copy->setHouseId($source->getHouseId());
		$copy->setListId($listId);
		$copy->setName($source->getName());
		$copy->setIcon($source->getIcon());
		$copy->setColor($source->getColor());
		$copy->setSortOrder($source->getSortOrder());
		$copy->setCreatedAt($now);
		$copy->setUpdatedAt($now);
		return $this->categoryMapper->insert($copy);
	}

	public function copyLabel(Label $source, int $listId, int $now): Label {
		$copy = new Label();
		$copy->setHouseId($source->getHouseId());
		$copy->setListId($listId);
		$copy->setName($source->getName());
		$copy->setIcon($source->getIcon());
		$copy->setColor($source->getColor());
		$copy->setSortOrder($source->getSortOrder());
		$copy->setCreatedAt($now);
		$copy->setUpdatedAt($now);
		return $this->labelMapper->insert($copy);
	}

	/**
	 * Copy a field definition along with its select options.
	 *
	 * @return array{0: FieldDefinition, 1: array<int, int>} The copy, and source option id → the copy's option id.
	 */
	public function copyField(FieldDefinition $source, int $listId, int $now): array {
		$copy = new FieldDefinition();
		$copy->setHouseId($source->getHouseId());
		$copy->setListId($listId);
		$copy->setName($source->getName());
		$copy->setType($source->getType());
		$copy->setSortOrder($source->getSortOrder());
		$copy->setHint($source->getHint());
		$copy->setMultiline($source->getMultiline());
		$copy->setDefaultText($source->getDefaultText());
		$copy->setDefaultNumber($source->getDefaultNumber());
		$copy->setDefaultBool($source->getDefaultBool());
		$copy->setDateMode($source->getDateMode());
		$copy->setDefaultOffsetDays($source->getDefaultOffsetDays());
		$copy->setNotifyDefault($source->getNotifyDefault());
		$copy->setLeadDays($source->getLeadDays());
		$copy->setOverridePolicy($source->getOverridePolicy());
		$copy->setStopWhenDone($source->getStopWhenDone());
		$copy->setCreatedAt($now);
		$copy->setUpdatedAt($now);
		$copy = $this->fieldDefMapper->insert($copy);

		$optionMap = [];
		foreach ($this->fieldOptionMapper->findByField((int)$source->getId()) as $option) {
			$optionCopy = new FieldOption();
			$optionCopy->setFieldId((int)$copy->getId());
			$optionCopy->setLabel($option->getLabel());
			$optionCopy->setSortOrder($option->getSortOrder());
			$optionMap[(int)$option->getId()] = (int)$this->fieldOptionMapper->insert($optionCopy)->getId();
		}

		// The default option is one of the options just copied, so it can only
		// be pointed at the new row after they exist.
		$defaultOptionId = $source->getDefaultOptionId();
		if ($defaultOptionId !== null && isset($optionMap[$defaultOptionId])) {
			$copy->setDefaultOptionId($optionMap[$defaultOptionId]);
			$this->fieldDefMapper->update($copy);
		}
		return [$copy, $optionMap];
	}
}
