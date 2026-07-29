<?php

declare(strict_types=1);

namespace OCA\Pantry\Controller;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Main app page
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, 'app', [
			'script' => Application::getViteEntryScript('app.ts'),
			'style' => Application::getViteEntryScript('style.css'),
		]);
		// Allow the barcode-scanner wasm fallback (Firefox/Safari, which lack a
		// native BarcodeDetector) to execute. The wasm is served from the app
		// origin, so no extra domains are needed — only 'wasm-unsafe-eval'.
		$csp = new ContentSecurityPolicy();
		$csp->allowEvalWasm(true);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Main app page - catch all route
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function catchAll(string $path = ''): TemplateResponse {
		return $this->index();
	}
}
