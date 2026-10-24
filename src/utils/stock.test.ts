import { describe, expect, it } from 'vitest'

import vectors from '../../tests/stock-vectors.json'
import { canStepDown, stepQuantity } from './quantity'
import {
  convertStock,
  evaluateStock,
  firstNumber,
  gaugeFill,
  gaugeTick,
  startingOnHand,
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
