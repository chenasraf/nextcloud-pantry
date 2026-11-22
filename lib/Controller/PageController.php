<?php

declare(strict_types=1);

namespace OCA\Pantry\Controller;

use OCA\Pantry\AppInfo\Application;
use OCA\Pantry\Service\PrefsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-api
 */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IEventDispatcher $eventDispatcher,
		private IInitialState $initialState,
		private IUserSession $userSession,
		private PrefsService $prefs,
		private IAppManager $appManager,
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
		// The frontend applies the Pantry language override before its first
		// render, so it has to arrive with the page rather than over the API.
		$uid = $this->userSession->getUser()?->getUID();
		$this->initialState->provideInitialState('language', $uid !== null ? ($this->prefs->getLanguage($uid) ?? '') : '');
		if ($uid !== null) {
			$installed = $this->appManager->getAppVersion(Application::APP_ID);
			$this->initialState->provideInitialState('whatsNew', [
				'seenVersion' => $this->prefs->getWhatsNewSeenVersion($uid, $installed),
				'installedVersion' => $installed,
			]);
		}
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
