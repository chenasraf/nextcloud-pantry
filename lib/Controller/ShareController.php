<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\Share;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\ShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type PantryShare from ResponseDefinitions
 * @psalm-import-type PantrySuccess from ResponseDefinitions
 */
final class ShareController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShareService $shares,
		private HouseAuthService $auth,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List the per-user shares on an entity
	 *
	 * @param int $houseId House id.
	 * @param string $entityType Entity type (checklist, note, photo, photos-folder).
	 * @param int $entityId Entity id.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<PantryShare>, array{}>
	 *
	 * 200: Shares returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/houses/{houseId}/shares/{entityType}/{entityId}', requirements: ['entityType' => 'checklist|note|photo|photos-folder', 'entityId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission]
	public function index(int $houseId, string $entityType, int $entityId): DataResponse {
		return $this->runAction(function () use ($houseId, $entityType, $entityId): DataResponse {
			$type = $this->assertType($entityType);
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			if (!$this->shares->canView($houseId, $uid, $type, $entityId)) {
				throw new ForbiddenException('No access to this item');
			}
			$shares = $this->shares->listForEntity($type, $entityId);
			return new DataResponse(array_map(fn ($s) => $s->jsonSerialize(), $shares));
		});
	}

	/**
	 * Replace the full set of shares on an entity
	 *
	 * Only the entity's creator or a house admin may manage shares, and every
	 * recipient must be a member of the house.
	 *
	 * @param int $houseId House id.
	 * @param string $entityType Entity type (checklist, note, photo, photos-folder).
	 * @param int $entityId Entity id.
	 * @param list<array{uid: string, permission: string}> $shares Recipients and their permission ('view' or 'edit').
	 *
	 * @return DataResponse<Http::STATUS_OK, PantrySuccess, array{}>
	 *
	 * 200: Shares updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/houses/{houseId}/shares/{entityType}/{entityId}', requirements: ['entityType' => 'checklist|note|photo|photos-folder', 'entityId' => '\d+'])]
	#[NoAdminRequired]
	#[Permission]
	public function setShares(int $houseId, string $entityType, int $entityId, array $shares = []): DataResponse {
		return $this->runAction(function () use ($houseId, $entityType, $entityId, $shares): DataResponse {
			$type = $this->assertType($entityType);
			$uid = $this->requireUid();
			$this->auth->requireMember($houseId, $uid);
			$entries = array_map(
				fn ($s) => [
					'uid' => (string)($s['uid'] ?? ''),
					'permission' => (string)($s['permission'] ?? Share::PERM_VIEW),
				],
				$shares,
			);
			$this->shares->setSharesForEntity($houseId, $uid, $type, $entityId, $entries);
			return new DataResponse(['success' => true]);
		});
	}

	/**
	 * @return Share::TYPE_*
	 */
	private function assertType(string $entityType): string {
		if (!in_array($entityType, Share::TYPES, true)) {
			throw new NotFoundException('Unknown entity type');
		}
		return $entityType;
	}

	private function requireUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not authenticated');
		}
		return $user->getUID();
	}
}
