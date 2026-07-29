<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Controller;

use OCA\Pantry\Controller\BarcodeController;
use OCA\Pantry\Db\BarcodeCache;
use OCA\Pantry\Service\BarcodeService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BarcodeControllerTest extends TestCase {
	/** @var BarcodeService&MockObject */
	private BarcodeService $barcodes;
	private BarcodeController $controller;

	protected function setUp(): void {
		$this->barcodes = $this->createMock(BarcodeService::class);
		$this->controller = new BarcodeController(
			'pantry',
			$this->createMock(IRequest::class),
			$this->barcodes,
		);
	}

	private function makeCache(): BarcodeCache {
		$c = new BarcodeCache();
		$c->setEan('4001724819103');
		$c->setName('Cola Zero');
		$c->setBrand('Acme');
		$c->setCategory('Beverages');
		$c->setImageUrl('https://images/x.jpg');
		$c->setProvider('openfoodfacts');
		return $c;
	}

	public function testShowReturnsCachedBarcode(): void {
		$this->barcodes->method('getFromCache')->with('4001724819103')->willReturn($this->makeCache());

		$response = $this->controller->show('4001724819103');

		$this->assertSame([
			'ean' => '4001724819103',
			'name' => 'Cola Zero',
			'brand' => 'Acme',
			'category' => 'Beverages',
			'imageUrl' => 'https://images/x.jpg',
			'provider' => 'openfoodfacts',
		], $response->getData());
	}

	public function testShowThrowsNotFoundOnMiss(): void {
		$this->barcodes->method('getFromCache')->willReturn(null);
		$this->expectException(OCSNotFoundException::class);
		$this->controller->show('0000000000000');
	}

	public function testStoreDelegatesToService(): void {
		$this->barcodes->expects($this->once())
			->method('saveResult')
			->with($this->callback(fn (array $d) => $d['ean'] === '4001724819103' && $d['name'] === 'Cola Zero'))
			->willReturn($this->makeCache());

		$response = $this->controller->store('4001724819103', 'Cola Zero', 'Acme', 'Beverages', 'https://images/x.jpg', 'openfoodfacts');
		$this->assertSame('Cola Zero', $response->getData()['name']);
	}
}
