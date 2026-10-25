import { beforeEach, describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { RestockSyncEvent } from '@/api/types'

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

import { announceRestockSync, restockSyncText } from './restockToasts'

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
