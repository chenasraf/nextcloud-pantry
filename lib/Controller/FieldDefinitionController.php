<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\FieldDefinitionService;
use OCA\Pantry\Service\HouseAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryFieldDefinition from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class FieldDefinitionController extends OCSController {
	use TranslatesDomainExceptions;

	/**
	 * The config keys an update may carry beyond name/listId. Presence in the
	 * request (not the value) decides whether each is applied.
	 */
	private const CONFIG_KEYS = [
		'hint', 'multiline', 'defaultText', 'defaultNumber', 'defaultBool',
		'defaultOptionId', 'dateMode', 'defaultOffsetDays', 'notifyDefault',
		'leadDays', 'overridePolicy', 'stopWhenDone', 'options',
	];

	public function __construct(
		string $appName,
		IRequest $request,
		private FieldDefinitionService $fields,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List the custom-field definitions of a house
	 *
	 * Includes both house-wide and list-scoped definitions, each with its
	 * `select` options.
	 *
	 * @param int $houseId House id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryFieldDefinition>, array{}>
	 *
	 * 200: Definitions returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/fields')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId): DataResponse {
		return $this->runAction(function () use ($houseId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			return new DataResponse($this->fields->listForHouse($houseId));
		});
	}

	/**
	 * Create a custom-field definition
	 *
	 * @param int $houseId House id.
	 * @param string $name Field name.
	 * @param string $type One of: text, number, checkbox, date, select.
	 * @param int|null $listId List to scope the field to, or null for house-wide.
	 * @param string|null $hint Placeholder / hint text.
	 * @param bool $multiline Text fields: render as a multi-line area.
	 * @param string|null $defaultText Text default.
	 * @param float|null $defaultNumber Number default.
	 * @param bool $defaultBool Checkbox default.
	 * @param string|null $dateMode Date fields: absolute or relative.
	 * @param int|null $defaultOffsetDays Date fields (relative): default "in N days".
	 * @param bool $notifyDefault Date fields: remind by default.
	 * @param int $leadDays Date fields: days before the date to remind (0 = on the day).
	 * @param string|null $overridePolicy Date fields: field-only or item-override.
	 * @param bool $stopWhenDone Date fields: withdraw the reminder when the item is done.
	 * @param list<array{id?: int, label: string, sortOrder?: int}> $options Select options.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryFieldDefinition, array{}>
	 *
	 * 200: Definition created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/houses/{houseId}/fields')]
	#[NoAdminRequired]
	#[Permission(['canEditFields'])]
	public function create(
		int $houseId,
		string $name,
		string $type,
		?int $listId = null,
		?string $hint = null,
		bool $multiline = false,
		?string $defaultText = null,
		?float $defaultNumber = null,
		bool $defaultBool = false,
		?string $dateMode = null,
		?int $defaultOffsetDays = null,
		bool $notifyDefault = false,
		int $leadDays = 0,
		?string $overridePolicy = null,
		bool $stopWhenDone = false,
		array $options = [],
	): DataResponse {
		return $this->runAction(function () use (
			$houseId, $name, $type, $listId, $hint, $multiline, $defaultText,
			$defaultNumber, $defaultBool, $dateMode, $defaultOffsetDays,
			$notifyDefault, $leadDays, $overridePolicy, $stopWhenDone, $options,
		): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$created = $this->fields->create($houseId, [
				'name' => $name,
				'type' => $type,
				'listId' => $listId,
				'hint' => $hint,
				'multiline' => $multiline,
				'defaultText' => $defaultText,
				'defaultNumber' => $defaultNumber,
				'defaultBool' => $defaultBool,
				'dateMode' => $dateMode,
				'defaultOffsetDays' => $defaultOffsetDays,
				'notifyDefault' => $notifyDefault,
				'leadDays' => $leadDays,
				'overridePolicy' => $overridePolicy,
				'stopWhenDone' => $stopWhenDone,
				'options' => $options,
			]);
			return new DataResponse($created);
		});
	}

	/**
	 * Update a custom-field definition
	 *
	 * Only the fields present in the request are changed. For a `select` field,
	 * an `options` array replaces the option set (existing entries are matched
	 * by id, entries without an id are created, and omitted entries are removed).
	 *
	 * @param int $houseId House id.
	 * @param int $fieldId Field id.
	 * @param string|null $name New name.
	 * @param int|null $listId New list scope, or null for house-wide. Applied only when present.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryFieldDefinition, array{}>
	 *
	 * 200: Definition updated
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/fields/{fieldId}')]
	#[NoAdminRequired]
	#[Permission(['canEditFields'])]
	public function update(int $houseId, int $fieldId, ?string $name = null, ?int $listId = null): DataResponse {
		$params = $this->request->getParams();
		return $this->runAction(function () use ($houseId, $fieldId, $params): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->fields->assertInHouse($fieldId, $houseId);

			$patch = [];
			if (array_key_exists('name', $params)) {
				$patch['name'] = $params['name'];
			}
			if (array_key_exists('listId', $params)) {
				$patch['listId'] = $params['listId'];
			}
			foreach (self::CONFIG_KEYS as $key) {
				if (array_key_exists($key, $params)) {
					$patch[$key] = $params[$key];
				}
			}
			$updated = $this->fields->update($fieldId, $patch);
			return new DataResponse($updated);
		});
	}

	/**
	 * Delete a custom-field definition
	 *
	 * Soft-delete: stored values are kept but hidden.
	 *
	 * @param int $houseId House id.
	 * @param int $fieldId Field id.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Definition deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/fields/{fieldId}')]
	#[NoAdminRequired]
	#[Permission(['canEditFields'])]
	public function destroy(int $houseId, int $fieldId): DataResponse {
		return $this->runAction(function () use ($houseId, $fieldId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->fields->assertInHouse($fieldId, $houseId);
			$this->fields->delete($fieldId);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * Delete a `select` option
	 *
	 * An option with no stored values is removed outright. An option in use
	 * requires an action: `remap` rewrites every affected value to `remapToId`
	 * (another option of the same field), `clear` nulls them. The value rewrite
	 * and the option delete run in one transaction.
	 *
	 * @param int $houseId House id.
	 * @param int $fieldId Field id.
	 * @param int $optionId Option id to delete.
	 * @param string|null $action `remap` or `clear`; required when the option is in use.
	 * @param int|null $remapToId Target option for `remap`.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryFieldDefinition, array{}>
	 *
	 * 200: Option deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/houses/{houseId}/fields/{fieldId}/options/{optionId}')]
	#[NoAdminRequired]
	#[Permission(['canEditFields'])]
	public function deleteOption(int $houseId, int $fieldId, int $optionId, ?string $action = null, ?int $remapToId = null): DataResponse {
		return $this->runAction(function () use ($houseId, $fieldId, $optionId, $action, $remapToId): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->fields->assertInHouse($fieldId, $houseId);
			$updated = $this->fields->deleteOption($fieldId, $optionId, $action, $remapToId);
			return new DataResponse($updated);
		});
	}

	/**
	 * Batch reorder custom-field definitions
	 *
	 * @param int $houseId House id.
	 * @param list<array{id: int, sortOrder: int}> $items Reorder entries.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Definitions reordered
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/houses/{houseId}/fields/reorder')]
	#[NoAdminRequired]
	#[Permission(['canEditFields'])]
	public function reorder(int $houseId, array $items = []): DataResponse {
		return $this->runAction(function () use ($houseId, $items): DataResponse {
			$this->auth->requireMember($houseId, $this->requireUid());
			$this->fields->reorder($houseId, $items);
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
