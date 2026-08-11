<?php

declare(strict_types=1);

namespace OCA\Pantry\Controller;

use OCA\Pantry\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Ask the Text app (if installed) to load its editor bundle so the
	 * frontend can mount a WYSIWYG Markdown editor via `window.OCA.Text`.
	 * No-op when Text is not enabled — the frontend then falls back to a
	 * plain Markdown textarea.
	 */
	private function loadTextEditor(): void {
		// Text is an optional integration and not a declared dependency, so its
		// classes are unknown to static analysis. Guarded by class_exists.
		/** @psalm-suppress UndefinedClass, MixedArgument */
		if (class_exists(\OCA\Text\Event\LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new \OCA\Text\Event\LoadEditor());
		}
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
		$this->loadTextEditor();
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
