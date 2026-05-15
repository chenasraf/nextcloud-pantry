<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Latch;

use Latch\Integration\Nextcloud\LatchBootstrap;
use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Latch\Payload\HouseListContext;
use OCA\Pantry\Latch\Payload\HouseMemberListContext;
use OCA\Pantry\Latch\Payload\HouseSummary;
use OCA\Pantry\Latch\Payload\MemberSummary;
use OCA\Pantry\Service\HouseService;
use OCP\IUserManager;

/**
 * The only handler-side code Pantry ships in v1.
 *
 * Latch is asymmetric — only the source owner can `collectFromHandlers`.
 * So for the read-style "ask Pantry for households" capability to work,
 * Pantry itself registers default handlers on its own `house.list` and
 * `house.member.list` collect points. External apps can still attach
 * additional handlers (e.g. a federated-households bridge) and Pantry's
 * handlers will merge with theirs.
 *
 * Depending on PantrySource (not declaring our own source) guarantees the
 * source is registered before these handlers attach.
 */
class PantryProvider {
	public const HANDLER_NAME = 'pantry-self';

	public function __construct(
		PantrySource $source,
		private HouseService $houseService,
		private HouseMemberMapper $memberMapper,
		private IUserManager $userManager,
	) {
		// `$source` is intentionally a constructor dependency we don't reference
		// after construction — it exists to force source registration before we
		// attach handlers below. Without it the registry would throw
		// SourceNotFoundException.
		unset($source);

		$handler = LatchBootstrap::registry()->registerHandler(self::HANDLER_NAME);

		$handler->hook(HookPoints::SOURCE, HookPoints::COLLECT_HOUSE_LIST)
			->handle(fn (HouseListContext $ctx) => $this->buildHouseList($ctx));

		$handler->hook(HookPoints::SOURCE, HookPoints::COLLECT_HOUSE_MEMBER_LIST)
			->handle(fn (HouseMemberListContext $ctx) => $this->buildMemberList($ctx));
	}

	/**
	 * @return list<HouseSummary>
	 */
	private function buildHouseList(HouseListContext $ctx): array {
		$houses = $this->houseService->listForUser($ctx->userId);
		$out = [];
		foreach ($houses as $house) {
			$houseId = (int)$house->getId();
			$role = $this->memberMapper->findForUserAndHouse($ctx->userId, $houseId);
			$out[] = new HouseSummary(
				$houseId,
				$house->getName(),
				$house->getDescription(),
				$house->getOwnerUid(),
				$role?->getRole() ?? HouseMember::ROLE_MEMBER,
			);
		}
		return $out;
	}

	/**
	 * @return list<MemberSummary>
	 */
	private function buildMemberList(HouseMemberListContext $ctx): array {
		// Only members of the house may enumerate other members.
		if ($this->memberMapper->findForUserAndHouse($ctx->viewerUid, $ctx->houseId) === null) {
			return [];
		}
		$members = $this->houseService->listMembers($ctx->houseId);
		$out = [];
		foreach ($members as $m) {
			$user = $this->userManager->get($m->getUserId());
			$display = $user !== null ? $user->getDisplayName() : $m->getUserId();
			$out[] = new MemberSummary(
				(int)$m->getId(),
				$m->getHouseId(),
				$m->getUserId(),
				$display,
				$m->getRole(),
			);
		}
		return $out;
	}
}
