<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ShoppingReminderService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Shopping Mode — user-defined reminders. House-scoped per-row REST
 * plus a batch reorder, mirroring {@see StoreController}. Surfacing (which
 * enabled reminders show at which moment) is done client-side over the index.
 *
 * @psalm-import-type PantryShoppingReminder from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class ShoppingReminderController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShoppingReminderService $reminders,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all shopping reminders in a house
	 *
	 * Ordered by position; includes disabled ones (the manager shows all, the
	 * surfacing filters `enabled` client-side).
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryShoppingReminder>, array{}>
	 *
	 * 200: Reminders returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/shopping/reminders')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$all = $this->reminders->listForHouse($houseId);
			return new DataResponse(array_map(static fn ($r) => $r->jsonSerialize(), $all));
		});
	}

	/**
	 * Create a shopping reminder
	 *
	 * @param int $houseId House id.
	 * @param string $text The prompt body.
	 * @param string $showOn When to surface it: `on_start`, `on_store_advance` or `on_close`.
	 * @param bool $enabled Whether the reminder is active.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingReminder, array{}>
	 *
	 * 200: Reminder created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/shopping/reminders')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function create(int $houseId, string $text, string $showOn, bool $enabled = true): DataResponse {
		return $this->runAction(function () use ($houseId, $text, $showOn, $enabled): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$reminder = $this->reminders->create($houseId, $text, $showOn, $enabled);
			return new DataResponse($reminder->jsonSerialize());
		});
	}

	/**
	 * Update a shopping reminder
	 *
	 * @param int $houseId House id.
	 * @param int $reminderId Reminder id.
	 * @param string|null $text New prompt body.
	 * @param string|null $showOn New moment: `on_start`, `on_store_advance` or `on_close`.
	 * @param bool|null $enabled New active state.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryShoppingReminder, array{}>
	 *
	 * 200: Reminder updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/shopping/reminders/{reminderId}', requirements: ['reminderId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function update(int $houseId, int $reminderId, ?string $text = null, ?string $showOn = null, ?bool $enabled = null): DataResponse {
		return $this->runAction(function () use ($houseId, $reminderId, $text, $showOn, $enabled): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->reminders->assertInHouse($reminderId, $houseId);
			$patch = [];
			if ($text !== null) {
				$patch['text'] = $text;
			}
			if ($showOn !== null) {
				$patch['showOn'] = $showOn;
			}
			if ($enabled !== null) {
				$patch['enabled'] = $enabled;
			}
			$updated = $this->reminders->update($reminderId, $patch);
			return new DataResponse($updated->jsonSerialize());
		});
	}

	/**
	 * Reorder a house's shopping reminders
	 *
	 * Batch: takes the ordered reminder ids and assigns positions 0..n-1.
	 *
	 * @param int $houseId House id.
	 * @param list<int> $reminderIds Reminder ids in the desired order.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryShoppingReminder>, array{}>
	 *
	 * 200: Reminders reordered
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/shopping/reminders/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function reorder(int $houseId, array $reminderIds = []): DataResponse {
		return $this->runAction(function () use ($houseId, $reminderIds): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$ids = array_map('intval', $reminderIds);
			$ordered = $this->reminders->reorder($houseId, $ids);
			return new DataResponse(array_map(static fn ($r) => $r->jsonSerialize(), $ordered));
		});
	}

	/**
	 * Delete a shopping reminder
	 *
	 * @param int $houseId House id.
	 * @param int $reminderId Reminder id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Reminder deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/shopping/reminders/{reminderId}', requirements: ['reminderId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission(['canEditLists'])]
	public function destroy(int $houseId, int $reminderId): DataResponse {
		return $this->runAction(function () use ($houseId, $reminderId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->reminders->assertInHouse($reminderId, $houseId);
			$this->reminders->delete($reminderId);
			return new DataResponse(['success' => true]);
		});
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}
}
