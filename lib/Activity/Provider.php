<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Activity;

use OCA\Pantry\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	public function __construct(
		private IFactory $languageFactory,
		private IURLGenerator $url,
		private IUserManager $userManager,
		private IManager $activityManager,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw $this->unknownActivity();
		}

		$l = $this->languageFactory->get(Application::APP_ID, $language);
		$params = $event->getSubjectParameters();
		$actorUid = (string)($params['author'] ?? '');
		$isSelf = $actorUid !== '' && $actorUid === $this->activityManager->getCurrentUserId();

		$event->setIcon($this->url->getAbsoluteURL(
			$this->url->imagePath(Application::APP_ID, 'app-dark.svg')
		));

		$rich = $this->buildRichSubject($event->getSubject(), $params, $isSelf, $l);
		if ($rich === null) {
			throw $this->unknownActivity();
		}
		[$template, $richParams] = $rich;

		$event->setRichSubject($template, $richParams);
		$event->setParsedSubject($this->flatten($template, $richParams));

		return $event;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array{0: string, 1: array<string, array<string, string>>}|null
	 */
	private function buildRichSubject(string $subject, array $params, bool $isSelf, IL10N $l): ?array {
		$author = $this->userParam((string)($params['author'] ?? ''));
		$house = $this->highlight((string)($params['houseId'] ?? ''), (string)($params['houseName'] ?? ''));

		switch ($subject) {
			// ----- Checklists -----

			case ActivityPublisher::SUBJECT_LIST_CREATED:
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You created list {list} in {house}')
						: $l->t('{author} created list {list} in {house}'),
					$isSelf ? compact('list', 'house') : compact('author', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_LIST_UPDATED:
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You updated list {list} in {house}')
						: $l->t('{author} updated list {list} in {house}'),
					$isSelf ? compact('list', 'house') : compact('author', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_LIST_DELETED:
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You deleted list {list} in {house}')
						: $l->t('{author} deleted list {list} in {house}'),
					$isSelf ? compact('list', 'house') : compact('author', 'list', 'house'),
				];

				// ----- Items -----

			case ActivityPublisher::SUBJECT_ITEM_ADDED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You added {item} to {list} in {house}')
						: $l->t('{author} added {item} to {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_UPDATED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You updated {item} on {list} in {house}')
						: $l->t('{author} updated {item} on {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_DONE:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You completed {item} on {list} in {house}')
						: $l->t('{author} completed {item} on {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_REOPENED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You reopened {item} on {list} in {house}')
						: $l->t('{author} reopened {item} on {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_MOVED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$from = $this->highlight((string)($params['fromListId'] ?? ''), (string)($params['fromListName'] ?? ''));
				$to = $this->highlight((string)($params['toListId'] ?? ''), (string)($params['toListName'] ?? ''));
				return [
					$isSelf
						? $l->t('You moved {item} from {from} to {to} in {house}')
						: $l->t('{author} moved {item} from {from} to {to} in {house}'),
					$isSelf ? compact('item', 'from', 'to', 'house') : compact('author', 'item', 'from', 'to', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_DELETED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You deleted {item} from {list} in {house}')
						: $l->t('{author} deleted {item} from {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEMS_RECURRED:
				$names = array_values(array_filter(
					(array)($params['itemNames'] ?? []),
					fn ($v) => is_string($v),
				));
				$count = (int)($params['itemCount'] ?? count($names));
				$label = $count <= 3 && !empty($names)
					? implode(', ', $names)
					: $l->n('%n item', '%n items', $count);
				$items = $this->highlight('items', $label);
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$l->t('{items} due again on {list} in {house}'),
					compact('items', 'list', 'house'),
				];

			case ActivityPublisher::SUBJECT_ITEM_RESTORED:
				$item = $this->highlight('item', (string)($params['itemName'] ?? ''));
				$list = $this->highlight((string)($params['listId'] ?? ''), (string)($params['listName'] ?? ''));
				return [
					$isSelf
						? $l->t('You restored {item} on {list} in {house}')
						: $l->t('{author} restored {item} on {list} in {house}'),
					$isSelf ? compact('item', 'list', 'house') : compact('author', 'item', 'list', 'house'),
				];

				// ----- Photos -----

			case ActivityPublisher::SUBJECT_PHOTO_UPLOADED:
				$folderName = (string)($params['folderName'] ?? '');
				if ($folderName !== '') {
					$folder = $this->highlight((string)($params['folderId'] ?? ''), $folderName);
					return [
						$isSelf
							? $l->t('You uploaded a photo to {folder} in {house}')
							: $l->t('{author} uploaded a photo to {folder} in {house}'),
						$isSelf ? compact('folder', 'house') : compact('author', 'folder', 'house'),
					];
				}
				return [
					$isSelf
						? $l->t('You uploaded a photo in {house}')
						: $l->t('{author} uploaded a photo in {house}'),
					$isSelf ? compact('house') : compact('author', 'house'),
				];

			case ActivityPublisher::SUBJECT_PHOTO_MOVED:
				$fromName = (string)($params['fromFolderName'] ?? '');
				$toName = (string)($params['toFolderName'] ?? '');
				$from = $this->highlight((string)($params['fromFolderId'] ?? 'root'), $fromName !== '' ? $fromName : $l->t('the board'));
				$to = $this->highlight((string)($params['toFolderId'] ?? 'root'), $toName !== '' ? $toName : $l->t('the board'));
				return [
					$isSelf
						? $l->t('You moved a photo from {from} to {to} in {house}')
						: $l->t('{author} moved a photo from {from} to {to} in {house}'),
					$isSelf ? compact('from', 'to', 'house') : compact('author', 'from', 'to', 'house'),
				];

			case ActivityPublisher::SUBJECT_PHOTO_DELETED:
				$folderName = (string)($params['folderName'] ?? '');
				if ($folderName !== '') {
					$folder = $this->highlight((string)($params['folderId'] ?? ''), $folderName);
					return [
						$isSelf
							? $l->t('You deleted a photo from {folder} in {house}')
							: $l->t('{author} deleted a photo from {folder} in {house}'),
						$isSelf ? compact('folder', 'house') : compact('author', 'folder', 'house'),
					];
				}
				return [
					$isSelf
						? $l->t('You deleted a photo in {house}')
						: $l->t('{author} deleted a photo in {house}'),
					$isSelf ? compact('house') : compact('author', 'house'),
				];

				// ----- Photo folders -----

			case ActivityPublisher::SUBJECT_FOLDER_CREATED:
				$folder = $this->highlight((string)($params['folderId'] ?? ''), (string)($params['folderName'] ?? ''));
				return [
					$isSelf
						? $l->t('You created photo folder {folder} in {house}')
						: $l->t('{author} created photo folder {folder} in {house}'),
					$isSelf ? compact('folder', 'house') : compact('author', 'folder', 'house'),
				];

			case ActivityPublisher::SUBJECT_FOLDER_RENAMED:
				$old = $this->highlight((string)($params['folderId'] ?? '') . ':old', (string)($params['oldName'] ?? ''));
				$folder = $this->highlight((string)($params['folderId'] ?? ''), (string)($params['folderName'] ?? ''));
				return [
					$isSelf
						? $l->t('You renamed photo folder {old} to {folder} in {house}')
						: $l->t('{author} renamed photo folder {old} to {folder} in {house}'),
					$isSelf ? compact('old', 'folder', 'house') : compact('author', 'old', 'folder', 'house'),
				];

			case ActivityPublisher::SUBJECT_FOLDER_DELETED:
				$folder = $this->highlight((string)($params['folderId'] ?? ''), (string)($params['folderName'] ?? ''));
				return [
					$isSelf
						? $l->t('You deleted photo folder {folder} in {house}')
						: $l->t('{author} deleted photo folder {folder} in {house}'),
					$isSelf ? compact('folder', 'house') : compact('author', 'folder', 'house'),
				];

				// ----- Notes -----

			case ActivityPublisher::SUBJECT_NOTE_CREATED:
				$note = $this->highlight((string)($params['noteId'] ?? ''), (string)($params['noteTitle'] ?? ''));
				return [
					$isSelf
						? $l->t('You added note {note} in {house}')
						: $l->t('{author} added note {note} in {house}'),
					$isSelf ? compact('note', 'house') : compact('author', 'note', 'house'),
				];

			case ActivityPublisher::SUBJECT_NOTE_EDITED:
				$note = $this->highlight((string)($params['noteId'] ?? ''), (string)($params['noteTitle'] ?? ''));
				return [
					$isSelf
						? $l->t('You edited note {note} in {house}')
						: $l->t('{author} edited note {note} in {house}'),
					$isSelf ? compact('note', 'house') : compact('author', 'note', 'house'),
				];

			case ActivityPublisher::SUBJECT_NOTE_DELETED:
				$note = $this->highlight((string)($params['noteId'] ?? ''), (string)($params['noteTitle'] ?? ''));
				return [
					$isSelf
						? $l->t('You deleted note {note} in {house}')
						: $l->t('{author} deleted note {note} in {house}'),
					$isSelf ? compact('note', 'house') : compact('author', 'note', 'house'),
				];
		}

		return null;
	}

	private function unknownActivity(): \InvalidArgumentException {
		if (class_exists(UnknownActivityException::class)) {
			return new UnknownActivityException();
		}
		return new \InvalidArgumentException();
	}

	/**
	 * @return array<string, string>
	 */
	private function userParam(string $uid): array {
		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->userManager->getDisplayName($uid) ?? $uid,
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function highlight(string $id, string $name): array {
		return [
			'type' => 'highlight',
			'id' => $id,
			'name' => $name,
		];
	}

	/**
	 * @param array<string, array<string, string>> $params
	 */
	private function flatten(string $template, array $params): string {
		$placeholders = [];
		$replacements = [];
		foreach ($params as $key => $param) {
			$placeholders[] = '{' . $key . '}';
			$replacements[] = $param['name'] ?? '';
		}
		return str_replace($placeholders, $replacements, $template);
	}
}
