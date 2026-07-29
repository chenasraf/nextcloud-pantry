<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Tests\Unit\Service\Barcode;

use OCA\Pantry\Service\Barcode\OpenFoodFactsProvider;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OpenFoodFactsProviderTest extends TestCase {
	/** @var IClientService&MockObject */
	private IClientService $clientService;
	/** @var IClient&MockObject */
	private IClient $client;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	private OpenFoodFactsProvider $provider;

	protected function setUp(): void {
		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->provider = new OpenFoodFactsProvider($this->clientService, $this->logger);
	}

	private function respondWith(string $body): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		$this->client->method('get')->willReturn($response);
	}

	public function testLookupMapsProductFields(): void {
		$this->client->expects($this->once())
			->method('get')
			->with(
				$this->stringContains('/api/v2/product/4001724819103.json'),
				$this->callback(fn (array $opts) => isset($opts['headers']['User-Agent'])),
			)
			->willReturn($this->makeResponse(json_encode([
				'status' => 1,
				'product' => [
					'product_name' => 'Cola Zero',
					'brands' => 'Acme, SubBrand',
					'categories' => 'Beverages, Sodas',
					'image_url' => 'https://images/x.jpg',
				],
			])));

		$result = $this->provider->lookup('4001724819103');

		$this->assertNotNull($result);
		$this->assertSame('4001724819103', $result->ean);
		$this->assertSame('Cola Zero', $result->name);
		$this->assertSame('Acme', $result->brand);
		$this->assertSame('Beverages', $result->category);
		$this->assertSame('https://images/x.jpg', $result->imageUrl);
		$this->assertSame(OpenFoodFactsProvider::ID, $result->provider);
	}

	public function testLookupReturnsNullWhenNotFound(): void {
		$this->respondWith(json_encode(['status' => 0, 'status_verbose' => 'product not found']));
		$this->assertNull($this->provider->lookup('0000000000000'));
	}

	public function testLookupReturnsNullWhenNoName(): void {
		$this->respondWith(json_encode(['status' => 1, 'product' => ['brands' => 'Acme']]));
		$this->assertNull($this->provider->lookup('4001724819103'));
	}

	public function testLookupReturnsNullOnHttpError(): void {
		$this->client->method('get')->willThrowException(new \RuntimeException('boom'));
		$this->assertNull($this->provider->lookup('4001724819103'));
	}

	public function testLookupFallsBackToGenericName(): void {
		$this->respondWith(json_encode([
			'status' => 1,
			'product' => ['generic_name' => 'Sparkling water'],
		]));
		$result = $this->provider->lookup('4001724819103');
		$this->assertNotNull($result);
		$this->assertSame('Sparkling water', $result->name);
	}

	private function makeResponse(string $body): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		return $response;
	}
}
