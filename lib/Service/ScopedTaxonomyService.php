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
