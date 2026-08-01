// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { resolveCurrency } from './currencies'

export interface PriceValue {
  priceType: 'set' | 'range' | null
  priceMin: number | null
  priceMax: number | null
  priceCurrency: string | null
}

/** True when the value carries a usable price (a type and a min amount). */
export function hasPrice(p: PriceValue): boolean {
  return (p.priceType === 'set' || p.priceType === 'range') && p.priceMin != null
}

/**
 * Format an amount using the currency's decimals but trimming trailing zeros,
 * so 1 → "1", 1.5 → "1.5", 1.25 → "1.25" (never "1.00").
 */
function formatAmount(amount: number, decimals: number): string {
  const rounded = Number(amount.toFixed(decimals))
  return String(rounded)
}

function place(symbol: string, body: string, position: 'before' | 'after'): string {
  return position === 'before' ? `${symbol}${body}` : `${body}${symbol}`
}

/** Format a single amount in a currency, e.g. "$1", "1.5₪". */
export function formatMoney(amount: number, currency: string | null): string {
  const c = resolveCurrency(currency)
  return place(c.symbol, formatAmount(amount, c.decimals), c.position)
}

/**
 * Format one per-currency total. A point value (min === max) renders as a single
 * amount; a range renders as "≈ X–Y".
 */
export function formatEstimateEntry(entry: { currency: string; min: number; max: number }): string {
  if (entry.min === entry.max) return formatMoney(entry.min, entry.currency)
  return `≈ ${formatMoney(entry.min, entry.currency)}–${formatMoney(entry.max, entry.currency)}`
}

/** Format a per-currency total list into one string (currencies never summed). */
export function formatEstimate(entries: { currency: string; min: number; max: number }[]): string {
  if (entries.length === 0) return '—'
  return entries.map(formatEstimateEntry).join(' · ')
}

/**
 * Render a price chip label, e.g. "$1", "$1-10", "1₪", "1-10₪".
 * Returns null when there is no price to show.
 */
export function formatPrice(p: PriceValue): string | null {
  if (!hasPrice(p) || p.priceMin == null) return null
  const currency = resolveCurrency(p.priceCurrency)
  const min = formatAmount(p.priceMin, currency.decimals)
  if (p.priceType === 'range' && p.priceMax != null) {
    const max = formatAmount(p.priceMax, currency.decimals)
    return place(currency.symbol, `${min}-${max}`, currency.position)
  }
  return place(currency.symbol, min, currency.position)
}
