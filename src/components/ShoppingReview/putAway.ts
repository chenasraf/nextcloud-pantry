import { firstNumber } from '@/utils/stock'
import type { ChecklistItem, PutAwayEntry, PutAwayStorage } from '@/api/types'

/** Item id → where it is put away, as the put-away section edits it. */
export type PutAwayDraft = Record<number, PutAwayStorage[]>

/**
 * Where the tick put the item, from its restock record: an amount per
 * inventory, or null for a level item it took to Full.
 */
export function putAwayFromRecord(item: ChecklistItem): PutAwayStorage[] {
  const out: PutAwayStorage[] = []
  for (const entry of item.restock ?? []) {
    if (entry.added === null && entry.previousLevel === null) continue // only renewed a date
    const amount = entry.added !== null ? firstNumber(entry.added) : null
    const existing = out.find((s) => s.listId === entry.listId)
    if (existing) {
      existing.amount =
        existing.amount === null || amount === null ? null : existing.amount + amount
    } else {
      out.push({ listId: entry.listId, amount })
    }
  }
  return out
}

/** A draft seeded with where each item's tick put it. */
export function putAwayDraft(items: ChecklistItem[]): PutAwayDraft {
  return Object.fromEntries(items.map((item) => [item.id, putAwayFromRecord(item)]))
}

/** How much of the item there is still to put away: what was bought less what is placed. */
export function amountLeft(item: ChecklistItem, storages: PutAwayStorage[]): number {
  const bought = firstNumber(item.quantity) ?? 1
  const placed = storages.reduce((sum, s) => sum + (s.amount ?? 0), 0)
  return Math.max(0, bought - placed)
}

function sameStorages(a: PutAwayStorage[], b: PutAwayStorage[]): boolean {
  const key = (list: PutAwayStorage[]) =>
    list
      .map((s) => `${s.listId}:${s.amount ?? 'full'}`)
      .sort()
      .join('|')
  return key(a) === key(b)
}

/**
 * The entries to send: only items whose storages differ from where their tick
 * put them, so an untouched item keeps exactly what was restocked.
 */
export function changedPutAway(items: ChecklistItem[], draft: PutAwayDraft): PutAwayEntry[] {
  return items
    .filter((item) => draft[item.id] && !sameStorages(draft[item.id], putAwayFromRecord(item)))
    .map((item) => ({ itemId: item.id, storages: draft[item.id] }))
}
