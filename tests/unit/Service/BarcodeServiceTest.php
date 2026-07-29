<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service;

use OCA\Pantry\Db\BarcodeCache;
use OCA\Pantry\Db\BarcodeCacheMapper;
use OCA\Pantry\Service\Barcode\BarcodeResult;
use OCA\Pantry\Service\Barcode\OpenFoodFactsProvider;
use OCA\Pantry\Service\BarcodeService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BarcodeServiceTest extends TestCase {
	/** @var BarcodeCacheMapper&MockObject */
	private BarcodeCacheMapper $mapper;
	/** @var IAppConfig&MockObject */
	private IAppConfig $appConfig;
	/** @var OpenFoodFactsProvider&MockObject */
	private OpenFoodFactsProvider $provider;
	private BarcodeService $svc;

	protected function setUp(): void {
		$this->mapper = $this->createMock(BarcodeCacheMapper::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->provider = $this->createMock(OpenFoodFactsProvider::class);
		$this->provider->method('getId')->willReturn(OpenFoodFactsProvider::ID);
		$this->svc = new BarcodeService($this->mapper, $this->appConfig, $this->provider);
	}

	public function testGetFromCacheDelegatesToMapper(): void {
		$entity = new BarcodeCache();
		$this->mapper->expects($this->once())
			->method('findByEan')
			->with('4001724819103')
			->willReturn($entity);

		$this->assertSame($entity, $this->svc->getFromCache('4001724819103'));
	}

	public function testGetFromCacheRejectsNonNumericEan(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->getFromCache('not-a-barcode');
	}

	public function testSaveResultInsertsWhenNew(): void {
		$this->mapper->method('findByEan')->willReturn(null);
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(fn (BarcodeCache $c) => $c);
		$this->mapper->expects($this->never())->method('update');

		$saved = $this->svc->saveResult([
			'ean' => '4001724819103',
			'name' => 'Cola',
			'brand' => 'Acme',
			'category' => 'Beverages',
			'imageUrl' => 'https://img/x.jpg',
			'provider' => 'openfoodfacts',
		]);

		$this->assertSame('4001724819103', $saved->getEan());
		$this->assertSame('Cola', $saved->getName());
		$this->assertSame('Beverages', $saved->getCategory());
		$this->assertGreaterThan(0, $saved->getResolvedAt());
	}

	public function testSaveResultUpdatesWhenExisting(): void {
		$existing = new BarcodeCache();
		$existing->setEan('4001724819103');
		$this->mapper->method('findByEan')->willReturn($existing);
		$this->mapper->expects($this->once())
			->method('update')
			->willReturnCallback(fn (BarcodeCache $c) => $c);
		$this->mapper->expects($this->never())->method('insert');

		$saved = $this->svc->saveResult(['ean' => '4001724819103', 'name' => 'Cola']);
		$this->assertSame('Cola', $saved->getName());
	}

	public function testSaveResultRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->saveResult(['ean' => '4001724819103', 'name' => '   ']);
	}

	public function testSaveResultDefaultsProviderToClient(): void {
		$this->mapper->method('findByEan')->willReturn(null);
		$this->mapper->method('insert')->willReturnCallback(fn (BarcodeCache $c) => $c);

		$saved = $this->svc->saveResult(['ean' => '12345678', 'name' => 'Thing']);
		$this->assertSame('client', $saved->getProvider());
	}

	public function testResolveViaProviderUsesConfiguredProvider(): void {
		$this->appConfig->method('getValueString')->willReturn(OpenFoodFactsProvider::ID);
		$result = new BarcodeResult('12345678', 'Thing', null, null, null, OpenFoodFactsProvider::ID);
		$this->provider->expects($this->once())
			->method('lookup')
			->with('12345678')
			->willReturn($result);

		$this->assertSame($result, $this->svc->resolveViaProvider('12345678'));
	}
}
