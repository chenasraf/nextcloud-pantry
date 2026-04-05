<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Exception\ForbiddenException;
use OCA\Pantry\Exception\NotFoundException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;

/**
 * Wraps controller actions so domain exceptions become proper OCS errors.
 */
trait TranslatesDomainExceptions {
	/**
	 * @template T
	 * @param callable():T $fn
	 * @return T
	 */
	private function runAction(callable $fn) {
		try {
			return $fn();
		} catch (ForbiddenException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException($e->getMessage(), $e);
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		}
	}
}
