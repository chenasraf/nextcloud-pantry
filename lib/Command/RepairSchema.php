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
 * `occ pantry:repair-schema` — add back optional columns that schema drift
 * left missing, so a stuck upgrade or a checklist that
 * errors out can be fixed without touching the database by hand.
 *
 * Only ever adds the nullable columns an earlier migration already intended, so
 * it is safe to run repeatedly; `--dry-run` prints the SQL without applying it.
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
			->setDescription('Add back Pantry columns that a failed migration left missing')
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
			$output->writeln('<info>Would add ' . count($result->added) . ' column(s):</info> ' . implode(', ', $result->added));
			if ($result->sql !== '') {
				$output->writeln('');
				$output->writeln($result->sql);
			}
			return 0;
		}

		foreach ($result->added as $column) {
			$output->writeln('<info>Added ' . $column . '.</info>');
		}
		$output->writeln('<info>Repaired ' . count($result->added) . ' column(s).</info>');

		return 0;
	}
}
