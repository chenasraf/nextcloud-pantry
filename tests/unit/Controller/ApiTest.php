<?php

declare(strict_types=1);

namespace Controller;

use OCA\NextcloudAppTemplate\AppInfo\Application;
use OCA\NextcloudAppTemplate\Controller\ApiController;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	private ApiController $controller;
	/** @var IRequest&MockObject */
	private IRequest $request;
	/** @var IAppConfig&MockObject */
	private IAppConfig $config;
	/** @var IL10N&MockObject */
	private IL10N $l10n;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);

		// Mock translation to return a simple string by default
		$this->l10n->method('t')
			->willReturnCallback(function ($text, $params = []) {
				if (empty($params)) {
					return $text;
				}
				return vsprintf(str_replace('%s', '%s', $text), $params);
			});

		$this->controller = new ApiController(
			Application::APP_ID,
			$this->request,
			$this->config,
			$this->l10n
		);
	}

	public function testGetHello(): void {
		// Mock config to return empty string (no previous hello)
		$this->config->method('getValueString')
			->willReturn('');

		$resp = $this->controller->getHello()->getData();

		$this->assertIsArray($resp);
		$this->assertArrayHasKey('message', $resp);
		$this->assertArrayHasKey('at', $resp);
		$this->assertEquals('👋 Hello from server!', $resp['message']);
		$this->assertNull($resp['at']);
	}

	public function testPostHello(): void {
		// Expect setValueString to be called to save the timestamp
		$this->config->expects($this->once())
			->method('setValueString');

		$resp = $this->controller->postHello([
			'name' => 'World',
			'theme' => 'dark',
			'items' => ['item1', 'item2'],
			'counter' => 5
		])->getData();

		$this->assertIsArray($resp);
		$this->assertArrayHasKey('message', $resp);
		$this->assertArrayHasKey('at', $resp);
		$this->assertStringContainsString('World', $resp['message']);
		$this->assertNotEmpty($resp['at']);
	}
}
