import { describe, expect, it } from 'vitest'
import { addDays, todayEpoch } from '@/utils/stock'
import {
  dateToEpoch,
  draftFromExpiry,
  expiryPatch,
  newItemExpiry,
  parseShelfLife,
  previewExpiry,
} from './expiryDraft'

const today = todayEpoch()
const inDays = (days: number) => addDays(today, days)

describe('expiry draft', () => {
  it('opens on how the expiry was entered', () => {
    expect(draftFromExpiry({ expiresAt: inDays(3), shelfLifeDays: 7 }).mode).toBe('days')
    expect(draftFromExpiry({ expiresAt: inDays(3), shelfLifeDays: null }).mode).toBe('date')
    expect(draftFromExpiry({ expiresAt: null, shelfLifeDays: null }).mode).toBe('none')
  })

  it('sends nothing when left as it was, so the date is not renewed', () => {
    const stored = { expiresAt: inDays(3), shelfLifeDays: 7 }
    expect(expiryPatch(draftFromExpiry(stored), stored)).toEqual({})
  })

  it('sends a changed shelf life on its own', () => {
    const stored = { expiresAt: inDays(3), shelfLifeDays: 7 }
    expect(expiryPatch({ ...draftFromExpiry(stored), shelfLifeDays: 10 }, stored)).toEqual({
      shelfLifeDays: 10,
    })
  })

  it('sends a picked date on its own, so the server matches the shelf life', () => {
    const stored = { expiresAt: inDays(3), shelfLifeDays: 7 }
    expect(expiryPatch({ mode: 'date', expiresAt: inDays(5), shelfLifeDays: 7 }, stored)).toEqual({
      expiresAt: inDays(5),
    })
  })

  it('clears what is stored for None', () => {
    expect(
      expiryPatch(
        { mode: 'none', expiresAt: inDays(3), shelfLifeDays: 7 },
        { expiresAt: inDays(3), shelfLifeDays: 7 },
      ),
    ).toEqual({ expiresAt: 0, shelfLifeDays: 0 })
    expect(
      expiryPatch(
        { mode: 'none', expiresAt: null, shelfLifeDays: null },
        { expiresAt: null, shelfLifeDays: null },
      ),
    ).toEqual({})
  })

  it('previews Keeps N days as N days from today', () => {
    const stored = { expiresAt: inDays(1), shelfLifeDays: null }
    expect(
      previewExpiry({ mode: 'days', expiresAt: inDays(1), shelfLifeDays: 4 }, stored, today),
    ).toEqual({
      expiresAt: inDays(4),
      shelfLifeDays: 4,
    })
  })

  it('previews an untouched shelf life as the stored date, since saving sends nothing', () => {
    const stored = { expiresAt: inDays(1), shelfLifeDays: 5 }
    expect(previewExpiry(draftFromExpiry(stored), stored, today)).toEqual(stored)
  })

  it('sends a new item its date or its shelf life', () => {
    expect(newItemExpiry({ mode: 'date', expiresAt: inDays(5), shelfLifeDays: 7 })).toEqual({
      expiresAt: inDays(5),
    })
    expect(newItemExpiry({ mode: 'days', expiresAt: inDays(5), shelfLifeDays: 7 })).toEqual({
      shelfLifeDays: 7,
    })
    expect(
      newItemExpiry({ mode: 'date', expiresAt: inDays(5), shelfLifeDays: null }, true),
    ).toEqual({})
    expect(newItemExpiry({ mode: 'none', expiresAt: inDays(5), shelfLifeDays: 7 })).toEqual({})
  })

  it('reads a picked day as its local midnight and a typed shelf life as whole days', () => {
    expect(dateToEpoch(new Date(2026, 9, 25, 15, 30))).toBe(new Date(2026, 9, 25).getTime() / 1000)
    expect(parseShelfLife('7')).toBe(7)
    expect(parseShelfLife('0')).toBeNull()
    expect(parseShelfLife('')).toBeNull()
    expect(parseShelfLife('99999')).toBe(36500)
  })

  it('previews a future date as setting the shelf life to match', () => {
    const stored = { expiresAt: null, shelfLifeDays: 30 }
    expect(
      previewExpiry({ mode: 'date', expiresAt: inDays(6), shelfLifeDays: 30 }, stored, today),
    ).toEqual({
      expiresAt: inDays(6),
      shelfLifeDays: 6,
    })
    expect(
      previewExpiry({ mode: 'date', expiresAt: inDays(-1), shelfLifeDays: 30 }, stored, today)
        .shelfLifeDays,
    ).toBe(30)
  })
})
