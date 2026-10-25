import { t } from '@nextcloud/l10n'
import { showInfo, showSuccess, type ToastHandle } from '@nextcloud/dialogs'
import type { RestockSyncEvent } from '@/api/types'

/** Names are shown as text, never as HTML, so placeholders are not escaped. */
const RAW = { escape: false }

/** What a restock sync did, as its toast reads it. */
export function restockSyncText(e: RestockSyncEvent): string {
  const vars = { row: e.rowName, checklist: e.listName, quantity: e.quantity ?? '' }
  switch (e.event) {
    case 'added':
      return e.quantity
        ? // TRANSLATORS: Toast after low stock added a checklist item. {quantity} is what it asks for, e.g. "Added 8 eggs to Groceries".
          t('pantry', 'Added {quantity} to {checklist}', vars, RAW)
        : // TRANSLATORS: Toast after low stock added a checklist item without a quantity, e.g. "Added Eggs to Groceries".
          t('pantry', 'Added {row} to {checklist}', vars, RAW)
    case 'revived':
      return e.quantity
        ? // TRANSLATORS: Toast after low stock unchecked a checklist item, e.g. "Back on Groceries: Eggs × 8 eggs".
          t('pantry', 'Back on {checklist}: {row} × {quantity}', vars, RAW)
        : // TRANSLATORS: Toast after low stock unchecked a checklist item without a quantity, e.g. "Back on Groceries: Eggs".
          t('pantry', 'Back on {checklist}: {row}', vars, RAW)
    case 'changed':
      // TRANSLATORS: Toast after stock changed what a checklist item asks for, e.g. "Groceries: Eggs now × 10 eggs".
      return t('pantry', '{checklist}: {row} now × {quantity}', vars, RAW)
    case 'pinned':
      return e.quantity
        ? // TRANSLATORS: Toast when stock would change a checklist item whose quantity a member set, e.g. "Eggs on Groceries was set by hand, left at 2 dozen".
          t('pantry', '{row} on {checklist} was set by hand, left at {quantity}', vars, RAW)
        : // TRANSLATORS: Toast when stock would change a checklist item whose quantity a member cleared by hand, e.g. "Eggs on Groceries was set by hand, left as it is".
          t('pantry', '{row} on {checklist} was set by hand, left as it is', vars, RAW)
    case 'recovered':
      // TRANSLATORS: Toast after stock recovered and a checklist item was ticked off, e.g. "No longer needed: Eggs on Groceries".
      return t('pantry', 'No longer needed: {row} on {checklist}', vars, RAW)
  }
}

/** The toast on screen for each checklist item, so a burst of stock steps shows only the latest. */
const shown = new Map<number, ToastHandle>()

/**
 * Tell the member what their write did to checklist items. A new toast about
 * an item replaces the one already on screen about it.
 */
export function announceRestockSync(events: RestockSyncEvent[] | undefined): void {
  for (const e of events ?? []) {
    shown.get(e.rowId)?.hideToast()
    const text = restockSyncText(e)
    const toast = e.event === 'added' || e.event === 'revived' ? showSuccess(text) : showInfo(text)
    shown.set(e.rowId, toast)
  }
}
