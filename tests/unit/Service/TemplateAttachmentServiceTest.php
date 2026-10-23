<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\ListTemplateMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Service\TemplateAttachmentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateAttachmentServiceTest extends TestCase {
	/** @var ChecklistMapper&MockObject */
	private ChecklistMapper $listMapper;
	/** @var ChecklistItemMapper&MockObject */
	private ChecklistItemMapper $itemMapper;
	/** @var ListTemplateMapper&MockObject */
	private ListTemplateMapper $attachments;
	private TemplateAttachmentService $svc;

	/** @var array<string, list<Checklist>> Active lists in house 1, by kind. */
	private array $active = [];

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ChecklistMapper::class);
		$this->itemMapper = $this->createMock(ChecklistItemMapper::class);
		$this->attachments = $this->createMock(ListTemplateMapper::class);
		$this->listMapper->method('findByHouse')->willReturnCallback(
			fn (int $houseId, string $sort, ?string $kind): array => $this->active[$kind] ?? [],
		);
		$this->svc = new TemplateAttachmentService($this->listMapper, $this->itemMapper, $this->attachments);
	}

	private function makeList(int $id, string $kind, string $name = 'List', bool $everyList = false): Checklist {
		$list = new Checklist();
		$list->setId($id);
		$list->setHouseId(1);
		$list->setKind($kind);
		$list->setName($name);
		$list->setEveryList($everyList);
		$this->active[$kind][] = $list;
		return $list;
	}

	private function makeItem(int $id, int $listId, string $name, int $updatedAt = 0): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId($id);
		$item->setListId($listId);
		$item->setName($name);
		$item->setUpdatedAt($updatedAt);
		return $item;
	}

	private static function anyone(): callable {
		return static fn (int $id): bool => true;
	}

	public function testForListNamesExplicitAndEveryListTemplates(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(10, Checklist::KIND_TEMPLATE);
		$this->makeList(11, Checklist::KIND_TEMPLATE, everyList: true);
		// 12 is an attachment to a template that is archived or trashed.
		$this->attachments->method('findTemplateIdsForList')->with(1)->willReturn([10, 12]);

		$this->assertSame(
			['templateIds' => [10], 'everyListTemplateIds' => [11]],
			$this->svc->forList($list),
		);
	}

	public function testTemplatesNeverAttachToTemplates(): void {
		$template = $this->makeList(10, Checklist::KIND_TEMPLATE);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->forList($template);
	}

	public function testSetForListRejectsAListThatIsNotATemplate(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(2, Checklist::KIND_INVENTORY);
		$this->attachments->expects($this->never())->method('setTemplatesForList');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->setForList($list, [2], self::anyone());
	}

	public function testSetForListRefusesATemplateTheCallerCannotAccess(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(10, Checklist::KIND_TEMPLATE);
		$this->attachments->expects($this->never())->method('setTemplatesForList');

		$this->expectException(ForbiddenException::class);
		$this->svc->setForList($list, [10], static fn (int $id): bool => false);
	}

	public function testSetForListKeepsAttachmentsTheCallerCannotSee(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(10, Checklist::KIND_TEMPLATE);
		$this->makeList(11, Checklist::KIND_TEMPLATE);
		$this->makeList(13, Checklist::KIND_TEMPLATE);
		// 11 is out of the caller's reach, 12 is archived; 13 is being detached.
		$this->attachments->method('findTemplateIdsForList')->willReturn([11, 12, 13]);
		$this->attachments->expects($this->once())->method('setTemplatesForList')->with(1, 1, [11, 12, 10]);

		$this->svc->setForList($list, [10], static fn (int $id): bool => $id !== 11);
	}

	public function testSetForTemplateReplacesListsAndTheEveryListSwitch(): void {
		$template = $this->makeList(10, Checklist::KIND_TEMPLATE);
		$this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(2, Checklist::KIND_INVENTORY);
		$this->makeList(3, Checklist::KIND_CHECKLIST);
		$this->attachments->method('findListIdsForTemplate')->willReturn([3]);
		$this->attachments->expects($this->once())->method('setListsForTemplate')->with(1, 10, [3, 1, 2]);
		$this->listMapper->expects($this->once())->method('update')->with($template);

		$this->svc->setForTemplate($template, true, [1, 2], static fn (int $id): bool => $id !== 3);

		$this->assertTrue($template->isEveryList());
	}

	public function testSetForTemplateRejectsATemplateAsAList(): void {
		$template = $this->makeList(10, Checklist::KIND_TEMPLATE);
		$this->makeList(11, Checklist::KIND_TEMPLATE);
		$this->attachments->expects($this->never())->method('setListsForTemplate');

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->setForTemplate($template, false, [11], self::anyone());
	}

	public function testOnlyATemplateHasAnEveryListSwitch(): void {
		$list = new Checklist();
		$list->setKind(Checklist::KIND_CHECKLIST);
		$list->setEveryList(true);

		$this->assertFalse($list->isEveryList());
	}

	public function testSuggestionsComeFromAttachedAndEveryListTemplates(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(10, Checklist::KIND_TEMPLATE, 'Weekly');
		$this->makeList(11, Checklist::KIND_TEMPLATE, 'Staples', everyList: true);
		$this->makeList(12, Checklist::KIND_TEMPLATE, 'Not attached');
		$this->attachments->method('findTemplateIdsForList')->willReturn([10]);
		$this->itemMapper->method('findByList')->willReturnCallback(fn (int $id): array => match ($id) {
			10 => [$this->makeItem(100, 10, 'Bread')],
			11 => [$this->makeItem(110, 11, 'Rice')],
			12 => [$this->makeItem(120, 12, 'Caviar')],
		});

		$rows = $this->svc->suggestions($list, self::anyone());

		$this->assertSame(['Bread', 'Rice'], array_map(static fn (array $r): string => $r['item']->getName(), $rows));
		$this->assertSame([['id' => 11, 'name' => 'Staples']], $rows[1]['templates']);
	}

	public function testSuggestionsSkipTemplatesTheCallerCannotAccess(): void {
		$list = $this->makeList(1, Checklist::KIND_INVENTORY);
		$this->makeList(10, Checklist::KIND_TEMPLATE, 'Private', everyList: true);
		$this->itemMapper->expects($this->never())->method('findByList');

		$this->assertSame([], $this->svc->suggestions($list, static fn (int $id): bool => false));
	}

	public function testSuggestionsCollapseOneNameIntoTheLatestCopy(): void {
		$list = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$this->makeList(10, Checklist::KIND_TEMPLATE, 'Weekly');
		$this->makeList(11, Checklist::KIND_TEMPLATE, 'Party');
		$this->attachments->method('findTemplateIdsForList')->willReturn([10, 11]);
		$newer = $this->makeItem(110, 11, ' oat  MILK ', 200);
		$this->itemMapper->method('findByList')->willReturnCallback(fn (int $id): array => match ($id) {
			10 => [$this->makeItem(100, 10, 'Oat milk', 100)],
			11 => [$newer],
		});

		$rows = $this->svc->suggestions($list, self::anyone());

		$this->assertCount(1, $rows);
		$this->assertSame($newer, $rows[0]['item']);
		$this->assertSame([['id' => 10, 'name' => 'Weekly'], ['id' => 11, 'name' => 'Party']], $rows[0]['templates']);
	}

	public function testDuplicatingAListKeepsItsTemplates(): void {
		$source = $this->makeList(1, Checklist::KIND_CHECKLIST);
		$copy = $this->makeList(2, Checklist::KIND_INVENTORY);
		$this->attachments->method('findTemplateIdsForList')->with(1)->willReturn([10, 11]);
		$this->attachments->expects($this->once())->method('setTemplatesForList')->with(1, 2, [10, 11]);

		$this->svc->copyAttachments($source, $copy);
	}

	public function testDuplicatingATemplateKeepsItsListsAndEveryList(): void {
		$source = $this->makeList(10, Checklist::KIND_TEMPLATE, everyList: true);
		$copy = $this->makeList(20, Checklist::KIND_TEMPLATE);
		$this->attachments->method('findListIdsForTemplate')->with(10)->willReturn([1]);
		$this->attachments->expects($this->once())->method('setListsForTemplate')->with(1, 20, [1]);

		$this->svc->copyAttachments($source, $copy);

		$this->assertTrue($copy->isEveryList());
	}

	public function testDuplicatingIntoOrOutOfATemplateDropsAttachments(): void {
		$this->attachments->expects($this->never())->method('setTemplatesForList');
		$this->attachments->expects($this->never())->method('setListsForTemplate');

		$this->svc->copyAttachments($this->makeList(1, Checklist::KIND_CHECKLIST), $this->makeList(20, Checklist::KIND_TEMPLATE));
		$this->svc->copyAttachments($this->makeList(10, Checklist::KIND_TEMPLATE), $this->makeList(2, Checklist::KIND_INVENTORY));
	}
}
