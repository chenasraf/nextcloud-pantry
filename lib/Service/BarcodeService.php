<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service;

use OCA\Pantry\Db\BarcodeCache;
use OCA\Pantry\Db\BarcodeCacheMapper;
use OCA\Pantry\Service\Barcode\BarcodeLookupProvider;
use OCA\Pantry\Service\Barcode\BarcodeResult;
use OCA\Pantry\Service\Barcode\OpenFoodFactsProvider;
use OCP\IAppConfig;

class BarcodeService {
	/** @var array<string, BarcodeLookupProvider> */
	private array $providers;

	public function __construct(
		private BarcodeCacheMapper $mapper,
		private IAppConfig $appConfig,
		OpenFoodFactsProvider $openFoodFacts,
	) {
		// Register available providers by id. Add new providers here.
		$this->providers = [$openFoodFacts->getId() => $openFoodFacts];
	}

	/**
	 * Shared-cache lookup only — never makes an external call. This is the
	 * common "do you know EAN X?" path.
	 */
	public function getFromCache(string $ean): ?BarcodeCache {
		return $this->mapper->findByEan($this->normalizeEan($ean));
	}

	/**
	 * Persist a resolved barcode into the shared cache (upsert). Used for
	 * client write-back and by the server-side proxy path.
	 *
	 * @param array{ean?: mixed, name?: mixed, brand?: mixed, category?: mixed, imageUrl?: mixed, provider?: mixed, raw?: mixed} $data
	 */
	public function saveResult(array $data): BarcodeCache {
		$ean = $this->normalizeEan((string)($data['ean'] ?? ''));
		$name = trim((string)($data['name'] ?? ''));
		if ($name === '') {
			throw new \InvalidArgumentException('Barcode name cannot be empty');
		}

		$existing = $this->mapper->findByEan($ean);
		$entity = $existing ?? new BarcodeCache();
		$entity->setEan($ean);
		$entity->setName($name);
		$entity->setBrand($this->strOrNull($data['brand'] ?? null));
		$entity->setCategory($this->strOrNull($data['category'] ?? null));
		$entity->setImageUrl($this->strOrNull($data['imageUrl'] ?? null));
		$entity->setProvider($this->strOrNull($data['provider'] ?? null) ?? 'client');
		$entity->setRaw($this->strOrNull($data['raw'] ?? null));
		$entity->setResolvedAt(time());

		/** @var BarcodeCache $saved */
		$saved = $existing !== null ? $this->mapper->update($entity) : $this->mapper->insert($entity);
		return $saved;
	}

	/**
	 * Server-side resolution via the configured provider. Only used by the
	 * deferred proxy endpoint — the default path resolves client-side.
	 */
	public function resolveViaProvider(string $ean): ?BarcodeResult {
		return $this->provider()->lookup($this->normalizeEan($ean));
	}

	private function provider(): BarcodeLookupProvider {
		$id = $this->appConfig->getValueString('pantry', 'barcode_provider', OpenFoodFactsProvider::ID);
		return $this->providers[$id] ?? $this->providers[OpenFoodFactsProvider::ID];
	}

	private function normalizeEan(string $ean): string {
		$ean = trim($ean);
		if (!preg_match('/^\d{6,14}$/', $ean)) {
			throw new \InvalidArgumentException('Invalid barcode');
		}
		return $ean;
	}

	private function strOrNull(mixed $value): ?string {
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}
}
