import { beforeEach, describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { RestockEntry, RestockSyncEvent } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
const { hide, showSuccess, showInfo } = vi.hoisted(() => {
  const hide = vi.fn()
  return {
    hide,
    showSuccess: vi.fn(() => ({ hideToast: hide })),
    showInfo: vi.fn(() => ({ hideToast: hide })),
  }
})
vi.mock('@nextcloud/dialogs', () => ({ showSuccess, showInfo }))

import {
  announceRestock,
  announceRestockSync,
  restockedText,
  restockSyncText,
  restockUndoneText,
} from './restockToasts'

function event(overrides: Partial<RestockSyncEvent> = {}): RestockSyncEvent {
  return {
    event: 'added',
    itemId: 3,
    rowId: 50,
    rowName: 'Eggs',
    quantity: '8 eggs',
    listId: 10,
    listName: 'Groceries',
    ...overrides,
  }
}

describe('restock toasts', () => {
  beforeEach(() => vi.clearAllMocks())

  it.each([
    [event(), 'Added 8 eggs to Groceries'],
    [event({ quantity: null }), 'Added Eggs to Groceries'],
    [event({ event: 'revived' }), 'Back on Groceries: Eggs × 8 eggs'],
    [event({ event: 'revived', quantity: null }), 'Back on Groceries: Eggs'],
    [event({ event: 'changed', quantity: '10 eggs' }), 'Groceries: Eggs now × 10 eggs'],
    [
      event({ event: 'pinned', quantity: '2 dozen' }),
      'Eggs on Groceries was set by hand, left at 2 dozen',
    ],
    [event({ event: 'recovered' }), 'No longer needed: Eggs on Groceries'],
  ])('words what the sync did', (e, text) => {
    expect(restockSyncText(e)).toBe(text)
  })

  it('leaves names unescaped, since they are shown as text', () => {
    expect(restockSyncText(event({ listName: 'Fruit & Veg' }))).toBe('Added 8 eggs to Fruit & Veg')
  })

  it('cheers what was added or put back, and informs of the rest', () => {
    announceRestockSync([event({ rowId: 1 }), event({ rowId: 2, event: 'revived' })])
    announceRestockSync([
      event({ rowId: 3, event: 'changed' }),
      event({ rowId: 4, event: 'recovered' }),
    ])

    expect(showSuccess).toHaveBeenCalledTimes(2)
    expect(showInfo).toHaveBeenCalledTimes(2)
  })

  it('replaces the toast already on screen about the same item', () => {
    announceRestockSync([event({ rowId: 7, event: 'changed', quantity: '9 eggs' })])
    announceRestockSync([event({ rowId: 7, event: 'changed', quantity: '10 eggs' })])

    expect(hide).toHaveBeenCalledTimes(1)
    expect(showInfo).toHaveBeenLastCalledWith('Groceries: Eggs now × 10 eggs')
  })

  it('shows nothing for a write that synced nothing', () => {
    announceRestockSync(undefined)
    announceRestockSync([])

    expect(showSuccess).not.toHaveBeenCalled()
    expect(showInfo).not.toHaveBeenCalled()
  })
})

function entry(overrides: Partial<RestockEntry> = {}): RestockEntry {
  return {
    itemId: 3,
    listId: 20,
    itemName: 'Eggs',
    listName: 'Kitchen',
    added: '8',
    previousLevel: null,
    replaced: false,
    previousOnHand: null,
    previousExpiresAt: null,
    expiresAt: null,
    ...overrides,
  }
}

describe('restock feedback', () => {
  beforeEach(() => vi.clearAllMocks())

  it('says what a tick put back into stock', () => {
    const milk = entry({ itemId: 4, itemName: 'Milk', added: null, previousLevel: 'low' })

    expect(restockedText([entry(), milk])).toBe(
      'Restocked Eggs (Kitchen) +8, Milk (Kitchen) to Full',
    )
  })

  it('says what an uncheck took back out', () => {
    expect(restockUndoneText([entry()])).toBe('Undid: restocked Eggs (Kitchen) +8')
  })

  it('shows a success toast for a tick and an info toast for an uncheck', () => {
    announceRestock({ restocked: [entry()] })
    announceRestock({ restockUndone: [entry()] })

    expect(showSuccess).toHaveBeenCalledWith('Restocked Eggs (Kitchen) +8')
    expect(showInfo).toHaveBeenCalledWith('Undid: restocked Eggs (Kitchen) +8')
  })

  it('tells what the sync did after it', () => {
    announceRestock({ restocked: [entry()], restockSync: [event({ event: 'revived' })] })

    expect(showSuccess).toHaveBeenCalledTimes(2)
    expect(showSuccess).toHaveBeenLastCalledWith('Back on Groceries: Eggs × 8 eggs')
  })

  it('says what a tick replaced', () => {
    const milk = entry({ itemId: 4, itemName: 'Milk', added: '2', replaced: true })
    const cream = entry({
      itemId: 5,
      itemName: 'Cream',
      added: null,
      previousLevel: 'low',
      replaced: true,
    })

    expect(restockedText([entry(), milk])).toBe(
      'Restocked Eggs (Kitchen) +8, replaced Milk (Kitchen) ×2',
    )
    expect(restockedText([milk, cream])).toBe('Replaced Milk (Kitchen) ×2, Cream (Kitchen), Full')
    expect(restockUndoneText([milk])).toBe('Undid: replaced Milk (Kitchen) ×2')
  })

  it('leaves out items whose date alone was renewed', () => {
    const dateOnly = entry({ itemId: 6, itemName: 'Rice', added: null })

    expect(restockedText([entry(), dateOnly])).toBe('Restocked Eggs (Kitchen) +8')
    expect(restockedText([dateOnly])).toBeNull()
  })

  it('shows no toast for a tick that only renewed a date', () => {
    announceRestock({ restocked: [entry({ added: null })] })

    expect(showSuccess).not.toHaveBeenCalled()
  })

  it('says nothing when stock was left alone', () => {
    announceRestock({ restocked: [], restockUndone: [] })

    expect(showSuccess).not.toHaveBeenCalled()
    expect(showInfo).not.toHaveBeenCalled()
  })
})
