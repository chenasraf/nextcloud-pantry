<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch;

use Latch\Integration\Nextcloud\BridgedSource;
use Latch\Integration\Nextcloud\LatchBootstrap;
use OCA\Pantry\Db\ChecklistItem;
use OCA\Pantry\Latch\Payload\CategorySuggestionContext;
use OCA\Pantry\Latch\Payload\ChecklistItemEventPayload;
use OCA\Pantry\Latch\Payload\ChecklistItemPayload;
use OCA\Pantry\Latch\Payload\ChecklistListEventPayload;
use OCA\Pantry\Latch\Payload\ChecklistListPayload;
use OCA\Pantry\Latch\Payload\HouseEventPayload;
use OCA\Pantry\Latch\Payload\HouseListContext;
use OCA\Pantry\Latch\Payload\HouseMemberEventPayload;
use OCA\Pantry\Latch\Payload\HouseMemberListContext;
use OCA\Pantry\Latch\Payload\HouseSummary;
use OCA\Pantry\Latch\Payload\ItemActionCollectContext;
use OCA\Pantry\Latch\Payload\ItemBadgeCollectContext;
use OCA\Pantry\Latch\Payload\ItemNameRenderPayload;
use OCA\Pantry\Latch\Payload\ItemNextDueAtPayload;
use OCA\Pantry\Latch\Payload\ListItemsCollectContext;
use OCA\Pantry\Latch\Payload\MemberSummary;

/**
 * Owns the bridged `pantry` Latch source. The constructor declares every
 * filter/action/collect point so the source is fully described as soon as
 * the DI container instantiates this class.
 *
 * Services inject this class and call the typed emit helpers — they never
 * reference raw point names or payload classes.
 *
 * Each emit accepts the underlying typed inputs and constructs the payload
 * internally so callers stay terse. Latch's bridged dispatcher always
 * routes to remote handlers, so we don't `hasHandlers()`-guard here —
 * `hasHandlers()` only sees local handlers and would skip remote ones.
 */
class PantrySource {
	protected BridgedSource $source;

	public function __construct() {
		$this->source = $this->buildSource();
	}

	protected function buildSource(): BridgedSource {
		return LatchBootstrap::registry()
			->registerSource(HookPoints::SOURCE, null, HookPoints::SOURCE_TAGS)
			// Filters
			->filter(HookPoints::FILTER_ITEM_BEFORE_CREATE, ChecklistItemPayload::class)
			->filter(HookPoints::FILTER_ITEM_BEFORE_UPDATE, ChecklistItemPayload::class)
			->filter(HookPoints::FILTER_ITEM_RENDER_NAME, ItemNameRenderPayload::class)
			->filter(HookPoints::FILTER_ITEM_NEXT_DUE_AT, ItemNextDueAtPayload::class)
			->filter(HookPoints::FILTER_LIST_BEFORE_CREATE, ChecklistListPayload::class)
			->filter(HookPoints::FILTER_LIST_BEFORE_UPDATE, ChecklistListPayload::class)
			// Actions
			->action(HookPoints::ACTION_ITEM_CREATED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_UPDATED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_COMPLETED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_REOPENED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_DELETED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_RESTORED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_ITEM_PERMANENTLY_DELETED, ChecklistItemEventPayload::class)
			->action(HookPoints::ACTION_LIST_CREATED, ChecklistListEventPayload::class)
			->action(HookPoints::ACTION_LIST_UPDATED, ChecklistListEventPayload::class)
			->action(HookPoints::ACTION_LIST_DELETED, ChecklistListEventPayload::class)
			->action(HookPoints::ACTION_HOUSE_CREATED, HouseEventPayload::class)
			->action(HookPoints::ACTION_HOUSE_DELETED, HouseEventPayload::class)
			->action(HookPoints::ACTION_HOUSE_MEMBER_ADDED, HouseMemberEventPayload::class)
			->action(HookPoints::ACTION_HOUSE_MEMBER_REMOVED, HouseMemberEventPayload::class)
			->action(HookPoints::ACTION_HOUSE_MEMBER_ROLE_CHANGED, HouseMemberEventPayload::class)
			// Collectors
			->collect(HookPoints::COLLECT_LIST_CONTRIBUTED_ITEMS, ListItemsCollectContext::class)
			->collect(HookPoints::COLLECT_ITEM_EXTRA_ACTIONS, ItemActionCollectContext::class)
			->collect(HookPoints::COLLECT_ITEM_METADATA_BADGES, ItemBadgeCollectContext::class)
			->collect(HookPoints::COLLECT_CATEGORY_SUGGESTIONS, CategorySuggestionContext::class)
			->collect(HookPoints::COLLECT_HOUSE_LIST, HouseListContext::class)
			->collect(HookPoints::COLLECT_HOUSE_MEMBER_LIST, HouseMemberListContext::class);
	}

	public function bridgedSource(): BridgedSource {
		return $this->source;
	}

	// ---------- Filters ----------

	public function filterItemBeforeCreate(int $listId, array $data, ?string $actorUid): ChecklistItemPayload {
		$payload = new ChecklistItemPayload($listId, $data, $actorUid);
		/** @var ChecklistItemPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_ITEM_BEFORE_CREATE, $payload);
		return $result;
	}

	public function filterItemBeforeUpdate(
		int $listId,
		array $patch,
		array $existing,
		?string $actorUid,
	): ChecklistItemPayload {
		$payload = new ChecklistItemPayload($listId, $patch, $actorUid, $existing);
		/** @var ChecklistItemPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_ITEM_BEFORE_UPDATE, $payload);
		return $result;
	}

	public function filterItemRenderName(ChecklistItem $item, ?string $viewerUid): string {
		$payload = new ItemNameRenderPayload($item, $item->getName(), $viewerUid);
		/** @var ItemNameRenderPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_ITEM_RENDER_NAME, $payload);
		return $result->name;
	}

	public function filterItemNextDueAt(ChecklistItem $item, ?int $nextDueAt, int $now): ?int {
		$payload = new ItemNextDueAtPayload($item, $nextDueAt, $now);
		/** @var ItemNextDueAtPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_ITEM_NEXT_DUE_AT, $payload);
		return $result->nextDueAt;
	}

	public function filterListBeforeCreate(int $houseId, array $data, ?string $actorUid): ChecklistListPayload {
		$payload = new ChecklistListPayload($houseId, $data, $actorUid);
		/** @var ChecklistListPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_LIST_BEFORE_CREATE, $payload);
		return $result;
	}

	public function filterListBeforeUpdate(
		int $houseId,
		array $patch,
		array $existing,
		?string $actorUid,
	): ChecklistListPayload {
		$payload = new ChecklistListPayload($houseId, $patch, $actorUid, $existing);
		/** @var ChecklistListPayload $result */
		$result = $this->source->apply(HookPoints::FILTER_LIST_BEFORE_UPDATE, $payload);
		return $result;
	}

	// ---------- Actions ----------

	public function dispatchItemCreated(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_ITEM_CREATED, new ChecklistItemEventPayload($item, $actorUid));
	}

	public function dispatchItemUpdated(ChecklistItem $item, ?array $previous, ?string $actorUid): void {
		$this->source->dispatch(
			HookPoints::ACTION_ITEM_UPDATED,
			new ChecklistItemEventPayload($item, $actorUid, $previous),
		);
	}

	public function dispatchItemCompleted(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_ITEM_COMPLETED, new ChecklistItemEventPayload($item, $actorUid));
	}

	public function dispatchItemReopened(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_ITEM_REOPENED, new ChecklistItemEventPayload($item, $actorUid));
	}

	public function dispatchItemDeleted(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_ITEM_DELETED, new ChecklistItemEventPayload($item, $actorUid));
	}

	public function dispatchItemRestored(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_ITEM_RESTORED, new ChecklistItemEventPayload($item, $actorUid));
	}

	public function dispatchItemPermanentlyDeleted(ChecklistItem $item, ?string $actorUid): void {
		$this->source->dispatch(
			HookPoints::ACTION_ITEM_PERMANENTLY_DELETED,
			new ChecklistItemEventPayload($item, $actorUid),
		);
	}

	public function dispatchListCreated(\OCA\Pantry\Db\Checklist $list, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_LIST_CREATED, new ChecklistListEventPayload($list, $actorUid));
	}

	public function dispatchListUpdated(\OCA\Pantry\Db\Checklist $list, ?array $previous, ?string $actorUid): void {
		$this->source->dispatch(
			HookPoints::ACTION_LIST_UPDATED,
			new ChecklistListEventPayload($list, $actorUid, $previous),
		);
	}

	public function dispatchListDeleted(\OCA\Pantry\Db\Checklist $list, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_LIST_DELETED, new ChecklistListEventPayload($list, $actorUid));
	}

	public function dispatchHouseCreated(\OCA\Pantry\Db\House $house, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_HOUSE_CREATED, new HouseEventPayload($house, $actorUid));
	}

	public function dispatchHouseDeleted(\OCA\Pantry\Db\House $house, ?string $actorUid): void {
		$this->source->dispatch(HookPoints::ACTION_HOUSE_DELETED, new HouseEventPayload($house, $actorUid));
	}

	public function dispatchHouseMemberAdded(\OCA\Pantry\Db\HouseMember $member, ?string $actorUid): void {
		$this->source->dispatch(
			HookPoints::ACTION_HOUSE_MEMBER_ADDED,
			new HouseMemberEventPayload($member, $actorUid),
		);
	}

	public function dispatchHouseMemberRemoved(\OCA\Pantry\Db\HouseMember $member, ?string $actorUid): void {
		$this->source->dispatch(
			HookPoints::ACTION_HOUSE_MEMBER_REMOVED,
			new HouseMemberEventPayload($member, $actorUid),
		);
	}

	public function dispatchHouseMemberRoleChanged(
		\OCA\Pantry\Db\HouseMember $member,
		?string $previousRole,
		?string $actorUid,
	): void {
		$this->source->dispatch(
			HookPoints::ACTION_HOUSE_MEMBER_ROLE_CHANGED,
			new HouseMemberEventPayload($member, $actorUid, $previousRole),
		);
	}

	// ---------- Collectors ----------

	/**
	 * @return list<array<string,mixed>>
	 */
	public function collectContributedItems(int $listId, int $houseId, ?string $viewerUid): array {
		$ctx = new ListItemsCollectContext($listId, $houseId, $viewerUid);
		$raw = $this->source->collectFromHandlers(HookPoints::COLLECT_LIST_CONTRIBUTED_ITEMS, $ctx);
		// Latch flattens handler returns; we accept either a single item array
		// per handler or a list of them. Normalize to a list<array>.
		$out = [];
		foreach ($raw as $entry) {
			if (is_array($entry)) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function collectItemExtraActions(ChecklistItem $item, ?string $viewerUid): array {
		$ctx = new ItemActionCollectContext($item, $viewerUid);
		$raw = $this->source->collectFromHandlers(HookPoints::COLLECT_ITEM_EXTRA_ACTIONS, $ctx);
		return array_values(array_filter($raw, 'is_array'));
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function collectItemBadges(ChecklistItem $item, ?string $viewerUid): array {
		$ctx = new ItemBadgeCollectContext($item, $viewerUid);
		$raw = $this->source->collectFromHandlers(HookPoints::COLLECT_ITEM_METADATA_BADGES, $ctx);
		return array_values(array_filter($raw, 'is_array'));
	}

	public function collectCategorySuggestion(CategorySuggestionContext $ctx): ?int {
		$raw = $this->source->collectFromHandlers(HookPoints::COLLECT_CATEGORY_SUGGESTIONS, $ctx);
		foreach ($raw as $entry) {
			if (is_int($entry) && $entry > 0) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * @return list<HouseSummary|array<string,mixed>>
	 */
	public function collectHouseList(string $userId): array {
		$ctx = new HouseListContext($userId);
		return $this->source->collectFromHandlers(HookPoints::COLLECT_HOUSE_LIST, $ctx);
	}

	/**
	 * @return list<MemberSummary|array<string,mixed>>
	 */
	public function collectHouseMemberList(int $houseId, string $viewerUid): array {
		$ctx = new HouseMemberListContext($houseId, $viewerUid);
		return $this->source->collectFromHandlers(HookPoints::COLLECT_HOUSE_MEMBER_LIST, $ctx);
	}
}
