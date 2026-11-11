import { describe, expect, it } from 'vitest'
import type { ChecklistItem, RestockEntry } from '@/api/types'
import { amountLeft, changedPutAway, putAwayDraft, putAwayFromRecord } from './putAway'

function entry(
  listId: number,
  added: string | null,
  previousLevel: 'low' | null = null,
): RestockEntry {
  return {
    itemId: 100 + listId,
    listId,
    itemName: 'Nuggets',
    listName: `List ${listId}`,
    added,
    previousLevel,
    replaced: false,
    previousOnHand: null,
    previousExpiresAt: null,
    expiresAt: null,
  }
}

function item(id: number, quantity: string | null, restock: RestockEntry[] | null): ChecklistItem {
  return { id, name: `Item ${id}`, quantity, restock } as unknown as ChecklistItem
}

describe('putAwayFromRecord', () => {
  it('reads where the tick put the item, one amount per inventory', () => {
    const record = [entry(20, '6'), entry(21, '4'), entry(20, '1')]

    expect(putAwayFromRecord(item(1, '11', record))).toEqual([
      { listId: 20, amount: 7 },
      { listId: 21, amount: 4 },
    ])
  })

  it('reads a level item taken to Full as no amount, and skips a date-only renewal', () => {
    const record = [entry(20, null, 'low'), entry(21, null)]

    expect(putAwayFromRecord(item(1, null, record))).toEqual([{ listId: 20, amount: null }])
  })

  it('is empty when the tick restocked nothing', () => {
    expect(putAwayFromRecord(item(1, '2', null))).toEqual([])
  })
})

describe('amountLeft', () => {
  it('is what was bought less what is already placed', () => {
    expect(amountLeft(item(1, '10 nuggets', null), [{ listId: 20, amount: 6 }])).toBe(4)
    expect(amountLeft(item(1, '10', null), [{ listId: 20, amount: 12 }])).toBe(0)
  })

  it('counts one when the quantity has no number', () => {
    expect(amountLeft(item(1, null, null), [])).toBe(1)
  })
})

describe('changedPutAway', () => {
  const items = [
    item(1, '10', [entry(20, '10')]),
    item(2, '2', null),
    item(3, '1', [entry(21, '1')]),
  ]

  it('sends nothing when every item stays where its tick put it', () => {
    expect(changedPutAway(items, putAwayDraft(items))).toEqual([])
  })

  it('sends only the items whose storages changed, including an emptied one', () => {
    const draft = {
      ...putAwayDraft(items),
      1: [
        { listId: 20, amount: 6 },
        { listId: 21, amount: 4 },
      ],
      2: [{ listId: 20, amount: 2 }],
      3: [],
    }

    expect(changedPutAway(items, draft)).toEqual([
      {
        itemId: 1,
        storages: [
          { listId: 20, amount: 6 },
          { listId: 21, amount: 4 },
        ],
      },
      { itemId: 2, storages: [{ listId: 20, amount: 2 }] },
      { itemId: 3, storages: [] },
    ])
  })

  it('ignores the order storages were listed in', () => {
    const split = [entry(20, '6'), entry(21, '4')]
    const draft = {
      1: [
        { listId: 21, amount: 4 },
        { listId: 20, amount: 6 },
      ],
    }

    expect(changedPutAway([item(1, '10', split)], draft)).toEqual([])
  })
})
