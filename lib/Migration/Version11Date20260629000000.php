<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Migration;

use Closure;
use OCA\Pantry\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Introduce a per-house roles & permissions system.
 *
 * - pantry_roles: per-house roles carrying granular can_* capabilities.
 * - pantry_house_member_roles: many-to-many between members and roles.
 * - pantry_list_roles: which roles may access a given checklist (empty = open).
 *
 * Seeds a built-in Admin and Member role for every existing house and backfills
 * each member's role assignment from the legacy house_members.role string. The
 * legacy column is left in place; a follow-up migration drops it once the
 * backfill has been verified in the wild.
 */
class Version11Date20260629000000 extends SimpleMigrationStep {
	/**
	 * The 18 capability columns, in declaration order. Kept in sync with
	 * \OCA\Pantry\Db\Role::CAPABILITIES.
	 *
	 * @var list<string>
	 */
	private const CAPABILITY_COLUMNS = [
		'can_view_lists', 'can_create_lists', 'can_edit_lists', 'can_delete_lists',
		'can_add_items', 'can_delete_items', 'can_copy_items', 'can_move_items', 'can_check_items',
		'can_view_photos', 'can_upload_photos', 'can_update_photos', 'can_delete_photos', 'can_move_photos',
		'can_view_notes', 'can_create_notes', 'can_update_notes', 'can_delete_notes',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$rolesTable = Application::tableName('roles');
		if (!$schema->hasTable($rolesTable)) {
			$table = $schema->createTable($rolesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('house_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('role_type', Types::STRING, ['notnull' => true, 'length' => 16]);
			foreach (self::CAPABILITY_COLUMNS as $col) {
				$table->addColumn($col, Types::BOOLEAN, [
					'notnull' => false,
					'default' => false,
				]);
			}
			$table->setPrimaryKey(['id']);
			$table->addIndex(['house_id'], 'pantry_roles_house_idx');
		}

		$memberRolesTable = Application::tableName('house_member_roles');
		if (!$schema->hasTable($memberRolesTable)) {
			$table = $schema->createTable($memberRolesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('member_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('role_id', Types::BIGINT, ['notnull' => true]);
			// Explicit short primary-key name: the auto-derived
			// "oc_pantry_house_member_roles_pkey" exceeds Nextcloud's 30-char
			// index-name limit.
			$table->setPrimaryKey(['id'], 'pantry_hmr_pkey');
			$table->addUniqueIndex(['member_id', 'role_id'], 'pantry_member_role_uniq');
			$table->addIndex(['role_id'], 'pantry_member_role_role_idx');
		}

		$listRolesTable = Application::tableName('list_roles');
		if (!$schema->hasTable($listRolesTable)) {
			$table = $schema->createTable($listRolesTable);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('role_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['list_id', 'role_id'], 'pantry_list_role_uniq');
			$table->addIndex(['role_id'], 'pantry_list_role_role_idx');
		}

		return $schema;
	}

	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$rolesTable = Application::tableName('roles');

		// Skip if roles were already seeded (idempotent re-run guard).
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))->from($rolesTable);
		$existing = (int)$qb->executeQuery()->fetchOne();
		if ($existing > 0) {
			return;
		}

		$housesTable = Application::tableName('houses');
		$membersTable = Application::tableName('house_members');
		$memberRolesTable = Application::tableName('house_member_roles');

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($housesTable);
		$houseIds = array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));

		foreach ($houseIds as $houseId) {
			$adminId = $this->insertRole($rolesTable, $houseId, 'Admin', 'admin');
			$memberId = $this->insertRole($rolesTable, $houseId, 'Member', 'default');

			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'role')
				->from($membersTable)
				->where($qb->expr()->eq('house_id', $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT)));
			$members = $qb->executeQuery()->fetchAll();

			foreach ($members as $member) {
				$legacyRole = (string)$member['role'];
				$roleId = ($legacyRole === 'owner' || $legacyRole === 'admin') ? $adminId : $memberId;
				$insert = $this->db->getQueryBuilder();
				$insert->insert($memberRolesTable)
					->values([
						'member_id' => $insert->createNamedParameter((int)$member['id'], IQueryBuilder::PARAM_INT),
						'role_id' => $insert->createNamedParameter($roleId, IQueryBuilder::PARAM_INT),
					]);
				$insert->executeStatement();
			}
		}
	}

	/**
	 * Insert a built-in role with every capability granted and return its id.
	 */
	private function insertRole(string $table, int $houseId, string $name, string $roleType): int {
		$qb = $this->db->getQueryBuilder();
		$values = [
			'house_id' => $qb->createNamedParameter($houseId, IQueryBuilder::PARAM_INT),
			'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
			'role_type' => $qb->createNamedParameter($roleType, IQueryBuilder::PARAM_STR),
		];
		foreach (self::CAPABILITY_COLUMNS as $col) {
			$values[$col] = $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL);
		}
		$qb->insert($table)->values($values);
		$qb->executeStatement();
		return (int)$qb->getLastInsertId();
	}
}
