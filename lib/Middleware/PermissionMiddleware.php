<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Middleware;

use OCA\Pantry\Db\Share;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Permission\Permission;
use OCA\Pantry\Service\HouseAuthService;
use OCA\Pantry\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Enforces #[Permission] attributes on controller actions before they run.
 *
 * Resolution order: authenticated → house member → admin short-circuit →
 * required capabilities → per-list access (when a {listId} is in the route).
 */
class PermissionMiddleware extends Middleware {
	public function __construct(
		private IRequest $request,
		private IUserSession $userSession,
		private HouseAuthService $auth,
		private PermissionService $permissions,
	) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		$attr = $this->readAttribute($controller, $methodName);
		if ($attr === null) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('Not authenticated');
		}
		$uid = $user->getUID();

		$houseId = (int)$this->request->getParam('houseId', 0);
		if ($houseId <= 0) {
			throw new OCSForbiddenException('Missing house context');
		}

		try {
			$this->auth->requireMember($houseId, $uid);
		} catch (ForbiddenException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		}

		// Admins bypass every capability and list-access check.
		if ($this->permissions->isAdmin($houseId, $uid)) {
			return;
		}

		if ($attr->admin) {
			throw new OCSForbiddenException('Admin privileges required');
		}

		foreach ($attr->caps as $cap) {
			if (!$this->permissions->can($houseId, $uid, $cap)) {
				throw new OCSForbiddenException('Missing permission: ' . $cap);
			}
		}

		$listId = (int)$this->request->getParam('listId', 0);
		if ($listId > 0) {
			$this->enforceListAccess($houseId, $uid, $listId, $attr->caps);
		}
	}

	/**
	 * Enforce per-list access. Role-based access keeps its full capabilities
	 * (already checked above). A user reaching the list only through a per-user
	 * share is bounded by the share level: a viewer share allows read-only
	 * actions, an editor share allows item edits, and neither grants deleting
	 * the list itself.
	 *
	 * @param string[] $caps
	 */
	private function enforceListAccess(int $houseId, string $uid, int $listId, array $caps): void {
		if ($this->permissions->hasRoleAccessToList($houseId, $uid, $listId)) {
			return;
		}
		$shareLevel = $this->permissions->listShareLevel($uid, $listId);
		if ($shareLevel === null) {
			throw new OCSForbiddenException('No access to this list');
		}
		$needed = $this->requiredListLevel($caps);
		if ($needed === 'listdelete') {
			throw new OCSForbiddenException('Sharing does not grant deleting this list');
		}
		if ($needed === Share::PERM_EDIT && $shareLevel !== Share::PERM_EDIT) {
			throw new OCSForbiddenException('This list is shared with you as view-only');
		}
	}

	/**
	 * Classify the strongest action the required capabilities represent for a
	 * shared checklist: 'listdelete' (never granted by a share), edit, or view.
	 *
	 * @param string[] $caps
	 * @return 'listdelete'|Share::PERM_EDIT|Share::PERM_VIEW
	 */
	private function requiredListLevel(array $caps): string {
		$editCaps = ['canEditLists', 'canAddItems', 'canCheckItems', 'canCopyItems', 'canMoveItems', 'canDeleteItems'];
		$level = Share::PERM_VIEW;
		foreach ($caps as $cap) {
			if ($cap === 'canDeleteLists') {
				return 'listdelete';
			}
			if (in_array($cap, $editCaps, true)) {
				$level = Share::PERM_EDIT;
			}
		}
		return $level;
	}

	private function readAttribute(Controller $controller, string $methodName): ?Permission {
		try {
			$ref = new \ReflectionMethod($controller, $methodName);
		} catch (\ReflectionException) {
			return null;
		}
		$attrs = $ref->getAttributes(Permission::class);
		if ($attrs === []) {
			return null;
		}
		return $attrs[0]->newInstance();
	}
}
