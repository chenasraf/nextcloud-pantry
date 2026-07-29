<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Pantry\Controller;

use OCA\Pantry\Db\BarcodeCache;
use OCA\Pantry\Exception\NotFoundException;
use OCA\Pantry\ResponseDefinitions;
use OCA\Pantry\Service\BarcodeService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type PantryBarcode from ResponseDefinitions
 */
final class BarcodeController extends OCSController {
	use TranslatesDomainExceptions;

	public function __construct(
		string $appName,
		IRequest $request,
		private BarcodeService $barcodes,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Look up a barcode in the shared cache
	 *
	 * Cache-only: never makes an external call. A miss returns 404 so the
	 * client resolves it from its own IP and writes the result back.
	 *
	 * @param string $ean Barcode digits (EAN/UPC).
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBarcode, array{}>
	 *
	 * 200: Barcode found in cache
	 */
	#[ApiRoute(verb: 'GET', url: '/api/barcode/{ean}', requirements: ['ean' => '\d+'])]
	#[NoAdminRequired]
	public function show(string $ean): DataResponse {
		return $this->runAction(function () use ($ean): DataResponse {
			$cached = $this->barcodes->getFromCache($ean);
			if ($cached === null) {
				throw new NotFoundException('Barcode not found');
			}
			return new DataResponse($this->serialize($cached));
		});
	}

	/**
	 * Write a resolved barcode back to the shared cache
	 *
	 * @param string $ean Barcode digits (EAN/UPC).
	 * @param string $name Resolved product name.
	 * @param string|null $brand Brand name.
	 * @param string|null $category Provider category hint (for best-match).
	 * @param string|null $imageUrl Product image URL.
	 * @param string|null $provider Id of the source that resolved it.
	 * @param string|null $raw Raw provider payload.
	 *
	 * @return DataResponse<Http::STATUS_OK, PantryBarcode, array{}>
	 *
	 * 200: Barcode cached
	 */
	#[ApiRoute(verb: 'POST', url: '/api/barcode')]
	#[NoAdminRequired]
	public function store(
		string $ean,
		string $name,
		?string $brand = null,
		?string $category = null,
		?string $imageUrl = null,
		?string $provider = null,
		?string $raw = null,
	): DataResponse {
		return $this->runAction(function () use ($ean, $name, $brand, $category, $imageUrl, $provider, $raw): DataResponse {
			$cached = $this->barcodes->saveResult([
				'ean' => $ean,
				'name' => $name,
				'brand' => $brand,
				'category' => $category,
				'imageUrl' => $imageUrl,
				'provider' => $provider,
				'raw' => $raw,
			]);
			return new DataResponse($this->serialize($cached));
		});
	}

	/**
	 * @return PantryBarcode
	 */
	private function serialize(BarcodeCache $cache): array {
		return [
			'ean' => $cache->getEan(),
			'name' => $cache->getName(),
			'brand' => $cache->getBrand(),
			'category' => $cache->getCategory(),
			'imageUrl' => $cache->getImageUrl(),
			'provider' => $cache->getProvider(),
		];
	}
}
