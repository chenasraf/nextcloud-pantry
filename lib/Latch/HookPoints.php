<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch;

/**
 * Names of the Latch source and every hook point Pantry declares.
 *
 * Kept here so producers and external handlers reference the same strings
 * without typos. Capability tags identify Pantry to discovery queries like
 * `$registry->sourcesByTag('todo-list')`.
 */
final class HookPoints {
	public const SOURCE = 'pantry';

	/** @var list<string> */
	public const SOURCE_TAGS = ['todo-list', 'shopping-list', 'household'];

	// Filters — chained transforms, ordered by priority().
	public const FILTER_ITEM_BEFORE_CREATE = 'item.before-create';
	public const FILTER_ITEM_BEFORE_UPDATE = 'item.before-update';
	public const FILTER_ITEM_RENDER_NAME = 'item.render-name';
	public const FILTER_ITEM_NEXT_DUE_AT = 'item.next-due-at';
	public const FILTER_LIST_BEFORE_CREATE = 'list.before-create';
	public const FILTER_LIST_BEFORE_UPDATE = 'list.before-update';

	// Actions — fire-and-forget events, return values ignored.
	public const ACTION_ITEM_CREATED = 'item.created';
	public const ACTION_ITEM_UPDATED = 'item.updated';
	public const ACTION_ITEM_COMPLETED = 'item.completed';
	public const ACTION_ITEM_REOPENED = 'item.reopened';
	public const ACTION_ITEM_DELETED = 'item.deleted';
	public const ACTION_ITEM_RESTORED = 'item.restored';
	public const ACTION_ITEM_PERMANENTLY_DELETED = 'item.permanently-deleted';
	public const ACTION_LIST_CREATED = 'list.created';
	public const ACTION_LIST_UPDATED = 'list.updated';
	public const ACTION_LIST_DELETED = 'list.deleted';
	public const ACTION_HOUSE_CREATED = 'house.created';
	public const ACTION_HOUSE_DELETED = 'house.deleted';
	public const ACTION_HOUSE_MEMBER_ADDED = 'house.member-added';
	public const ACTION_HOUSE_MEMBER_REMOVED = 'house.member-removed';
	public const ACTION_HOUSE_MEMBER_ROLE_CHANGED = 'house.member-role-changed';

	// Collectors — gather an array of contributions from all handlers.
	public const COLLECT_LIST_CONTRIBUTED_ITEMS = 'list.contributed-items';
	public const COLLECT_ITEM_EXTRA_ACTIONS = 'item.extra-actions';
	public const COLLECT_ITEM_METADATA_BADGES = 'item.metadata-badges';
	public const COLLECT_CATEGORY_SUGGESTIONS = 'category.suggestions';
	public const COLLECT_HOUSE_LIST = 'house.list';
	public const COLLECT_HOUSE_MEMBER_LIST = 'house.member.list';
}
