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
 *     trashRetentionDays: int,
 *     role: string,
 *     isAdmin: bool,
 *     permissions: array<string, bool>,
 * }
 *
 * @psalm-type PantryMember = array{
 *     id: int,
 *     houseId: int,
 *     userId: string,
 *     displayName: string,
 *     role: string,
 *     roleIds: list<int>,
 *     joinedAt: int,
 * }
 *
 * @psalm-type PantryRole = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     roleType: string,
 *     canViewLists: bool,
 *     canCreateLists: bool,
 *     canEditLists: bool,
 *     canDeleteLists: bool,
 *     canAddItems: bool,
 *     canDeleteItems: bool,
 *     canCopyItems: bool,
 *     canMoveItems: bool,
 *     canCheckItems: bool,
 *     canViewPhotos: bool,
 *     canUploadPhotos: bool,
 *     canUpdatePhotos: bool,
 *     canDeletePhotos: bool,
 *     canMovePhotos: bool,
 *     canViewNotes: bool,
 *     canCreateNotes: bool,
 *     canUpdateNotes: bool,
 *     canDeleteNotes: bool,
 * }
 *
 * @psalm-type PantryChecklistColor = '#f44336'|'#e91e63'|'#9c27b0'|'#673ab7'|'#3f51b5'|'#2196f3'|'#03a9f4'|'#00bcd4'|'#009688'|'#4caf50'|'#8bc34a'|'#cddc39'|'#ffeb3b'|'#ffc107'|'#ff9800'|'#ff5722'
 *
 * @psalm-type PantryList = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     description: string|null,
 *     icon: PantryChecklistIcon,
 *     color: PantryChecklistColor|null,
 *     sortOrder: int,
 *     deleteOnDoneDefault: bool,
 *     createdAt: int,
 *     updatedAt: int,
 *     deletedAt: int|null,
 *     canEdit: bool,
 *     sharedOnly: bool,
 * }
 *
 * @psalm-type PantryListItem = array{
 *     id: int,
 *     listId: int,
 *     name: string,
 *     description: string|null,
 *     categoryId: int|null,
 *     storeIds: list<int>,
 *     quantity: string|null,
 *     done: bool,
 *     doneAt: int|null,
 *     doneBy: string|null,
 *     rrule: string|null,
 *     repeatFromCompletion: bool,
 *     deleteOnDone: bool,
 *     nextDueAt: int|null,
 *     imageFileId: int|null,
 *     imageUploadedBy: string|null,
 *     addedBy: string|null,
 *     barcode: string|null,
 *     priceType: string|null,
 *     priceMin: float|null,
 *     priceMax: float|null,
 *     priceCurrency: string|null,
 *     sortOrder: int,
 *     createdAt: int,
 *     updatedAt: int,
 *     deletedAt: int|null,
 *     archivedAt: int|null,
 * }
 *
 * @psalm-type PantryCategoryIcon = 'tag'|'food'|'fruit'|'vegetable'|'bakery'|'dairy'|'meat'|'fish'|'snacks'|'cookie'|'drinks'|'coffee'|'frozen'|'household'|'pets'|'baby'|'home'|'leaf'|'pizza'|'clipboard-check'|'clipboard-list'|'format-list-checks'|'cart'|'basket'|'star'|'heart'|'calendar'|'bell'|'flag'|'bookmark'|'pin'|'map-marker'|'briefcase'|'wrench'|'silverware'|'gift'|'book'|'school'|'palette'|'camera'|'music'|'gamepad'|'run'|'dumbbell'|'pill'|'paw'|'flower'|'tree'|'broom'|'lightbulb'|'package'|'car'|'bike'|'beach'
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
 * @psalm-type PantryStoreIcon = 'store'|'storefront'|'market'|'supermarket'|'convenience'|'cart'|'basket'|'shopping'|'online'|'warehouse'|'pharmacy'|'health'|'bakery'|'butcher'|'seafood'|'produce'|'garden'|'florist'|'hardware'|'tools'|'wrench'|'electronics'|'phone'|'clothing'|'shoes'|'furniture'|'homegoods'|'home'|'books'|'toys'|'pets'|'liquor'|'coffee'|'gas'|'gift'|'jewelry'|'sports'|'beauty'|'office'|'baby'|'deli'
 *
 * @psalm-type PantryStoreColor = '#e11d48'|'#ea580c'|'#ca8a04'|'#16a34a'|'#0891b2'|'#2563eb'|'#7c3aed'|'#c026d3'|'#db2777'|'#57534e'
 *
 * @psalm-type PantryStoreOpeningHours = list<array{day: int<1, 7>, start: string, end: string}>
 *
 * @psalm-type PantryStore = array{
 *     id: int,
 *     houseId: int,
 *     name: string,
 *     icon: PantryStoreIcon,
 *     color: PantryStoreColor,
 *     brand: string|null,
 *     location: string|null,
 *     openingHours: PantryStoreOpeningHours|null,
 *     contact: string|null,
 *     responsible: string|null,
 *     notes: string|null,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryShoppingSessionStore = array{
 *     storeId: int,
 *     position: int,
 *     billedTotal: float|null,
 *     billedCurrency: string|null,
 * }
 *
 * @psalm-type PantryShoppingSession = array{
 *     id: int,
 *     houseId: int,
 *     userId: string,
 *     activeStoreId: int|null,
 *     lastSeenAt: int,
 *     closedAt: int|null,
 *     includeUnassigned: bool,
 *     isPrivate: bool,
 *     billedTotal: float|null,
 *     billedCurrency: string|null,
 *     live: bool,
 *     createdAt: int,
 *     updatedAt: int,
 *     listIds: list<int>,
 *     stores: list<PantryShoppingSessionStore>,
 * }
 *
 * @psalm-type PantryShoppingEstimate = list<array{
 *     currency: string,
 *     min: float,
 *     max: float,
 * }>
 *
 * @psalm-type PantryShoppingReviewStore = array{
 *     storeId: int|null,
 *     items: list<PantryListItem>,
 *     estimate: PantryShoppingEstimate,
 *     noPriceCount: int,
 *     billedTotal: float|null,
 *     billedCurrency: string|null,
 * }
 *
 * @psalm-type PantryShoppingReview = array{
 *     stores: list<PantryShoppingReviewStore>,
 *     grandTotal: PantryShoppingEstimate,
 *     uncheckedCount: int,
 * }
 *
 * @psalm-type PantryShoppingReminder = array{
 *     id: int,
 *     houseId: int,
 *     text: string,
 *     showOn: string,
 *     position: int,
 *     enabled: bool,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryShoppingPresenceEntry = array{
 *     userId: string,
 *     activeStoreId: int|null,
 *     lastSeenAt: int,
 * }
 *
 * @psalm-type PantryShoppingHistoryRow = array{
 *     id: int,
 *     userId: string,
 *     createdAt: int,
 *     closedAt: int|null,
 *     stores: list<string>,
 *     itemCount: int,
 *     grandTotal: list<array{currency: string, amount: float}>,
 * }
 *
 * @psalm-type PantryShare = array{
 *     id: int,
 *     houseId: int,
 *     entityType: string,
 *     entityId: int,
 *     sharedWithUid: string,
 *     permission: string,
 *     createdBy: string,
 *     createdAt: int,
 *     updatedAt: int,
 * }
 *
 * @psalm-type PantryBarcode = array{
 *     ean: string,
 *     name: string,
 *     brand: string|null,
 *     category: string|null,
 *     imageUrl: string|null,
 *     provider: string,
 * }
 *
 * @psalm-type PantrySuccess = array{success: true}
 *
 * @psalm-type PantryBatchResult = array{
 *     success: true,
 *     items: list<PantryListItem>,
 *     skipped: list<int>,
 * }
 *
 * @psalm-type PantryUserPrefs = array{
 *     lastHouseId: int|null,
 *     firstDayOfWeek: int,
 *     tapRowToComplete: bool,
 *     reuseExistingItems: string,
 * }
 *
 * @psalm-type PantryHousePrefs = array{
 *     imageFolder: string,
 *     photoSort: string,
 *     photoFoldersFirst: bool,
 *     noteSort: string,
 *     checklistItemSort: string,
 *     checklistSort: string,
 *     categorySort: string,
 *     showAddedBy: bool,
 *     lastCurrency: string,
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
 *     canEdit: bool,
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
 *     isPinned: bool,
 *     createdAt: int,
 *     updatedAt: int,
 *     deletedAt: int|null,
 *     canEdit: bool,
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
 *     deletedAt: int|null,
 *     canEdit: bool,
 * }
 */
class ResponseDefinitions {
}
