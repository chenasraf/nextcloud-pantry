import { describe, expect, it } from 'vitest'

import { formatPrice, hasPrice, type PriceValue } from './price'

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
