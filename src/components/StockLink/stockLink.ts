import { n, t } from '@nextcloud/l10n'
import type { ChecklistItem, RestockEntry, StockLinkRow, StockLinkSource } from '@/api/types'

/** Names are shown as text, never as HTML, so placeholders are not escaped. */
const RAW = { escape: false }

/**
 * How an inventory item stands with the shopping list:
 * - `linked`: a checklist item restocks it.
 * - `pick`: several rows match its name, so nothing links until a member picks one.
 * - `auto`: unlinked, and linked by name when a matching row turns up.
 * - `off`: unlinked with auto-link off, so it never reaches a shopping list.
 */
export type StockLinkState = 'linked' | 'pick' | 'auto' | 'off'

export function stockLinkState(item: ChecklistItem): StockLinkState {
  if (item.stockLink) return 'linked'
  if ((item.linkCandidates?.length ?? 0) > 1) return 'pick'
  return item.autoLink === false ? 'off' : 'auto'
}

/**
 * A translated sentence split around the one placeholder shown bold, so the
 * translation stays one string while the template styles that part.
 */
export function splitAround(sentence: string, marker: string): [string, string] {
  const [before, after = ''] = sentence.split(marker)
  return [before, after]
}

/** What a linked row's state reads as, e.g. "checked" or "× 2". */
export function rowStateText(row: StockLinkRow): string {
  switch (row.state) {
    case 'checked':
      // TRANSLATORS: State of the checklist item an inventory item is linked to: it is checked off.
      return t('pantry', 'checked')
    case 'archived':
      // TRANSLATORS: State of the checklist item an inventory item is linked to: it is archived.
      return t('pantry', 'archived')
    case 'trashed':
      // TRANSLATORS: State of the checklist item an inventory item is linked to: it is in the trash.
      return t('pantry', 'in the trash')
    default:
      return row.quantity
        ? // TRANSLATORS: Quantity of the open checklist item an inventory item is linked to, e.g. "× 2".
          t('pantry', '× {quantity}', { quantity: row.quantity }, RAW)
        : // TRANSLATORS: State of the checklist item an inventory item is linked to: it is unchecked.
          t('pantry', 'open')
  }
}

/** The popover's line for a linked row, split around its bold name: "**Milk** on Groceries · × 2". */
export function linkedRowParts(row: StockLinkRow): [string, string] {
  return splitAround(
    // TRANSLATORS: Shopping list popover of an inventory item. {row} is the linked checklist item, shown bold, e.g. "Milk on Groceries · × 2".
    t(
      'pantry',
      '{row} on {checklist} · {state}',
      { row: '{row}', checklist: row.listName, state: rowStateText(row) },
      RAW,
    ),
    '{row}',
  )
}

/** The dialog card's line for a linked row, split around its bold name: "Linked to **Milk** on Groceries · × 2". */
export function linkedToParts(row: StockLinkRow): [string, string] {
  return splitAround(
    // TRANSLATORS: Shopping list card of an inventory item. {row} is the linked checklist item, shown bold, e.g. "Linked to Milk on Groceries · × 2".
    t(
      'pantry',
      'Linked to {row} on {checklist} · {state}',
      { row: '{row}', checklist: row.listName, state: rowStateText(row) },
      RAW,
    ),
    '{row}',
  )
}

/** A candidate to pick, e.g. "Milk, Groceries, checked". */
export function candidateText(row: StockLinkRow): string {
  // TRANSLATORS: A checklist item to pick for linking, with its checklist and state, e.g. "Milk, Groceries, checked".
  return t(
    'pantry',
    '{row}, {checklist}, {state}',
    { row: row.name, checklist: row.listName, state: rowStateText(row) },
    RAW,
  )
}

/** A row as a picker option reads it: "Milk — Groceries", archived ones marked. */
export function rowOptionLabel(row: StockLinkRow): string {
  const vars = { row: row.name, checklist: row.listName }
  return row.state === 'archived'
    ? // TRANSLATORS: Option when linking an inventory item by hand: an archived checklist item and its checklist, e.g. "Milk — Groceries (archived)".
      t('pantry', '{row} — {checklist} (archived)', vars, RAW)
    : // TRANSLATORS: Option when linking an inventory item by hand: a checklist item and its checklist, e.g. "Milk — Groceries".
      t('pantry', '{row} — {checklist}', vars, RAW)
}

/** The link button's tooltip and accessible name, per state. */
export function stockLinkTooltip(item: ChecklistItem): string {
  const row = item.stockLink
  switch (stockLinkState(item)) {
    case 'linked':
      return t(
        'pantry',
        'Linked to {row} on {checklist}',
        { row: row!.name, checklist: row!.listName },
        RAW,
      )
    case 'pick':
      return n(
        'pantry',
        'Not linked: %n match, pick one',
        'Not linked: %n matches, pick one',
        item.linkCandidates?.length ?? 0,
      )
    case 'auto':
      return t('pantry', 'Not linked yet: added to the shopping list when low')
    default:
      return t('pantry', 'Not linked: never added to a shopping list')
  }
}

/** The checklist row's marker tooltip, e.g. "Restocks Eggs (Kitchen), Soda (Garage)". */
export function restocksTooltip(sources: StockLinkSource[]): string {
  const names = sources
    .map((s) =>
      // TRANSLATORS: One inventory item a checklist item restocks, with its inventory, e.g. "Eggs (Kitchen)".
      t('pantry', '{item} ({inventory})', { item: s.name, inventory: s.listName }, RAW),
    )
    .join(', ')
  // TRANSLATORS: Tooltip on a checklist item linked to inventory items. {items} is a comma-separated list such as "Eggs (Kitchen), Soda (Garage)".
  return t('pantry', 'Restocks {items}', { items: names }, RAW)
}

/** What a tick put back into stock: "Eggs (Kitchen) +8, Milk (Kitchen) to Full". */
function restockedParts(entries: RestockEntry[]): string {
  return entries
    .map((e) => {
      const vars = { item: e.itemName, inventory: e.listName, amount: e.added ?? '' }
      return e.added !== null
        ? // TRANSLATORS: One inventory item a checked-off item restocked and how much it added, e.g. "Eggs (Kitchen) +8".
          t('pantry', '{item} ({inventory}) +{amount}', vars, RAW)
        : // TRANSLATORS: One inventory item a checked-off item took to the Full stock level, e.g. "Milk (Kitchen) to Full".
          t('pantry', '{item} ({inventory}) to Full', vars, RAW)
    })
    .join(', ')
}

/** "Restocked Eggs (Kitchen) +8", as the toast and the checked item's line read it. */
export function restockedText(entries: RestockEntry[]): string {
  // TRANSLATORS: {restock} lists what a checked-off item put back into stock, e.g. "Restocked Eggs (Kitchen) +8, Milk (Kitchen) to Full".
  return t('pantry', 'Restocked {restock}', { restock: restockedParts(entries) }, RAW)
}

/** "Undid: restocked Eggs (Kitchen) +8", after a member's uncheck took it back out. */
export function restockUndoneText(entries: RestockEntry[]): string {
  // TRANSLATORS: Toast after unchecking an item took back what its check put into stock, e.g. "Undid: restocked Eggs (Kitchen) +8".
  return t('pantry', 'Undid: restocked {restock}', { restock: restockedParts(entries) }, RAW)
}

export const linkToasts = {
  linked: (item: string, row: StockLinkRow) =>
    t(
      'pantry',
      'Linked {item} to {row} on {checklist}',
      { item, row: row.name, checklist: row.listName },
      RAW,
    ),
  unlinked: (item: string, row: StockLinkRow) =>
    t(
      'pantry',
      'Unlinked {item} from {row} on {checklist}. It will not be added to a shopping list.',
      { item, row: row.name, checklist: row.listName },
      RAW,
    ),
  severalMatches: (item: string, count: number, checklist: string) =>
    n(
      'pantry',
      '{item} matches %n item on {checklist}. Pick one to link.',
      '{item} matches %n items on {checklist}. Pick one to link.',
      count,
      { item, checklist },
      RAW,
    ),
}
