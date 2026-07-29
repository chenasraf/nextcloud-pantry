import { ocs } from '../axios'

/**
 * A resolved barcode, matching the backend `PantryBarcode` shape.
 */
export interface BarcodeResult {
  ean: string
  name: string
  brand: string | null
  /** Provider category hint, used for best-match against house categories. */
  category: string | null
  imageUrl: string | null
  provider: string
}

/**
 * Ask the backend whether a barcode is already in the shared cache.
 * Returns the cached result, or null on a cache miss (404).
 */
export async function lookupBarcode(ean: string): Promise<BarcodeResult | null> {
  try {
    const resp = await ocs.get<BarcodeResult>(`/barcode/${encodeURIComponent(ean)}`)
    return resp.data
  } catch (err: unknown) {
    if (isNotFound(err)) {
      return null
    }
    throw err
  }
}

/**
 * Write a client-resolved barcode back to the shared cache so it becomes an
 * instant hit for the whole house next time.
 */
export async function saveBarcode(result: BarcodeResult): Promise<BarcodeResult> {
  const resp = await ocs.post<BarcodeResult>('/barcode', {
    ean: result.ean,
    name: result.name,
    brand: result.brand ?? undefined,
    category: result.category ?? undefined,
    imageUrl: result.imageUrl ?? undefined,
    provider: result.provider,
  })
  return resp.data
}

/**
 * Resolve a barcode against the external provider from the user's own IP
 * (Open Food Facts, open CORS, no key). Uses native fetch — never the
 * Nextcloud axios instance — so no CSRF/session headers leak to a third party.
 *
 * v1 ships Open Food Facts only; a future provider swap lives here.
 */
export async function resolveExternally(ean: string): Promise<BarcodeResult | null> {
  const url = `https://world.openfoodfacts.org/api/v2/product/${encodeURIComponent(ean)}.json`
  let body: OffResponse
  try {
    const resp = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!resp.ok) {
      return null
    }
    body = (await resp.json()) as OffResponse
  } catch {
    return null
  }

  if (body.status !== 1 || !body.product) {
    return null
  }
  const p = body.product
  const name = firstNonEmpty(p.product_name, p.generic_name, p.abbreviated_product_name)
  if (!name) {
    return null
  }
  return {
    ean,
    name,
    brand: firstOf(p.brands),
    category: firstOf(p.categories),
    imageUrl: strOrNull(p.image_url),
    provider: 'openfoodfacts',
  }
}

interface OffResponse {
  status?: number
  product?: {
    product_name?: string
    generic_name?: string
    abbreviated_product_name?: string
    brands?: string
    categories?: string
    image_url?: string
  }
}

/** Open Food Facts returns comma-separated lists; keep the primary entry. */
function firstOf(csv: string | undefined): string | null {
  return firstNonEmpty((csv ?? '').split(',')[0])
}

function firstNonEmpty(...values: (string | undefined)[]): string | null {
  for (const value of values) {
    const trimmed = (value ?? '').trim()
    if (trimmed !== '') {
      return trimmed
    }
  }
  return null
}

function strOrNull(value: string | undefined): string | null {
  const trimmed = (value ?? '').trim()
  return trimmed === '' ? null : trimmed
}

function isNotFound(err: unknown): boolean {
  return (
    typeof err === 'object' &&
    err !== null &&
    'response' in err &&
    (err as { response?: { status?: number } }).response?.status === 404
  )
}
