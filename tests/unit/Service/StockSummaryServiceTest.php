<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\Checklist;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Service\HouseService;
use OCA\Pantry\Service\NotificationService;
use OCA\Pantry\Service\ShareService;
use OCA\Pantry\Service\StockRules;
use OCA\Pantry\Service\StockSummaryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Summarises the "Kitchen" inventory (20) of house 1, whose recurrence time
 * is 08:00, on Oct 21.
 */
class StockSummaryServiceTest extends TestCase {
	private const NOW = '2026-10-21 09:00:00';

	/** @var NotificationService&MockObject */
	private NotificationService $notifications;
	/** @var \Closure(int, string, string, int): bool Who can view what, by house, member, share type and entity. */
	private \Closure $canView;
	private StockSummaryService $svc;
	private Checklist $kitchen;
	/** @var list<ChecklistItem> */
	private array $items = [];
	/** @var list<array{array<string, int>, list<string>}> Counts and recipients of each summary sent. */
	private array $sent = [];

	protected function setUp(): void {
		$this->kitchen = new Checklist();
		$this->kitchen->setId(20);
		$this->kitchen->setHouseId(1);
		$this->kitchen->setKind(Checklist::KIND_INVENTORY);
		$this->kitchen->setName('Kitchen');

		$listMapper = $this->createMock(ChecklistMapper::class);
		$listMapper->method('findInventoriesToSummarize')->willReturnCallback(fn (int $today): array => ($this->kitchen->getSummarizedOn() ?? 0) < $today ? [$this->kitchen] : []);
		$listMapper->method('update')->willReturnArgument(0);
		$itemMapper = $this->createMock(ChecklistItemMapper::class);
		$itemMapper->method('findByList')->willReturnCallback(fn (): array => $this->items);
		$itemMapper->method('update')->willReturnArgument(0);

		$houses = $this->createMock(HouseService::class);
		$houses->method('get')->willReturn(new House());
		$members = $this->createMock(HouseMemberMapper::class);
		$members->method('findByHouse')->willReturnCallback(fn (): array => array_map(static function (string $uid): HouseMember {
			$member = new HouseMember();
			$member->setUserId($uid);
			return $member;
		}, ['alice', 'bob']));
		$this->canView = static fn (): bool => true;
		$shares = $this->createMock(ShareService::class);
		$shares->method('canView')->willReturnCallback(fn (int $houseId, string $uid, string $type, int $id): bool => ($this->canView)($houseId, $uid, $type, $id));
		$this->notifications = $this->createMock(NotificationService::class);
		$this->notifications->method('notifyStockSummary')->willReturnCallback(function (Checklist $inventory, array $counts, array $recipients): void {
			$this->sent[] = [$counts, $recipients];
		});

		$this->svc = new StockSummaryService($listMapper, $itemMapper, $houses, $members, $shares, $this->notifications, $this->createMock(LoggerInterface::class));
	}

	private static function day(int $offset): int {
		return (new \DateTimeImmutable('2026-10-21'))->modify(sprintf('%+d days', $offset))->getTimestamp();
	}

	/** A count item with `$onHand` of a target of 12, reordering at 4. */
	private function eggs(string $onHand, ?int $expiresAt = null): ChecklistItem {
		$item = new ChecklistItem();
		$item->setId(count($this->items) + 1);
		$item->setListId(20);
		$item->setName('Eggs');
		$item->setStockMode(StockRules::MODE_COUNT);
		$item->setQuantity($onHand);
		$item->setTarget('12');
		$item->setReorderPoint('4');
		$item->setExpiresAt($expiresAt);
		return $this->items[] = $item;
	}

	private function summarize(string $at = self::NOW): int {
		return $this->svc->run((int)strtotime($at));
	}

	public function testItTellsWhatNewlyNeedsRestocking(): void {
		$this->eggs('2');
		$this->eggs('0');
		$this->eggs('12', self::day(1));
		$this->eggs('12', self::day(-1));
		$this->eggs('12');

		$this->assertSame(1, $this->summarize());

		$this->assertSame([['low' => 1, 'out' => 1, 'expiring' => 1, 'expired' => 1], ['alice', 'bob']], $this->sent[0]);
	}

	public function testAnItemStillLowIsNotNews(): void {
		$eggs = $this->eggs('2');
		$this->summarize();
		$eggs->setQuantity('1');

		$this->summarize('2026-10-22 09:00:00');

		$this->assertCount(1, $this->sent);
	}

	public function testAnItemThatRecoveredAndRanLowAgainIsNews(): void {
		$eggs = $this->eggs('2');
		$this->summarize();
		$eggs->setQuantity('12');
		$this->summarize('2026-10-22 09:00:00');
		$eggs->setQuantity('3');

		$this->summarize('2026-10-23 09:00:00');

		$this->assertCount(2, $this->sent);
	}

	public function testAnItemThatRanLowAndRecoveredBetweenSummariesIsNotReported(): void {
		$this->eggs('12');

		$this->summarize();

		$this->assertSame([], $this->sent);
	}

	public function testExpiringThenExpiredIsNewsTwice(): void {
		$this->eggs('12', self::day(1));
		$this->summarize();

		$this->summarize('2026-10-23 09:00:00');

		$this->assertSame(1, $this->sent[1][0]['expired']);
	}

	public function testItSendsOncePerDay(): void {
		$eggs = $this->eggs('2');
		$this->summarize();
		$this->eggs('0');

		$this->assertSame(0, $this->summarize('2026-10-21 18:00:00'));
		$this->assertCount(1, $this->sent);
		$this->assertSame('2', $eggs->getQuantity());
	}

	public function testItWaitsForTheHousesRecurrenceTime(): void {
		$this->eggs('2');

		$this->assertSame(0, $this->summarize('2026-10-21 07:59:00'));
		$this->assertNull($this->kitchen->getSummarizedOn());
	}

	public function testAnInventoryWithTheSummaryOffSendsNothingButMovesOn(): void {
		$this->eggs('2');
		$this->kitchen->setStockSummary(false);
		$this->summarize();
		$this->kitchen->setStockSummary(true);

		$this->summarize('2026-10-22 09:00:00');

		$this->assertSame([], $this->sent);
	}

	public function testOnlyMembersWhoCanViewTheInventoryHear(): void {
		$this->eggs('2');
		$this->canView = static fn (int $houseId, string $uid, string $type, int $id): bool => $houseId === 1 && $type === 'checklist' && $id === 20 && $uid === 'bob';

		$this->summarize();

		$this->assertSame(['bob'], $this->sent[0][1]);
	}
}
