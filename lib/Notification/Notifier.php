<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Notification;

use OCA\Pantry\AppInfo\Application;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Pantry');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Unknown notification');
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();

		$count = (int)($params['count'] ?? 1);
		$userParam = [
			'type' => 'user',
			'id' => $params['userId'] ?? '',
			'name' => $params['userDisplayName'] ?? '',
		];
		$houseParam = [
			'type' => 'highlight',
			'id' => (string)($params['houseId'] ?? ''),
			'name' => $params['houseName'] ?? '',
		];

		switch ($notification->getSubject()) {
			case 'photo_uploaded':
				if ($count <= 1) {
					$notification->setRichSubject(
						$l->t('{user} uploaded a photo in {house}'),
						['user' => $userParam, 'house' => $houseParam],
					);
				} else {
					$notification->setRichSubject(
						$l->n(
							'{user} uploaded %n photo in {house}',
							'{user} uploaded %n photos in {house}',
							$count,
						),
						['user' => $userParam, 'house' => $houseParam],
					);
				}
				break;

			case 'note_created':
				if ($count <= 1) {
					$notification->setRichSubject(
						$l->t('{user} added a note "{title}" in {house}'),
						[
							'user' => $userParam,
							'title' => [
								'type' => 'highlight',
								'id' => (string)($params['noteId'] ?? ''),
								'name' => $params['noteTitle'] ?? '',
							],
							'house' => $houseParam,
						],
					);
				} else {
					$notification->setRichSubject(
						$l->n(
							'{user} added %n note in {house}',
							'{user} added %n notes in {house}',
							$count,
						),
						['user' => $userParam, 'house' => $houseParam],
					);
				}
				break;

			case 'note_edited':
				if ($count <= 1) {
					$notification->setRichSubject(
						$l->t('{user} edited the note "{title}" in {house}'),
						[
							'user' => $userParam,
							'title' => [
								'type' => 'highlight',
								'id' => (string)($params['noteId'] ?? ''),
								'name' => $params['noteTitle'] ?? '',
							],
							'house' => $houseParam,
						],
					);
				} else {
					$notification->setRichSubject(
						$l->n(
							'{user} edited %n note in {house}',
							'{user} edited %n notes in {house}',
							$count,
						),
						['user' => $userParam, 'house' => $houseParam],
					);
				}
				break;

			case 'item_added':
				$listParam = [
					'type' => 'highlight',
					'id' => 'list',
					'name' => $params['listName'] ?? '',
				];
				if ($count <= 1) {
					$notification->setRichSubject(
						$l->t('{user} added "{item}" to {list} in {house}'),
						[
							'user' => $userParam,
							'item' => [
								'type' => 'highlight',
								'id' => 'item',
								'name' => $params['itemName'] ?? '',
							],
							'list' => $listParam,
							'house' => $houseParam,
						],
					);
				} else {
					$notification->setRichSubject(
						$l->n(
							'{user} added %n item to {list} in {house}',
							'{user} added %n items to {list} in {house}',
							$count,
						),
						[
							'user' => $userParam,
							'list' => $listParam,
							'house' => $houseParam,
						],
					);
				}
				break;

			case 'item_done':
				$listParam = [
					'type' => 'highlight',
					'id' => 'list',
					'name' => $params['listName'] ?? '',
				];
				if ($count <= 1) {
					$notification->setRichSubject(
						$l->t('{user} completed "{item}" on {list} in {house}'),
						[
							'user' => $userParam,
							'item' => [
								'type' => 'highlight',
								'id' => 'item',
								'name' => $params['itemName'] ?? '',
							],
							'list' => $listParam,
							'house' => $houseParam,
						],
					);
				} else {
					$notification->setRichSubject(
						$l->n(
							'{user} completed %n item on {list} in {house}',
							'{user} completed %n items on {list} in {house}',
							$count,
						),
						[
							'user' => $userParam,
							'list' => $listParam,
							'house' => $houseParam,
						],
					);
				}
				break;

			case 'item_reminder':
				$names = $params['itemNames'] ?? [];
				$count = (int)($params['itemCount'] ?? count($names));
				$reminderLabel = $count <= 3
					? implode(', ', $names)
					: $l->n('%n item', '%n items', $count);
				$notification->setRichSubject(
					$l->t('{items} still undone on {list} in {house}'),
					[
						'items' => [
							'type' => 'highlight',
							'id' => 'items',
							'name' => $reminderLabel,
						],
						'list' => [
							'type' => 'highlight',
							'id' => 'list',
							'name' => $params['listName'] ?? '',
						],
						'house' => [
							'type' => 'highlight',
							'id' => (string)($params['houseId'] ?? ''),
							'name' => $params['houseName'] ?? '',
						],
					]
				);
				break;

			case 'item_recurred':
				$names = $params['itemNames'] ?? [];
				$count = (int)($params['itemCount'] ?? count($names));
				$itemLabel = $count <= 3
					? implode(', ', $names)
					: $l->n('%n item', '%n items', $count);
				$notification->setRichSubject(
					$l->t('{items} back on {list} in {house}'),
					[
						'items' => [
							'type' => 'highlight',
							'id' => 'items',
							'name' => $itemLabel,
						],
						'list' => [
							'type' => 'highlight',
							'id' => 'list',
							'name' => $params['listName'] ?? '',
						],
						'house' => [
							'type' => 'highlight',
							'id' => (string)($params['houseId'] ?? ''),
							'name' => $params['houseName'] ?? '',
						],
					]
				);
				break;

			default:
				throw new \InvalidArgumentException('Unknown notification');
		}

		$this->setParsedSubjectFromRichSubject($notification);
		return $notification;
	}

	private function setParsedSubjectFromRichSubject(INotification $notification): void {
		$placeholders = $replacements = [];
		foreach ($notification->getRichSubjectParameters() as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'] ?? '';
		}
		$notification->setParsedSubject(str_replace($placeholders, $replacements, $notification->getRichSubject()));
	}
}
