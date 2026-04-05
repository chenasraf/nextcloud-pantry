<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry;

/**
 * @psalm-type PantryHouse = array{
 *     id: int,
 *     name: string,
 *     description: string|null,
 *     ownerUid: string,
 *     createdAt: int,
 *     updatedAt: int,
 *     role: string,
 * }
 *
 * @psalm-type PantryMember = array{
 *     id: int,
 *     houseId: int,
 *     userId: string,
 *     displayName: string,
 *     role: string,
 *     joinedAt: int,
 * }
 *
 * @psalm-type PantryList = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     description: string|null,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryListItem = array{
 *     id: int,
 *     listId: int,
 *     name: string,
 *     categoryId: int|null,
 *     quantity: string|null,
 *     bought: bool,
 *     boughtAt: int|null,
 *     boughtBy: string|null,
 *     rrule: string|null,
 *     repeatFromCompletion: bool,
 *     nextDueAt: int|null,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryCategory = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     icon: string,
 *     color: string,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantrySuccess = array{success: true}
 *
 * @psalm-type PantryLastHouse = array{houseId: int|null}
 */
class ResponseDefinitions {
}
