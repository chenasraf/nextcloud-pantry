<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Service\Barcode;

/**
 * A source that can resolve a barcode (EAN/UPC) into product details.
 *
 * Providers are keyed by {@see self::getId()} and selected via the
 * `barcode_provider` app config value. This is the server-side lookup path
 * (used by the deferred proxy endpoint); the default client-side path resolves
 * from the user's own IP and writes the result back.
 */
interface BarcodeLookupProvider {
	/**
	 * Stable identifier, e.g. "openfoodfacts". Stored on cached rows.
	 */
	public function getId(): string;

	/**
	 * Resolve a barcode, or return null when the product is unknown or the
	 * lookup fails. Must never throw for a plain "not found".
	 */
	public function lookup(string $ean): ?BarcodeResult;
}
