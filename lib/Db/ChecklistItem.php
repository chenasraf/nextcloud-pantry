<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method int|null getCategoryId()
 * @method void setCategoryId(?int $categoryId)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method bool getDone()
 * @method void setDone(bool $done)
 * @method int|null getDoneAt()
 * @method void setDoneAt(?int $doneAt)
 * @method string|null getDoneBy()
 * @method void setDoneBy(?string $doneBy)
 * @method string|null getRrule()
 * @method void setRrule(?string $rrule)
 * @method bool getRepeatFromCompletion()
 * @method void setRepeatFromCompletion(bool $repeatFromCompletion)
 * @method bool getDeleteOnDone()
 * @method void setDeleteOnDone(bool $deleteOnDone)
 * @method int|null getNextDueAt()
 * @method void setNextDueAt(?int $nextDueAt)
 * @method int|null getImageFileId()
 * @method void setImageFileId(?int $imageFileId)
 * @method string|null getImageUploadedBy()
 * @method void setImageUploadedBy(?string $imageUploadedBy)
 * @method string|null getAddedBy()
 * @method void setAddedBy(?string $addedBy)
 * @method string|null getBarcode()
 * @method void setBarcode(?string $barcode)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method int|null getArchivedAt()
 * @method void setArchivedAt(?int $archivedAt)
 * @method string|null getStockMode()
 * @method void setStockMode(?string $stockMode)
 * @method string|null getStockLevel()
 * @method void setStockLevel(?string $stockLevel)
 * @method string|null getTarget()
 * @method void setTarget(?string $target)
 * @method string|null getReorderPoint()
 * @method void setReorderPoint(?string $reorderPoint)
 * @method int|null getExpiresAt()
 * @method void setExpiresAt(?int $expiresAt)
 * @method int|null getShelfLifeDays()
 * @method void setShelfLifeDays(?int $shelfLifeDays)
 * @method int|null getLinkedItemId()
 * @method void setLinkedItemId(?int $linkedItemId)
 * @method int|null getLinkedAt()
 * @method void setLinkedAt(?int $linkedAt)
 * @method void setAutoLink(?bool $autoLink)
 * @method void setQuantityPinned(?bool $quantityPinned)
 * @method void setMemberReopened(?bool $memberReopened)
 * @method string|null getRestockRecord()
 * @method void setRestockRecord(?string $restockRecord)
 * @method string|null getExpirySyncedStatus()
 * @method void setExpirySyncedStatus(?string $expirySyncedStatus)
 *
 * @psalm-type RestockEntry = array{itemId: int, listId: int, itemName: string, listName: string, added: string|null, previousLevel: string|null}
 */
class ChecklistItem extends Entity implements \JsonSerializable {
	protected int $listId = 0;
	protected string $name = '';
	protected ?string $description = null;
	protected ?int $categoryId = null;
	protected ?string $quantity = null;
	protected bool $done = false;
	protected ?int $doneAt = null;
	protected ?string $doneBy = null;
	protected ?string $rrule = null;
	protected bool $repeatFromCompletion = false;
	protected bool $deleteOnDone = false;
	protected ?int $nextDueAt = null;
	protected ?int $imageFileId = null;
	protected ?string $imageUploadedBy = null;
	protected ?string $addedBy = null;
	protected ?string $barcode = null;
	protected int $sortOrder = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $deletedAt = null;
	protected ?int $archivedAt = null;
	protected ?string $stockMode = null;
	protected ?string $stockLevel = null;
	protected ?string $target = null;
	protected ?string $reorderPoint = null;
	protected ?int $expiresAt = null;
	protected ?int $shelfLifeDays = null;
	/** The checklist item that restocks this inventory item. */
	protected ?int $linkedItemId = null;
	protected ?int $linkedAt = null;
	protected ?bool $autoLink = true;
	/** A member set a linked checklist item's quantity, so restock syncs leave it until it is next done. */
	protected ?bool $quantityPinned = false;
	/** A member reopened the checklist item, so stock recovering does not tick it off until it is next done. */
	protected ?bool $memberReopened = false;
	/** JSON list of what a member's tick put back into stock, for their uncheck to take out. */
	protected ?string $restockRecord = null;
	/** The expiry status the restock sync last acted on, for the daily job to compare against. */
	protected ?string $expirySyncedStatus = null;

	/**
	 * Prices resolved for this item, attached transiently by the service/controller
	 * layer (not a DB column). Each entry is the serialized {@see ItemPrice} shape.
	 *
	 * @var list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>
	 */
	private array $prices = [];

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('done', 'boolean');
		$this->addType('doneAt', 'integer');
		$this->addType('repeatFromCompletion', 'boolean');
		$this->addType('deleteOnDone', 'boolean');
		$this->addType('nextDueAt', 'integer');
		$this->addType('imageFileId', 'integer');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('archivedAt', 'integer');
		$this->addType('expiresAt', 'integer');
		$this->addType('shelfLifeDays', 'integer');
		$this->addType('linkedItemId', 'integer');
		$this->addType('linkedAt', 'integer');
		$this->addType('autoLink', 'boolean');
		$this->addType('quantityPinned', 'boolean');
		$this->addType('memberReopened', 'boolean');
		// Force these bool fields to be included in INSERTs. Their PHP defaults
		// match the initial value, so the magic setter would otherwise never
		// mark them dirty and the column would be omitted from the INSERT.
		// fromRow() resets updated fields after hydration, so reads are unaffected.
		$this->markFieldUpdated('done');
		$this->markFieldUpdated('repeatFromCompletion');
		$this->markFieldUpdated('deleteOnDone');
	}

	/** A row from before the column existed links, like a new item. */
	public function autoLinks(): bool {
		return $this->autoLink !== false;
	}

	public function isQuantityPinned(): bool {
		return $this->quantityPinned === true;
	}

	public function isMemberReopened(): bool {
		return $this->memberReopened === true;
	}

	/**
	 * What the member's tick of this checklist item put back into stock, each
	 * inventory item's added amount or, for a level item, its level before.
	 *
	 * @return list<RestockEntry>|null
	 */
	public function getRestock(): ?array {
		if ($this->restockRecord === null) {
			return null;
		}
		$decoded = json_decode($this->restockRecord, true);
		if (!is_array($decoded)) {
			return null;
		}
		$entries = [];
		foreach ($decoded as $entry) {
			if (!is_array($entry) || !is_int($entry['itemId'] ?? null)) {
				continue;
			}
			$entries[] = [
				'itemId' => $entry['itemId'],
				'listId' => (int)($entry['listId'] ?? 0),
				'itemName' => (string)($entry['itemName'] ?? ''),
				'listName' => (string)($entry['listName'] ?? ''),
				'added' => isset($entry['added']) ? (string)$entry['added'] : null,
				'previousLevel' => isset($entry['previousLevel']) ? (string)$entry['previousLevel'] : null,
			];
		}
		return $entries !== [] ? $entries : null;
	}

	/**
	 * @param list<RestockEntry>|null $entries
	 */
	public function setRestock(?array $entries): void {
		$this->setRestockRecord($entries !== null && $entries !== [] ? json_encode($entries, JSON_THROW_ON_ERROR) : null);
	}

	/** Done by stock recovering rather than by a member: "No longer needed". */
	public function isSystemDone(): bool {
		return $this->done && $this->doneBy === null;
	}

	/**
	 * Attach the item's resolved prices (not persisted here — prices live in
	 * pantry_item_prices). Each entry is the serialized {@see ItemPrice} shape.
	 *
	 * @param list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}> $prices
	 */
	public function setResolvedPrices(array $prices): void {
		$this->prices = $prices;
	}

	/**
	 * @return list<array{storeId: ?int, priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}>
	 */
	public function getResolvedPrices(): array {
		return $this->prices;
	}

	/**
	 * The price to apply for a given store: the store's own price when set,
	 * otherwise the store-less price, otherwise an all-null "no price". Passing
	 * null resolves the store-less price directly.
	 *
	 * @return array{priceType: ?string, priceMin: ?float, priceMax: ?float, priceCurrency: ?string}
	 */
	public function priceForStore(?int $storeId): array {
		$storeless = null;
		foreach ($this->prices as $price) {
			$priceStoreId = $price['storeId'] ?? null;
			if ($storeId !== null && $priceStoreId === $storeId) {
				return [
					'priceType' => $price['priceType'] ?? null,
					'priceMin' => $price['priceMin'] ?? null,
					'priceMax' => $price['priceMax'] ?? null,
					'priceCurrency' => $price['priceCurrency'] ?? null,
				];
			}
			if ($priceStoreId === null) {
				$storeless = $price;
			}
		}
		return [
			'priceType' => $storeless['priceType'] ?? null,
			'priceMin' => $storeless['priceMin'] ?? null,
			'priceMax' => $storeless['priceMax'] ?? null,
			'priceCurrency' => $storeless['priceCurrency'] ?? null,
		];
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'listId' => $this->listId,
			'name' => $this->name,
			'description' => $this->description,
			'categoryId' => $this->categoryId,
			'quantity' => $this->quantity,
			'done' => $this->done,
			'doneAt' => $this->doneAt,
			'doneBy' => $this->doneBy,
			'rrule' => $this->rrule,
			'repeatFromCompletion' => $this->repeatFromCompletion,
			'deleteOnDone' => $this->deleteOnDone,
			'nextDueAt' => $this->nextDueAt,
			'imageFileId' => $this->imageFileId,
			'imageUploadedBy' => $this->imageUploadedBy,
			'addedBy' => $this->addedBy,
			'barcode' => $this->barcode,
			'prices' => $this->prices,
			// Values are attached by the controller after serialization (like
			// prices/storeIds); the entity carries no custom-field state itself.
			'customFields' => [],
			'sortOrder' => $this->sortOrder,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'deletedAt' => $this->deletedAt,
			'archivedAt' => $this->archivedAt,
			'quantityPinned' => $this->isQuantityPinned(),
			'restock' => $this->getRestock(),
		];
	}
}
