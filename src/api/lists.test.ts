import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/axios', () => ({ ocs: { get } }))

import { listItems, listAllItems } from './lists'
import type { ChecklistItem } from './types'

function makeItems(count: number, startId = 0): ChecklistItem[] {
  return Array.from({ length: count }, (_, i) => ({ id: startId + i }) as unknown as ChecklistItem)
}

const PAGE_SIZE = 500

describe('item pagination', () => {
  beforeEach(() => get.mockReset())
  afterEach(() => vi.restoreAllMocks())

  it('returns a single short page without a second request (listItems)', async () => {
    get.mockResolvedValueOnce({ data: makeItems(10) })

    const items = await listItems(1, 2, 'custom')

    expect(items).toHaveLength(10)
    expect(get).toHaveBeenCalledTimes(1)
    expect(get).toHaveBeenCalledWith('/houses/1/lists/2/items', {
      params: { sortBy: 'custom', limit: PAGE_SIZE, offset: 0 },
    })
  })

  it('pages until a short page returns and concatenates all items (listItems)', async () => {
    get
      .mockResolvedValueOnce({ data: makeItems(PAGE_SIZE, 0) })
      .mockResolvedValueOnce({ data: makeItems(PAGE_SIZE, PAGE_SIZE) })
      .mockResolvedValueOnce({ data: makeItems(20, PAGE_SIZE * 2) })

    const items = await listItems(1, 2)

    expect(items).toHaveLength(PAGE_SIZE * 2 + 20)
    expect(get).toHaveBeenCalledTimes(3)
    expect(get).toHaveBeenNthCalledWith(2, '/houses/1/lists/2/items', {
      params: { limit: PAGE_SIZE, offset: PAGE_SIZE },
    })
    expect(get).toHaveBeenNthCalledWith(3, '/houses/1/lists/2/items', {
      params: { limit: PAGE_SIZE, offset: PAGE_SIZE * 2 },
    })
  })

  it('stops after a full final page returns an empty page', async () => {
    get.mockResolvedValueOnce({ data: makeItems(PAGE_SIZE, 0) }).mockResolvedValueOnce({ data: [] })

    const items = await listAllItems(1, 'newest')

    expect(items).toHaveLength(PAGE_SIZE)
    expect(get).toHaveBeenCalledTimes(2)
    expect(get).toHaveBeenNthCalledWith(1, '/houses/1/items', {
      params: { sortBy: 'newest', limit: PAGE_SIZE, offset: 0 },
    })
  })

  it('pages the house-wide "All lists" endpoint (listAllItems)', async () => {
    get
      .mockResolvedValueOnce({ data: makeItems(PAGE_SIZE, 0) })
      .mockResolvedValueOnce({ data: makeItems(5, PAGE_SIZE) })

    const items = await listAllItems(1)

    expect(items).toHaveLength(PAGE_SIZE + 5)
    expect(get).toHaveBeenCalledTimes(2)
    expect(get).toHaveBeenNthCalledWith(1, '/houses/1/items', {
      params: { limit: PAGE_SIZE, offset: 0 },
    })
  })
})
