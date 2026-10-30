import { describe, expect, it } from 'vitest'

import vectors from '../../tests/stock-vectors.json'
import { canStepDown, stepQuantity } from './quantity'
import {
  addDays,
  convertStock,
  daysUntil,
  evaluateStock,
  expiryStatus,
  firstNumber,
  itemTone,
  needsRestock,
  gaugeFill,
  renewOffer,
  gaugeTick,
  startingOnHand,
  todayEpoch,
  type ExpiryInput,
  type StockInput,
} from './stock'

describe('shared stock vectors', () => {
  it.each(vectors.firstNumber)('firstNumber($text)', ({ text, value }) => {
    expect(firstNumber(text)).toBe(value)
  })

  it.each(vectors.evaluate)('evaluate: $name', ({ item, stockStatus, shortfall }) => {
    expect(evaluateStock(item as StockInput)).toEqual({ stockStatus, shortfall })
  })

  it.each(vectors.startingOnHand)('startingOnHand: $name', ({ quantity, target, result }) => {
    expect(startingOnHand(quantity, target)).toBe(result)
  })

  it.each(vectors.convert)('convert: $name', ({ item, toMode, result }) => {
    expect(convertStock(item as StockInput, toMode as 'count' | 'level')).toEqual(result)
  })

  it.each(vectors.expiryStatus)('expiryStatus: $name', ({ item, leadDays, today, ...want }) => {
    expect(expiryStatus(item as ExpiryInput, leadDays, today)).toBe(want.expiryStatus)
  })
})

describe('expiry days', () => {
  it('adds days from midnight to midnight', () => {
    const today = todayEpoch()
    expect(daysUntil(addDays(today, 7), today)).toBe(7)
    expect(new Date(addDays(today, 200) * 1000).getHours()).toBe(0)
  })
})

describe('needing restock', () => {
  it('counts low, out, expiring and expired items', () => {
    expect(needsRestock({ stockStatus: 'low', expiryStatus: 'none' })).toBe(true)
    expect(needsRestock({ stockStatus: 'ok', expiryStatus: 'expiring' })).toBe(true)
    expect(needsRestock({ stockStatus: 'ok', expiryStatus: 'expired' })).toBe(true)
    expect(needsRestock({ stockStatus: 'uncounted', expiryStatus: 'ok' })).toBe(false)
  })

  it('colours red over amber', () => {
    expect(itemTone('low', 'expired')).toBe('error')
    expect(itemTone('out', 'expiring')).toBe('error')
    expect(itemTone('ok', 'expiring')).toBe('warning')
    expect(itemTone('uncounted', 'none')).toBe('muted')
    expect(itemTone('uncounted', 'expired')).toBe('error')
  })
})

describe('stepping on hand', () => {
  it('steps down to 0, keeping the unit', () => {
    expect(stepQuantity('1 eggs', -1, 0)).toBe('0 eggs')
    expect(canStepDown('0 eggs', 0)).toBe(false)
    expect(canStepDown('1', 0)).toBe(true)
  })

  it('keeps the floor of 1 for other quantities', () => {
    expect(stepQuantity('1', -1)).toBe('1')
    expect(canStepDown('1')).toBe(false)
  })
})

describe('gauge', () => {
  const count = (quantity: string, target: string, reorderPoint: string | null = null) => {
    const input: StockInput = {
      stockMode: 'count',
      stockLevel: null,
      quantity,
      target,
      reorderPoint,
    }
    return { ...input, ...evaluateStock(input) }
  }

  it('fills to on hand over target, capped at full', () => {
    expect(gaugeFill(count('4', '12 eggs'))).toBeCloseTo(1 / 3)
    expect(gaugeFill(count('20', '12'))).toBe(1)
  })

  it('has no gauge when not counted', () => {
    expect(gaugeFill(count('some', '12'))).toBeNull()
  })

  it('fills a third of the way for a low level item', () => {
    const level: StockInput = {
      stockMode: 'level',
      stockLevel: 'low',
      quantity: null,
      target: null,
      reorderPoint: null,
    }
    expect(gaugeFill({ ...level, ...evaluateStock(level) })).toBeCloseTo(1 / 3)
  })

  it('ticks at the reorder point', () => {
    expect(gaugeTick(count('4', '12', '3'))).toBe(0.25)
    expect(gaugeTick(count('4', '12'))).toBeNull()
  })
})

describe('renew offer', () => {
  const today = todayEpoch()
  function count(quantity: string | null, overrides: Partial<ExpiryInput> = {}): ExpiryInput {
    return {
      stockMode: 'count',
      stockLevel: null,
      quantity,
      expiresAt: addDays(today, 2),
      shelfLifeDays: 7,
      ...overrides,
    }
  }

  it('offers the renewed date after a raise onto stock already there', () => {
    expect(renewOffer(count('4'), count('5'), today)).toBe(addDays(today, 7))
  })

  it('offers nothing after a raise from empty, which renews on its own', () => {
    expect(renewOffer(count('0'), count('5'), today)).toBeNull()
    expect(renewOffer(count(null), count('5'), today)).toBeNull()
  })

  it('offers nothing after a step down', () => {
    expect(renewOffer(count('5'), count('4'), today)).toBeNull()
  })

  it('offers nothing without a shelf life', () => {
    expect(
      renewOffer(count('4', { shelfLifeDays: null }), count('5', { shelfLifeDays: null }), today),
    ).toBeNull()
  })

  it('offers nothing when the date is already as late', () => {
    const late = { expiresAt: addDays(today, 7) }
    expect(renewOffer(count('4', late), count('5', late), today)).toBeNull()
  })

  it('offers a date to a level item raised from Low', () => {
    const level = (stockLevel: 'low' | 'full' | 'out') =>
      count(null, { stockMode: 'level', stockLevel })
    expect(renewOffer(level('low'), level('full'), today)).toBe(addDays(today, 7))
    expect(renewOffer(level('out'), level('low'), today)).toBeNull()
  })
})
