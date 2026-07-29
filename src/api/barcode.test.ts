import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { get, post } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/axios', () => ({ ocs: { get, post } }))

import { lookupBarcode, resolveExternally, saveBarcode } from './barcode'

describe('lookupBarcode', () => {
  beforeEach(() => {
    get.mockReset()
    post.mockReset()
  })
  afterEach(() => vi.restoreAllMocks())

  it('returns the cached result on a hit', async () => {
    const result = {
      ean: '4001724819103',
      name: 'Cola',
      brand: 'Acme',
      category: 'Beverages',
      imageUrl: null,
      provider: 'openfoodfacts',
    }
    get.mockResolvedValueOnce({ data: result })

    expect(await lookupBarcode('4001724819103')).toEqual(result)
    expect(get).toHaveBeenCalledWith('/barcode/4001724819103')
  })

  it('returns null on a 404 cache miss', async () => {
    get.mockRejectedValueOnce({ response: { status: 404 } })
    expect(await lookupBarcode('4001724819103')).toBeNull()
  })

  it('rethrows non-404 errors', async () => {
    get.mockRejectedValueOnce({ response: { status: 500 } })
    await expect(lookupBarcode('4001724819103')).rejects.toEqual({ response: { status: 500 } })
  })
})

describe('saveBarcode', () => {
  beforeEach(() => post.mockReset())

  it('posts the write-back payload', async () => {
    const result = {
      ean: '4001724819103',
      name: 'Cola',
      brand: 'Acme',
      category: 'Beverages',
      imageUrl: 'https://img/x.jpg',
      provider: 'openfoodfacts',
    }
    post.mockResolvedValueOnce({ data: result })

    await saveBarcode(result)
    expect(post).toHaveBeenCalledWith('/barcode', {
      ean: '4001724819103',
      name: 'Cola',
      brand: 'Acme',
      category: 'Beverages',
      imageUrl: 'https://img/x.jpg',
      provider: 'openfoodfacts',
    })
  })
})

describe('resolveExternally', () => {
  afterEach(() => vi.restoreAllMocks())

  function mockFetch(status: number, body: unknown) {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: status >= 200 && status < 300,
        json: () => Promise.resolve(body),
      }),
    )
  }

  it('maps Open Food Facts fields, keeping the primary brand/category', async () => {
    mockFetch(200, {
      status: 1,
      product: {
        product_name: 'Cola Zero',
        brands: 'Acme, SubBrand',
        categories: 'Beverages, Sodas',
        image_url: 'https://images/x.jpg',
      },
    })

    const result = await resolveExternally('4001724819103')
    expect(result).toEqual({
      ean: '4001724819103',
      name: 'Cola Zero',
      brand: 'Acme',
      category: 'Beverages',
      imageUrl: 'https://images/x.jpg',
      provider: 'openfoodfacts',
    })
  })

  it('returns null when the product is not found', async () => {
    mockFetch(200, { status: 0 })
    expect(await resolveExternally('0000000000000')).toBeNull()
  })

  it('returns null when the product has no usable name', async () => {
    mockFetch(200, { status: 1, product: { brands: 'Acme' } })
    expect(await resolveExternally('4001724819103')).toBeNull()
  })

  it('returns null on a network error', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')))
    expect(await resolveExternally('4001724819103')).toBeNull()
  })
})
