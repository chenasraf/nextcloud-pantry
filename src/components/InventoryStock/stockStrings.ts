import { n, t } from '@nextcloud/l10n'
import type { ExpiryStatus, StockLevel, StockStatus } from '@/api/types'
import { formatShortDate } from '@/utils/datetime'
import { daysUntil } from '@/utils/stock'

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

/**
 * The expiry chip on an inventory row: "Expires tomorrow · buy 2 cartons",
 * "Expired Oct 21" or "Good until Oct 31". `amount` is what the shopping list
 * asks for while the item is expiring or expired: the full replacement.
 */
export function expiryChipText(
  status: ExpiryStatus,
  expiresAt: number | null,
  today: number,
  amount: string | null,
): string | null {
  if (expiresAt === null || status === 'none') return null
  const date = formatShortDate(expiresAt)
  if (status === 'expired') {
    return amount
      ? // TRANSLATORS: Chip on an inventory item past its expiry date. {date} is the date, e.g. "Oct 21"; {amount} is what to buy, e.g. "2 cartons".
        t('pantry', 'Expired {date} · buy {amount}', { date, amount })
      : // TRANSLATORS: Chip on an inventory item past its expiry date. {date} is the date, e.g. "Oct 21".
        t('pantry', 'Expired {date}', { date })
  }
  if (status === 'expiring') {
    const days = daysUntil(expiresAt, today)
    if (days <= 0) {
      return amount
        ? // TRANSLATORS: Chip on an inventory item. {amount} is what to buy, e.g. "2 cartons".
          t('pantry', 'Expires today · buy {amount}', { amount })
        : t('pantry', 'Expires today')
    }
    if (days === 1) {
      return amount
        ? // TRANSLATORS: Chip on an inventory item. {amount} is what to buy, e.g. "2 cartons".
          t('pantry', 'Expires tomorrow · buy {amount}', { amount })
        : t('pantry', 'Expires tomorrow')
    }
    return amount
      ? // TRANSLATORS: Chip on an inventory item. %n is the number of days; {amount} is what to buy, e.g. "2 cartons".
        n('pantry', 'Expires in %n day · buy {amount}', 'Expires in %n days · buy {amount}', days, {
          amount,
        })
      : n('pantry', 'Expires in %n day', 'Expires in %n days', days)
  }
  // TRANSLATORS: Chip on an inventory item that is not expiring yet. {date} is its expiry date, e.g. "Oct 31".
  return t('pantry', 'Good until {date}', { date })
}
