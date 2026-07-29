<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service\Barcode;

/**
 * Immutable result of resolving a barcode, independent of the provider that
 * produced it.
 */
class BarcodeResult {
	public function __construct(
		public readonly string $ean,
		public readonly string $name,
		public readonly ?string $brand,
		public readonly ?string $category,
		public readonly ?string $imageUrl,
		public readonly string $provider,
		public readonly ?string $raw = null,
	) {
	}

	/**
	 * @return array{ean: string, name: string, brand: string|null, category: string|null, imageUrl: string|null, provider: string}
	 */
	public function jsonSerialize(): array {
		return [
			'ean' => $this->ean,
			'name' => $this->name,
			'brand' => $this->brand,
			'category' => $this->category,
			'imageUrl' => $this->imageUrl,
			'provider' => $this->provider,
		];
	}
}
