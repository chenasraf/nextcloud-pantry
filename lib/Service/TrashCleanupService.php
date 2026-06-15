<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\ChecklistItemMapper;
use OCA\Pantry\Db\ChecklistMapper;
use OCA\Pantry\Db\House;
use OCA\Pantry\Db\HouseMapper;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\Photo;
use OCA\Pantry\Db\PhotoMapper;
use Psr\Log\LoggerInterface;

/**
 * Permanently delete soft-deleted rows whose deleted_at is older than the
 * house's retention window. Runs out of {@see \OCA\Pantry\BackgroundJob\PurgeExpiredTrashJob}
 * but is also exposed for occ-style commands and tests.
 */
class TrashCleanupService {
	public const SECONDS_PER_DAY = 86400;

	public function __construct(
		private HouseMapper $houseMapper,
		private ChecklistMapper $listMapper,
		private ChecklistItemMapper $itemMapper,
		private NoteMapper $noteMapper,
		private PhotoMapper $photoMapper,
		private ImageService $images,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Purge expired trash rows across every house. Returns the per-entity
	 * removal counts so callers (jobs, occ) can log a summary.
	 *
	 * @return array{lists: int, items: int, notes: int, photos: int}
	 */
	public function purgeAll(?int $now = null): array {
		$now ??= time();
		$totals = ['lists' => 0, 'items' => 0, 'notes' => 0, 'photos' => 0];
		foreach ($this->houseMapper->findAll() as $house) {
			$counts = $this->purgeHouse($house, $now);
			$totals['lists'] += $counts['lists'];
			$totals['items'] += $counts['items'];
			$totals['notes'] += $counts['notes'];
			$totals['photos'] += $counts['photos'];
		}
		return $totals;
	}

	/**
	 * Purge expired trash for a single house. A retention of 0 means "never
	 * auto-purge" and short-circuits the work.
	 *
	 * @return array{lists: int, items: int, notes: int, photos: int}
	 */
	public function purgeHouse(House $house, ?int $now = null): array {
		$retentionDays = $house->getTrashRetentionDays();
		if ($retentionDays <= 0) {
			return ['lists' => 0, 'items' => 0, 'notes' => 0, 'photos' => 0];
		}
		$now ??= time();
		$cutoff = $now - $retentionDays * self::SECONDS_PER_DAY;
		$houseId = (int)$house->getId();

		// Items first: items inside expired lists will be wiped by the cascading
		// list deletion below, so we only need to purge items that expired on
		// their own (in active lists).
		$items = $this->itemMapper->findExpiredTrashByHouse($houseId, $cutoff);
		foreach ($items as $item) {
			$this->itemMapper->delete($item);
		}

		// Lists: hard-delete the list and any item still attached to it.
		$lists = $this->listMapper->findExpiredTrashByHouse($houseId, $cutoff);
		foreach ($lists as $list) {
			$this->itemMapper->deleteByList((int)$list->getId());
			$this->listMapper->delete($list);
		}

		// Notes.
		$notes = $this->noteMapper->findExpiredTrashByHouse($houseId, $cutoff);
		foreach ($notes as $note) {
			$this->noteMapper->delete($note);
		}

		// Photos: also unlink the underlying file.
		$photos = $this->photoMapper->findExpiredTrashByHouse($houseId, $cutoff);
		foreach ($photos as $photo) {
			$this->safeDeletePhotoFile($photo, $house);
			$this->photoMapper->delete($photo);
		}

		return [
			'lists' => count($lists),
			'items' => count($items),
			'notes' => count($notes),
			'photos' => count($photos),
		];
	}

	/**
	 * Remove the underlying Nextcloud file for a photo. We try the original
	 * uploader first; if they no longer have access we fall back to the house
	 * owner. Best-effort — the row is removed regardless.
	 */
	private function safeDeletePhotoFile(Photo $photo, House $house): void {
		$uids = array_filter([$photo->getUploadedBy(), $house->getOwnerUid()]);
		foreach (array_unique($uids) as $uid) {
			try {
				$this->images->deleteFile($photo->getFileId(), $uid);
				return;
			} catch (\Throwable $e) {
				$this->logger->debug('Pantry: trash purge could not delete file {file} as {uid}: {msg}', [
					'file' => $photo->getFileId(),
					'uid' => $uid,
					'msg' => $e->getMessage(),
				]);
			}
		}
	}
}
