<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\HouseMember;
use OCA\Pantry\Db\HouseMemberMapper;
use OCA\Pantry\Db\Note;
use OCA\Pantry\Db\NoteMapper;
use OCA\Pantry\Db\Photo;
use OCA\Pantry\Db\PhotoMapper;
use OCA\Pantry\Db\Share;
use OCA\Pantry\Db\ShareMapper;
use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Service\PermissionService;
use OCA\Pantry\Service\ShareService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShareServiceTest extends TestCase {
	/** @var ShareMapper&MockObject */
	private ShareMapper $shareMapper;
	/** @var PermissionService&MockObject */
	private PermissionService $permissions;
	/** @var PhotoMapper&MockObject */
	private PhotoMapper $photoMapper;
	/** @var NoteMapper&MockObject */
	private NoteMapper $noteMapper;
	/** @var HouseMemberMapper&MockObject */
	private HouseMemberMapper $memberMapper;
	private ShareService $svc;

	protected function setUp(): void {
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->photoMapper = $this->createMock(PhotoMapper::class);
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->memberMapper = $this->createMock(HouseMemberMapper::class);
		$this->svc = new ShareService(
			$this->shareMapper,
			$this->permissions,
			$this->photoMapper,
			$this->noteMapper,
			$this->memberMapper,
		);
	}

	private function share(string $permission): Share {
		$s = new Share();
		$s->setPermission($permission);
		return $s;
	}

	public function testRoleLevelWinsOverLowerShare(): void {
		// Role grants edit; a viewer share should not downgrade it.
		$this->permissions->method('roleLevel')->willReturn(PermissionService::LEVEL_EDIT);
		$this->shareMapper->method('findForUserAndEntity')->willReturn($this->share(Share::PERM_VIEW));

		$this->assertTrue($this->svc->canEdit(1, 'alice', Share::TYPE_NOTE, 5));
	}

	public function testShareUpgradesOverLowerRole(): void {
		// Role grants only view; an editor share upgrades to edit.
		$this->permissions->method('roleLevel')->willReturn(PermissionService::LEVEL_VIEW);
		$this->shareMapper->method('findForUserAndEntity')->willReturn($this->share(Share::PERM_EDIT));

		$this->assertTrue($this->svc->canEdit(1, 'alice', Share::TYPE_NOTE, 5));
		$this->assertTrue($this->svc->hasEditShare('alice', Share::TYPE_NOTE, 5));
	}

	public function testNoRoleNoShareGivesNoAccess(): void {
		$this->permissions->method('roleLevel')->willReturn(PermissionService::LEVEL_NONE);
		$this->shareMapper->method('findForUserAndEntity')->willReturn(null);

		$this->assertFalse($this->svc->canView(1, 'alice', Share::TYPE_NOTE, 5));
	}

	public function testPhotoInheritsFolderShare(): void {
		$this->permissions->method('roleLevel')->willReturn(PermissionService::LEVEL_NONE);
		$photo = new Photo();
		$photo->setFolderId(9);
		$this->photoMapper->method('findById')->willReturn($photo);
		// No direct share on the photo, but an editor share on its folder.
		$this->shareMapper->method('findForUserAndEntity')->willReturnCallback(
			function (string $uid, string $type, int $id): ?Share {
				if ($type === Share::TYPE_PHOTO_FOLDER && $id === 9) {
					return $this->share(Share::PERM_EDIT);
				}
				return null;
			},
		);

		$this->assertTrue($this->svc->canEdit(1, 'alice', Share::TYPE_PHOTO, 3));
		$this->assertTrue($this->svc->hasEditShare('alice', Share::TYPE_PHOTO, 3));
	}

	public function testCanManageSharesForNoteCreator(): void {
		$this->permissions->method('isAdmin')->willReturn(false);
		$note = new Note();
		$note->setCreatedBy('alice');
		$this->noteMapper->method('findById')->willReturn($note);

		$this->assertTrue($this->svc->canManageShares(1, 'alice', Share::TYPE_NOTE, 5));
		$this->assertFalse($this->svc->canManageShares(1, 'bob', Share::TYPE_NOTE, 5));
	}

	public function testChecklistSharesAreAdminOnly(): void {
		$this->permissions->method('isAdmin')->willReturnCallback(fn ($h, $uid) => $uid === 'admin');

		$this->assertTrue($this->svc->canManageShares(1, 'admin', Share::TYPE_CHECKLIST, 5));
		$this->assertFalse($this->svc->canManageShares(1, 'alice', Share::TYPE_CHECKLIST, 5));
	}

	public function testSetSharesRejectsNonMember(): void {
		$this->permissions->method('isAdmin')->willReturn(true);
		$this->memberMapper->method('findForUserAndHouse')->willReturn(null);

		$this->expectException(\InvalidArgumentException::class);
		$this->svc->setSharesForEntity(1, 'admin', Share::TYPE_NOTE, 5, [
			['uid' => 'stranger', 'permission' => Share::PERM_VIEW],
		]);
	}

	public function testSetSharesRejectsUnauthorizedManager(): void {
		$this->permissions->method('isAdmin')->willReturn(false);
		$note = new Note();
		$note->setCreatedBy('alice');
		$this->noteMapper->method('findById')->willReturn($note);

		$this->expectException(ForbiddenException::class);
		$this->svc->setSharesForEntity(1, 'bob', Share::TYPE_NOTE, 5, []);
	}

	public function testSetSharesPersistsForMember(): void {
		$this->permissions->method('isAdmin')->willReturn(true);
		$this->memberMapper->method('findForUserAndHouse')->willReturn(new HouseMember());
		$entries = [['uid' => 'bob', 'permission' => Share::PERM_EDIT]];
		$this->shareMapper->expects($this->once())
			->method('setSharesForEntity')
			->with(1, Share::TYPE_NOTE, 5, 'admin', $entries);

		$this->svc->setSharesForEntity(1, 'admin', Share::TYPE_NOTE, 5, $entries);
	}
}
