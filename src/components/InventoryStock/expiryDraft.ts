import type { ItemInput } from '@/api/lists'
import { addDays, daysUntil } from '@/utils/stock'

export const MAX_SHELF_LIFE_DAYS = 36500

export type ExpiryMode = 'none' | 'date' | 'days'

/** The Expiry card's state. `expiresAt` is a midnight timestamp in seconds. */
export interface ExpiryDraft {
  mode: ExpiryMode
  expiresAt: number | null
  shelfLifeDays: number | null
}

export interface StoredExpiry {
  expiresAt: number | null
  shelfLifeDays: number | null
}

/** A picked day as the local midnight timestamp the server stores. */
export function dateToEpoch(value: Date | null): number | null {
  if (!value) return null
  return Math.floor(
    new Date(value.getFullYear(), value.getMonth(), value.getDate()).getTime() / 1000,
  )
}

/** A typed shelf life, or null when it is not a positive whole number of days. */
export function parseShelfLife(value: string): number | null {
  const days = Number.parseInt(value, 10)
  return Number.isFinite(days) && days > 0 ? Math.min(days, MAX_SHELF_LIFE_DAYS) : null
}

/**
 * What a new item sends: a picked date or a shelf life, which the server turns
 * into a date of today plus that many days. A template keeps only the shelf life.
 */
export function newItemExpiry(
  draft: ExpiryDraft,
  shelfLifeOnly = false,
): Pick<ItemInput, 'expiresAt' | 'shelfLifeDays'> {
  if (draft.mode === 'days' && draft.shelfLifeDays) return { shelfLifeDays: draft.shelfLifeDays }
  if (draft.mode === 'date' && draft.expiresAt !== null && !shelfLifeOnly) {
    return { expiresAt: draft.expiresAt }
  }
  return {}
}

/** The card opens on how the item's expiry was entered. */
export function draftFromExpiry(stored: StoredExpiry): ExpiryDraft {
  const mode: ExpiryMode =
    stored.shelfLifeDays !== null ? 'days' : stored.expiresAt !== null ? 'date' : 'none'
  return { mode, ...stored }
}

/**
 * The date and shelf life the draft leaves the item with once saved, the way
 * the server applies them: a shelf life of N days expires N days from today,
 * and a date after today sets the shelf life to match.
 */
export function previewExpiry(
  draft: ExpiryDraft,
  stored: StoredExpiry,
  today: number,
): StoredExpiry {
  switch (draft.mode) {
    case 'none':
      return { expiresAt: null, shelfLifeDays: null }
    case 'days':
      if (draft.shelfLifeDays === stored.shelfLifeDays) return stored
      return draft.shelfLifeDays
        ? { expiresAt: addDays(today, draft.shelfLifeDays), shelfLifeDays: draft.shelfLifeDays }
        : { expiresAt: stored.expiresAt, shelfLifeDays: null }
    default: {
      if (draft.expiresAt === stored.expiresAt) return stored
      const daysLeft = draft.expiresAt !== null ? daysUntil(draft.expiresAt, today) : 0
      return {
        expiresAt: draft.expiresAt,
        shelfLifeDays: daysLeft > 0 ? daysLeft : stored.shelfLifeDays,
      }
    }
  }
}

/**
 * Only what the member changed, so opening and saving the dialog never
 * renews a date on its own. 0 clears a value.
 */
export function expiryPatch(
  draft: ExpiryDraft,
  stored: StoredExpiry,
): Pick<ItemInput, 'expiresAt' | 'shelfLifeDays'> {
  switch (draft.mode) {
    case 'none':
      return {
        ...(stored.expiresAt !== null ? { expiresAt: 0 } : {}),
        ...(stored.shelfLifeDays !== null ? { shelfLifeDays: 0 } : {}),
      }
    case 'days': {
      const days = draft.shelfLifeDays ?? 0
      if (days === (stored.shelfLifeDays ?? 0)) return {}
      return { shelfLifeDays: days }
    }
    default:
      return draft.expiresAt === stored.expiresAt ? {} : { expiresAt: draft.expiresAt ?? 0 }
  }
}
