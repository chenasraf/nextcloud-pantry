<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Exception\ForbiddenException;

class HouseAuthService {
	public function __construct(
		private HouseMemberMapper $memberMapper,
	) {
	}

	public function requireMember(int $houseId, string $uid): HouseMember {
		$member = $this->memberMapper->findForUserAndHouse($uid, $houseId);
		if ($member === null) {
			throw new ForbiddenException('Not a member of this house');
		}
		return $member;
	}

	public function requireAdmin(int $houseId, string $uid): HouseMember {
		$member = $this->requireMember($houseId, $uid);
		if (!$member->isAtLeastAdmin()) {
			throw new ForbiddenException('Admin privileges required');
		}
		return $member;
	}

	public function requireOwner(int $houseId, string $uid): HouseMember {
		$member = $this->requireMember($houseId, $uid);
		if (!$member->isOwner()) {
			throw new ForbiddenException('Owner privileges required');
		}
		return $member;
	}
}
