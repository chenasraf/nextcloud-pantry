import { t } from '@nextcloud/l10n'
import type { StockLevel, StockStatus } from '@/api/types'

export function stockLevelLabel(level: StockLevel): string {
  switch (level) {
    case 'low':
      // TRANSLATORS: Adjective, a stock level: some left, time to buy more.
      return t('pantry', 'Low')
    case 'out':
      // TRANSLATORS: Adjective, a stock level: none left.
      return t('pantry', 'Out')
    default:
      // TRANSLATORS: Adjective, a stock level: fully stocked.
      return t('pantry', 'Full')
  }
}

/**
 * The status as a row's chip and status icon spell it out: "Low · buy 8 eggs",
 * where the amount is what the shopping list asks for.
 */
export function stockStatusText(status: StockStatus, shortfall: string | null): string {
  switch (status) {
    case 'low':
      return shortfall
        ? // TRANSLATORS: Chip on an inventory item that is running low. {amount} is what to buy, e.g. "8 eggs".
          t('pantry', 'Low · buy {amount}', { amount: shortfall })
        : stockLevelLabel('low')
    case 'out':
      return shortfall
        ? // TRANSLATORS: Chip on an inventory item with none left. {amount} is what to buy, e.g. "2 blocks".
          t('pantry', 'Out · buy {amount}', { amount: shortfall })
        : stockLevelLabel('out')
    case 'uncounted':
      // TRANSLATORS: Chip on an inventory item whose amount on hand has no number, e.g. "some".
      return t('pantry', 'Not counted')
    default:
      return t('pantry', 'In stock')
  }
}
