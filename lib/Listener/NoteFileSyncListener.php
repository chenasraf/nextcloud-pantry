<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Listener;

use OCA\Pantry\Service\NoteSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

/**
 * Carries file changes into the notes bound to them, which is what makes the
 * file side of the sync a push rather than a poll.
 *
 * These events fire for every file on the instance, not just ours, so each
 * branch narrows on in-memory checks — node type, then MIME — before it is
 * willing to spend a database query.
 *
 * Deletion is deliberately not one of the branches. Deleting a file moves it to
 * the trash, and a binding dropped there could not be rebuilt: sync always
 * creates the file it binds to, so there is no way to re-attach a note to a
 * file that came back. Leaving the binding alone means a restore resumes sync;
 * a file that is purged for good leaves a binding the note's owner can release
 * by hand.
 *
 * @template-implements IEventListener<Event>
 */
class NoteFileSyncListener implements IEventListener {
	public function __construct(
		private NoteSyncService $sync,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			match (true) {
				$event instanceof NodeWrittenEvent => $this->onWritten($event->getNode()),
				$event instanceof NodeRenamedEvent => $this->onRenamed($event->getTarget()),
				default => null,
			};
		} catch (\Throwable $e) {
			// A note that failed to keep up must not take the file write with it.
			$this->logger->warning('Pantry: note sync failed to handle a file event', [
				'exception' => $e,
			]);
		}
	}

	private function onWritten(Node $node): void {
		$file = $this->textFile($node);
		if ($file !== null) {
			$this->sync->pullFromFile($file);
		}
	}

	private function onRenamed(Node $node): void {
		$file = $this->textFile($node);
		if ($file !== null) {
			$this->sync->applyFileRename($file);
		}
	}

	/**
	 * The node as a text file, or null when it cannot be one a note is bound to.
	 */
	private function textFile(Node $node): ?File {
		if (!$node instanceof File) {
			return null;
		}

		return NoteSyncService::isTextMime($node->getMimeType()) ? $node : null;
	}
}
