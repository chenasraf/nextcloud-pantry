<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service\Barcode;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Resolves barcodes against the free Open Food Facts database.
 *
 * No API key required; the API asks only for a descriptive User-Agent.
 * @see https://openfoodfacts.github.io/openfoodfacts-server/api/
 */
class OpenFoodFactsProvider implements BarcodeLookupProvider {
	public const ID = 'openfoodfacts';

	private const ENDPOINT = 'https://world.openfoodfacts.org/api/v2/product/%s.json';
	private const USER_AGENT = 'NextcloudPantry - https://github.com/chenasraf/nextcloud-pantry';

	public function __construct(
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return self::ID;
	}

	public function lookup(string $ean): ?BarcodeResult {
		$url = sprintf(self::ENDPOINT, rawurlencode($ean));
		try {
			$response = $this->clientService->newClient()->get($url, [
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 10,
			]);
		} catch (\Throwable $e) {
			$this->logger->info('Open Food Facts lookup failed for {ean}: {message}', [
				'ean' => $ean,
				'message' => $e->getMessage(),
				'app' => 'pantry',
			]);
			return null;
		}

		$body = json_decode((string)$response->getBody(), true);
		if (!is_array($body) || (int)($body['status'] ?? 0) !== 1 || !is_array($body['product'] ?? null)) {
			// status 0 => product not found.
			return null;
		}

		/** @var array<string, mixed> $product */
		$product = $body['product'];
		$name = $this->pickName($product);
		if ($name === null) {
			// A row with no usable name is not worth caching.
			return null;
		}

		$raw = json_encode($product);
		return new BarcodeResult(
			ean: $ean,
			name: $name,
			brand: $this->firstOf((string)($product['brands'] ?? '')),
			category: $this->firstOf((string)($product['categories'] ?? '')),
			imageUrl: $this->strOrNull($product['image_url'] ?? null),
			provider: self::ID,
			raw: $raw === false ? null : $raw,
		);
	}

	/**
	 * @param array<string, mixed> $product
	 */
	private function pickName(array $product): ?string {
		foreach (['product_name', 'generic_name', 'abbreviated_product_name'] as $key) {
			$value = $this->strOrNull($product[$key] ?? null);
			if ($value !== null) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * Open Food Facts returns comma-separated lists for brands/categories.
	 * Keep the first entry, which is the most specific/primary one.
	 */
	private function firstOf(string $csv): ?string {
		$first = trim(explode(',', $csv)[0] ?? '');
		return $first === '' ? null : $first;
	}

	private function strOrNull(mixed $value): ?string {
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}
}
