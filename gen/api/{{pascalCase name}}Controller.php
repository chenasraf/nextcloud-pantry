<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\NextcloudAppTemplate\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class {{pascalCase name}}Controller extends OCSController {
	/**
	 * {{pascalCase name}} constructor.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $config,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * API index
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Data returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{{kebabCase name}}')]
	public function index(): JSONResponse {
		return new JSONResponse();
	}
}
