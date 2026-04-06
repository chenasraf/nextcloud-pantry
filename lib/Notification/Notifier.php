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

		switch ($notification->getSubject()) {
			case 'photo_uploaded':
				$notification->setRichSubject(
					$l->t('{user} uploaded a photo in {house}'),
					[
						'user' => [
							'type' => 'user',
							'id' => $params['userId'] ?? '',
							'name' => $params['userDisplayName'] ?? '',
						],
						'house' => [
							'type' => 'highlight',
							'id' => (string)($params['houseId'] ?? ''),
							'name' => $params['houseName'] ?? '',
						],
					]
				);
				break;

			case 'note_created':
				$notification->setRichSubject(
					$l->t('{user} added a note "{title}" in {house}'),
					[
						'user' => [
							'type' => 'user',
							'id' => $params['userId'] ?? '',
							'name' => $params['userDisplayName'] ?? '',
						],
						'title' => [
							'type' => 'highlight',
							'id' => (string)($params['noteId'] ?? ''),
							'name' => $params['noteTitle'] ?? '',
						],
						'house' => [
							'type' => 'highlight',
							'id' => (string)($params['houseId'] ?? ''),
							'name' => $params['houseName'] ?? '',
						],
					]
				);
				break;

			case 'note_edited':
				$notification->setRichSubject(
					$l->t('{user} edited the note "{title}" in {house}'),
					[
						'user' => [
							'type' => 'user',
							'id' => $params['userId'] ?? '',
							'name' => $params['userDisplayName'] ?? '',
						],
						'title' => [
							'type' => 'highlight',
							'id' => (string)($params['noteId'] ?? ''),
							'name' => $params['noteTitle'] ?? '',
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
