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
 *     icon: PantryChecklistIcon,
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
 *     done: bool,
 *     doneAt: int|null,
 *     doneBy: string|null,
 *     rrule: string|null,
 *     repeatFromCompletion: bool,
 *     nextDueAt: int|null,
 *     imageFileId: int|null,
 *     imageUploadedBy: string|null,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryCategoryIcon = 'tag'|'food'|'fruit'|'vegetable'|'bakery'|'dairy'|'meat'|'fish'|'snacks'|'cookie'|'drinks'|'coffee'|'frozen'|'household'|'pets'|'baby'|'home'|'leaf'|'pizza'
 *
 * @psalm-type PantryCategoryColor = '#ef4444'|'#f97316'|'#eab308'|'#22c55e'|'#14b8a6'|'#0ea5e9'|'#6366f1'|'#a855f7'|'#ec4899'|'#78716c'
 *
 * @psalm-type PantryChecklistIcon = 'clipboard-check'|'clipboard-list'|'format-list-checks'|'cart'|'basket'|'star'|'heart'|'home'|'calendar'|'bell'|'flag'|'bookmark'|'pin'|'map-marker'|'briefcase'|'wrench'|'silverware'|'coffee'|'gift'|'book'|'school'|'palette'|'camera'|'music'|'gamepad'|'run'|'dumbbell'|'pill'|'paw'|'flower'|'tree'|'broom'|'lightbulb'|'package'|'car'|'bike'|'beach'|'tag'
 *
 * @psalm-type PantryNoteColor = '#f44336'|'#e91e63'|'#9c27b0'|'#673ab7'|'#3f51b5'|'#2196f3'|'#03a9f4'|'#00bcd4'|'#009688'|'#4caf50'|'#8bc34a'|'#cddc39'|'#ffeb3b'|'#ffc107'|'#ff9800'|'#ff5722'
 *
 * @psalm-type PantryCategory = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     icon: PantryCategoryIcon,
 *     color: PantryCategoryColor,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantrySuccess = array{success: true}
 *
 * @psalm-type PantryUserPrefs = array{
 *     lastHouseId: int|null,
 *     firstDayOfWeek: int,
 * }
 *
 * @psalm-type PantryHousePrefs = array{
 *     imageFolder: string,
 *     photoSort: string,
 *     photoFoldersFirst: bool,
 *     noteSort: string,
 *     checklistItemSort: string,
 *     notifyPhoto: bool,
 *     notifyNoteCreate: bool,
 *     notifyNoteEdit: bool,
 *     notifyItemAdd: bool,
 *     notifyItemRecur: bool,
 *     notifyItemDone: bool,
 * }
 *
 * @psalm-type PantryPhotoFolder = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryNote = array{
 *     id: int,
 *     houseId: int,
 *     title: string,
 *     content: string|null,
 *     color: PantryNoteColor|null,
 *     createdBy: string,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryPhoto = array{
 *     id: int,
 *     houseId: int,
 *     folderId: int|null,
 *     fileId: int,
 *     caption: string|null,
 *     uploadedBy: string,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 */
class ResponseDefinitions {
}
