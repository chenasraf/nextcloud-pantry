import { describe, expect, it } from 'vitest'

import { formatPrice, hasPrice, resolveItemPrice, storelessPrice, type PriceValue } from './price'
import type { ItemPrice } from '@/api/types'

function itemPrice(partial: Partial<ItemPrice>): ItemPrice {
  return {
    storeId: null,
    priceType: 'set',
    priceMin: 1,
    priceMax: null,
    priceCurrency: 'USD',
    ...partial,
  }
}

function price(partial: Partial<PriceValue>): PriceValue {
  return {
    priceType: null,
    priceMin: null,
    priceMax: null,
    priceCurrency: null,
    ...partial,
  }
}

describe('hasPrice', () => {
  it('is false without a type or amount', () => {
    expect(hasPrice(price({}))).toBe(false)
    expect(hasPrice(price({ priceType: 'set', priceMin: null }))).toBe(false)
    expect(hasPrice(price({ priceType: null, priceMin: 5 }))).toBe(false)
  })

  it('is true with a type and a min amount', () => {
    expect(hasPrice(price({ priceType: 'set', priceMin: 5 }))).toBe(true)
    expect(hasPrice(price({ priceType: 'range', priceMin: 1, priceMax: 10 }))).toBe(true)
  })
})

describe('formatPrice', () => {
  it('returns null when there is no price', () => {
    expect(formatPrice(price({}))).toBeNull()
    expect(formatPrice(price({ priceType: 'set', priceMin: null }))).toBeNull()
  })

  it('places the symbol before the amount for USD ($1)', () => {
    expect(formatPrice(price({ priceType: 'set', priceMin: 1, priceCurrency: 'USD' }))).toBe('$1')
  })

  it('places the symbol after the amount for ILS (1₪)', () => {
    expect(formatPrice(price({ priceType: 'set', priceMin: 1, priceCurrency: 'ILS' }))).toBe('1₪')
  })

  it('formats a range as $1-10', () => {
    expect(
      formatPrice(price({ priceType: 'range', priceMin: 1, priceMax: 10, priceCurrency: 'USD' })),
    ).toBe('$1-10')
  })

  it('formats a range after the amount for ILS (1-10₪)', () => {
    expect(
      formatPrice(price({ priceType: 'range', priceMin: 1, priceMax: 10, priceCurrency: 'ILS' })),
    ).toBe('1-10₪')
  })

  it('trims trailing zeros but keeps meaningful decimals', () => {
    expect(formatPrice(price({ priceType: 'set', priceMin: 1.0, priceCurrency: 'USD' }))).toBe('$1')
    expect(formatPrice(price({ priceType: 'set', priceMin: 1.5, priceCurrency: 'USD' }))).toBe(
      '$1.5',
    )
    expect(formatPrice(price({ priceType: 'set', priceMin: 9.99, priceCurrency: 'USD' }))).toBe(
      '$9.99',
    )
  })

  it('falls back to a set price when a range has no max', () => {
    expect(
      formatPrice(price({ priceType: 'range', priceMin: 3, priceMax: null, priceCurrency: 'USD' })),
    ).toBe('$3')
  })

  it('shows the raw code for an unknown currency', () => {
    expect(formatPrice(price({ priceType: 'set', priceMin: 2, priceCurrency: 'XyZ' }))).toBe('XYZ2')
  })
})

describe('storelessPrice', () => {
  it('returns the price with a null store id', () => {
    const storeless = itemPrice({ storeId: null, priceMin: 5 })
    expect(storelessPrice([itemPrice({ storeId: 3, priceMin: 9 }), storeless])).toBe(storeless)
  })

  it('returns null when every price is attached to a store', () => {
    expect(storelessPrice([itemPrice({ storeId: 3 })])).toBeNull()
    expect(storelessPrice([])).toBeNull()
  })
})

describe('resolveItemPrice', () => {
  const storeless = itemPrice({ storeId: null, priceMin: 5 })
  const atStore3 = itemPrice({ storeId: 3, priceMin: 9 })

  it('prefers the store price when present', () => {
    expect(resolveItemPrice([storeless, atStore3], 3)).toBe(atStore3)
  })

  it('falls back to the store-less price when the store has none', () => {
    expect(resolveItemPrice([storeless, atStore3], 7)).toBe(storeless)
  })

  it('resolves the store-less price directly for null store context', () => {
    expect(resolveItemPrice([storeless, atStore3], null)).toBe(storeless)
  })

  it('returns null when neither a store price nor a store-less price exists', () => {
    expect(resolveItemPrice([atStore3], 7)).toBeNull()
    expect(resolveItemPrice([], 3)).toBeNull()
  })
})
