import { describe, expect, it } from 'vitest'

import type { ChecklistItem, StockStatus } from '@/api/types'
import {
  STOCK_IN_STOCK,
  STOCK_NEEDS_RESTOCK,
  matchesStockFilter,
  stockFilterFromQuery,
  stockFilterQuery,
} from './stockFilter'

function item(stockStatus: StockStatus): ChecklistItem {
  return { stockStatus } as ChecklistItem
}

describe('stock filter', () => {
  it('shows everything with nothing or both rows selected', () => {
    for (const selected of [[], [STOCK_NEEDS_RESTOCK, STOCK_IN_STOCK]]) {
      expect(matchesStockFilter(item('out'), selected)).toBe(true)
      expect(matchesStockFilter(item('ok'), selected)).toBe(true)
    }
  })

  it('counts low and out as needing restock, and the rest as in stock', () => {
    const restock = [STOCK_NEEDS_RESTOCK]
    expect(matchesStockFilter(item('low'), restock)).toBe(true)
    expect(matchesStockFilter(item('out'), restock)).toBe(true)
    expect(matchesStockFilter(item('ok'), restock)).toBe(false)
    expect(matchesStockFilter(item('uncounted'), restock)).toBe(false)
    expect(matchesStockFilter(item('uncounted'), [STOCK_IN_STOCK])).toBe(true)
  })

  it('reads and writes the stock query value', () => {
    expect(stockFilterFromQuery('restock')).toEqual([STOCK_NEEDS_RESTOCK])
    expect(stockFilterFromQuery(['in-stock'])).toEqual([STOCK_IN_STOCK])
    expect(stockFilterFromQuery('nonsense')).toEqual([])
    expect(stockFilterFromQuery(undefined)).toEqual([])
    expect(stockFilterQuery([STOCK_NEEDS_RESTOCK])).toBe('restock')
    expect(stockFilterQuery([STOCK_NEEDS_RESTOCK, STOCK_IN_STOCK])).toBeUndefined()
    expect(stockFilterQuery([])).toBeUndefined()
  })
})
