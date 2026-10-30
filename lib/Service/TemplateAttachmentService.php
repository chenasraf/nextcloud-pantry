<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\ListTemplateMapper;
use OCA\Pantry\Exception\ForbiddenException;

/**
 * Which templates a checklist or inventory draws suggestions from.
 *
 * A template is attached to a list explicitly, from either side, or to every
 * checklist and inventory in its house through its every-list switch. Only
 * active templates count: an archived or trashed one keeps its attachments but
 * feeds no suggestions until it is back.
 */
class TemplateAttachmentService {
	public function __construct(
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private ListTemplateMapper $attachments,
	) {
	}

	/**
	 * The active templates attached to a list: explicitly, and every-list.
	 *
	 * @return array{templateIds: list<int>, everyListTemplateIds: list<int>}
	 */
	public function forList(Checklist $list): array {
		$this->assertAttachable($list);
		$active = $this->activeTemplates((int)$list->getHouseId());
		$explicit = array_values(array_filter(
			$this->attachments->findTemplateIdsForList((int)$list->getId()),
			static fn (int $id): bool => isset($active[$id]),
		));
		$everyList = array_values(array_map(
			static fn (Checklist $t): int => (int)$t->getId(),
			array_filter($active, static fn (Checklist $t): bool => $t->isEveryList()),
		));
		return ['templateIds' => $explicit, 'everyListTemplateIds' => $everyList];
	}

	/**
	 * Replace the templates explicitly attached to a list.
	 *
	 * Attachments the caller cannot see — to a template they cannot access, or
	 * one that is archived or trashed — are kept as they are.
	 *
	 * @param int[] $templateIds
	 * @param callable(int $listId): bool $canAccess
	 */
	public function setForList(Checklist $list, array $templateIds, callable $canAccess): void {
		$this->assertAttachable($list);
		$houseId = (int)$list->getHouseId();
		$active = $this->activeTemplates($houseId);
		$wanted = array_values(array_unique(array_map('intval', $templateIds)));
		foreach ($wanted as $id) {
			if (!isset($active[$id])) {
				throw new \InvalidArgumentException('Not a template in this house: ' . $id);
			}
			if (!$canAccess($id)) {
				throw new ForbiddenException('No access to template ' . $id);
			}
		}
		$kept = array_filter(
			$this->attachments->findTemplateIdsForList((int)$list->getId()),
			static fn (int $id): bool => !isset($active[$id]) || !$canAccess($id),
		);
		$this->attachments->setTemplatesForList($houseId, (int)$list->getId(), [...$kept, ...$wanted]);
	}

	/**
	 * The lists a template is attached to, and whether it covers every list.
	 * Lists that are archived or trashed are left out.
	 *
	 * @return array{everyList: bool, listIds: list<int>}
	 */
	public function forTemplate(Checklist $template): array {
		$this->assertTemplate($template);
		$active = $this->activeAttachableLists((int)$template->getHouseId());
		$listIds = array_values(array_filter(
			$this->attachments->findListIdsForTemplate((int)$template->getId()),
			static fn (int $id): bool => isset($active[$id]),
		));
		return ['everyList' => $template->isEveryList(), 'listIds' => $listIds];
	}

	/**
	 * Replace the lists a template is attached to, and its every-list switch.
	 *
	 * Attachments to lists the caller cannot see are kept as they are.
	 *
	 * @param int[] $listIds
	 * @param callable(int $listId): bool $canAccess
	 */
	public function setForTemplate(Checklist $template, bool $everyList, array $listIds, callable $canAccess): void {
		$this->assertTemplate($template);
		$houseId = (int)$template->getHouseId();
		$active = $this->activeAttachableLists($houseId);
		$wanted = array_values(array_unique(array_map('intval', $listIds)));
		foreach ($wanted as $id) {
			if (!isset($active[$id])) {
				throw new \InvalidArgumentException('Not a checklist or inventory in this house: ' . $id);
			}
			if (!$canAccess($id)) {
				throw new ForbiddenException('No access to list ' . $id);
			}
		}
		$kept = array_filter(
			$this->attachments->findListIdsForTemplate((int)$template->getId()),
			static fn (int $id): bool => !isset($active[$id]) || !$canAccess($id),
		);
		$this->attachments->setListsForTemplate($houseId, (int)$template->getId(), [...$kept, ...$wanted]);

		if ($template->isEveryList() !== $everyList) {
			$template->setEveryList($everyList);
			$template->setUpdatedAt(time());
			$this->listMapper->update($template);
		}
	}

	/**
	 * Items of the active templates attached to a list that the caller can
	 * access, one row per name. Names match ignoring case and runs of
	 * whitespace; the row is the most recently edited copy and names every
	 * template the name is in.
	 *
	 * @param callable(int $listId): bool $canAccess
	 * @return list<array{item: ChecklistItem, templates: list<array{id: int, name: string}>}>
	 */
	public function suggestions(Checklist $list, callable $canAccess): array {
		$this->assertAttachable($list);
		$active = $this->activeTemplates((int)$list->getHouseId());
		$explicit = array_flip($this->attachments->findTemplateIdsForList((int)$list->getId()));

		/** @var array<string, array{item: ChecklistItem, templates: list<array{id: int, name: string}>}> $rows */
		$rows = [];
		foreach ($active as $id => $template) {
			if ((!isset($explicit[$id]) && !$template->isEveryList()) || !$canAccess($id)) {
				continue;
			}
			$source = ['id' => $id, 'name' => $template->getName()];
			foreach ($this->itemMapper->findByList($id) as $item) {
				$key = self::nameKey($item->getName());
				if (!isset($rows[$key])) {
					$rows[$key] = ['item' => $item, 'templates' => [$source]];
					continue;
				}
				if (!in_array($source, $rows[$key]['templates'], true)) {
					$rows[$key]['templates'][] = $source;
				}
				if ($item->getUpdatedAt() > $rows[$key]['item']->getUpdatedAt()) {
					$rows[$key]['item'] = $item;
				}
			}
		}
		return array_values($rows);
	}

	/**
	 * Carry a list's attachments onto its duplicate, where they still fit: a
	 * checklist or inventory keeps its templates, a template keeps its lists and
	 * its every-list switch. Crossing to or from a template drops them.
	 */
	public function copyAttachments(Checklist $source, Checklist $copy): void {
		$sourceIsTemplate = $source->getKind() === Checklist::KIND_TEMPLATE;
		$copyIsTemplate = $copy->getKind() === Checklist::KIND_TEMPLATE;
		if ($sourceIsTemplate !== $copyIsTemplate) {
			return;
		}
		$houseId = (int)$copy->getHouseId();
		if ($copyIsTemplate) {
			$this->attachments->setListsForTemplate(
				$houseId,
				(int)$copy->getId(),
				$this->attachments->findListIdsForTemplate((int)$source->getId()),
			);
			if ($source->isEveryList()) {
				$copy->setEveryList(true);
				$this->listMapper->update($copy);
			}
			return;
		}
		$this->attachments->setTemplatesForList(
			$houseId,
			(int)$copy->getId(),
			$this->attachments->findTemplateIdsForList((int)$source->getId()),
		);
	}

	private static function nameKey(string $name): string {
		return mb_strtolower((string)preg_replace('/\s+/u', ' ', trim($name)));
	}

	/**
	 * @return array<int, Checklist> Active templates in the house, by id.
	 */
	private function activeTemplates(int $houseId): array {
		return $this->byId($this->listMapper->findByHouse($houseId, 'custom', Checklist::KIND_TEMPLATE));
	}

	/**
	 * @return array<int, Checklist> Active checklists and inventories in the house, by id.
	 */
	private function activeAttachableLists(int $houseId): array {
		return $this->byId([
			...$this->listMapper->findByHouse($houseId, 'custom', Checklist::KIND_CHECKLIST),
			...$this->listMapper->findByHouse($houseId, 'custom', Checklist::KIND_INVENTORY),
		]);
	}

	/**
	 * @param Checklist[] $lists
	 * @return array<int, Checklist>
	 */
	private function byId(array $lists): array {
		$out = [];
		foreach ($lists as $list) {
			$out[(int)$list->getId()] = $list;
		}
		return $out;
	}

	private function assertAttachable(Checklist $list): void {
		if ($list->getKind() === Checklist::KIND_TEMPLATE) {
			throw new \InvalidArgumentException('Templates do not attach to templates');
		}
	}

	private function assertTemplate(Checklist $list): void {
		if ($list->getKind() !== Checklist::KIND_TEMPLATE) {
			throw new \InvalidArgumentException('Only a template attaches to lists');
		}
	}
}
