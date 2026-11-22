<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ItemMemoryService;
use OCA\Pantry\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryItemMemory from ResponseDefinitions
 * @psalm-api
 */
final class ItemMemoryController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ItemMemoryService $memory,
		private HouseAuthService $auth,
		private PermissionService $permissions,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the house's item memory
	 *
	 * The category and amount the household picked for each item name, for
	 * suggesting them when an item is typed in. Names are keyed by their folded
	 * form. A category's score is its uses minus its rejections; suggest the
	 * highest above zero among the categories usable on the target list, the
	 * most recently changed on a tie. Amounts are per list and only cover lists
	 * the caller can open.
	 *
	 * Without `since` every row is returned. With it, only rows changed at or
	 * after that time, including amounts forgotten since (a null quantity).
	 * Rows of deleted categories and lists are not reported: drop any row whose
	 * category or list is no longer known.
	 *
	 * @param int $houseId House id.
	 * @param int|null $since The `serverTime` of the previous sync.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryItemMemory, array{}>
	 *
	 * 200: Item memory returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/item-memory')]
	#[NoAdminRequired]
	#[Permission(['canViewLists'])]
	public function index(int $houseId, ?int $since = null): DataResponse {
		return $this->runAction(function () use ($houseId, $since): DataResponse {
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$access = [];
			$canAccessList = function (int $listId) use ($houseId, $uid, &$access): bool {
				return $access[$listId] ??= $this->permissions->canAccessList($houseId, $uid, $listId);
			};
			return new DataResponse($this->memory->snapshot($houseId, $since, $canAccessList));
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
