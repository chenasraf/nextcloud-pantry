<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Controller;

use OCA\NextcloudAppTemplate\AppInfo;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;

final class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $config,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * GET /api/hello
	 *
	 * Returns a simple hello message and the last time the server said hello.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string, at: string|null}, array{}>
	 *
	 * 200: Data returned successfully.
	 */
	#[ApiRoute(verb: 'GET', url: '/api/hello')]
	#[NoAdminRequired]
	public function getHello(): DataResponse {
		$lastAt = $this->config->getValueString(AppInfo\Application::APP_ID, 'last_hello_at', '');
		$at = $lastAt !== '' ? $lastAt : null;

		$message = (string)$this->l10n->t('👋 Hello from server!');

		return new DataResponse([
			'message' => $message,
			'at' => $at,
		]);
	}

	/**
	 * POST /api/hello
	 *
	 * Accepts example payload and returns a message + timestamp.
	 *
	 * @param array{
	 *   name?: string,
	 *   theme?: string,
	 *   items?: list<string>,
	 *   counter?: int
	 * } $data Request payload for creating a hello message.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string, at: string}, array{}>
	 *
	 * 200: Data returned successfully.
	 */
	#[ApiRoute(verb: 'POST', url: '/api/hello')]
	#[NoAdminRequired]
	public function postHello(mixed $data = []): DataResponse {
		// Normalize incoming payload (be permissive for the example)
		$name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
		$theme = isset($data['theme']) && is_string($data['theme']) ? $data['theme'] : null;
		$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
		$counter = isset($data['counter']) && is_int($data['counter']) ? $data['counter'] : 0;

		// Build a friendly message (localized)
		$who = $name !== '' ? $name : (string)$this->l10n->t('there');
		$message = (string)$this->l10n->t('Hello, %s!', [$who]);

		// Optionally include a tiny summary (kept simple for the example)
		if ($theme !== null) {
			$message .= ' ' . (string)$this->l10n->t('Theme: %s.', [$theme]);
		}
		if (!empty($items)) {
			$message .= ' ' . (string)$this->l10n->t('Items: %d.', [count($items)]);
		}
		if ($counter !== 0) {
			$message .= ' ' . (string)$this->l10n->t('Counter: %d.', [$counter]);
		}

		// Stamp "now" and persist as the last hello time
		$now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DATE_ATOM);
		$this->config->setValueString(AppInfo\Application::APP_ID, 'last_hello_at', $now);

		return new DataResponse([
			'message' => $message,
			'at' => $now,
		]);
	}
}
