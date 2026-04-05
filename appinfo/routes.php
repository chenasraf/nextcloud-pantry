<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// SPA catch-all routes - serve the main template for all sub-paths
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.*']],
	],
];
