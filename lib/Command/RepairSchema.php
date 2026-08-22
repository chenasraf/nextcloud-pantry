<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Command;

use OCA\Pantry\Migration\SchemaRepairService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ pantry:repair-schema` — reconcile optional columns that schema drift
 * left behind: add back the nullable columns a migration meant to create and
 * drop the leftover ones a later migration meant to remove, so a stuck upgrade
 * or a checklist that errors out can be fixed without touching the database by
 * hand.
 *
 * Only ever adds columns an earlier migration already intended or drops columns
 * a later migration already meant to remove, so it is safe to run repeatedly;
 * `--dry-run` prints the SQL without applying it.
 */
class RepairSchema extends Command {
	public function __construct(
		private SchemaRepairService $repair,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('pantry:repair-schema')
			->setDescription('Reconcile Pantry columns that a failed migration left missing or behind')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the SQL that would run instead of applying it.');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		$result = $this->repair->repair($dryRun);

		foreach ($result->missingTables as $table) {
			$output->writeln('<comment>Table ' . $table . ' does not exist; skipping its columns. Run "occ upgrade" to create it.</comment>');
		}

		if (!$result->madeChanges()) {
			$output->writeln('<info>Pantry schema is up to date; nothing to repair.</info>');
			return 0;
		}

		if ($dryRun) {
			if ($result->added !== []) {
				$output->writeln('<info>Would add ' . count($result->added) . ' column(s):</info> ' . implode(', ', $result->added));
			}
			if ($result->dropped !== []) {
				$output->writeln('<info>Would drop ' . count($result->dropped) . ' column(s):</info> ' . implode(', ', $result->dropped));
			}
			if ($result->sql !== '') {
				$output->writeln('');
				$output->writeln($result->sql);
			}
			return 0;
		}

		foreach ($result->added as $column) {
			$output->writeln('<info>Added ' . $column . '.</info>');
		}
		foreach ($result->dropped as $column) {
			$output->writeln('<info>Dropped ' . $column . '.</info>');
		}
		$output->writeln('<info>Repaired ' . (count($result->added) + count($result->dropped)) . ' column(s).</info>');

		return 0;
	}
}
