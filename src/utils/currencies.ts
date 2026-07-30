// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface Currency {
  /** ISO 4217 code, e.g. 'USD'. */
  code: string
  /** Full English name, e.g. 'United States Dollar'. */
  name: string
  /** Display symbol, e.g. '$' or '₪'. */
  symbol: string
  /** Where the symbol sits relative to the amount. */
  position: 'before' | 'after'
  /** Number of fractional digits the currency conventionally uses. */
  decimals: number
}

/**
 * Curated list of common currencies with an explicit symbol and placement.
 *
 * Placement is intentionally hand-set (not locale-derived) so a price reads the
 * same for everyone: USD before the amount ($1), ILS after (1₪). Exported
 * sorted by name; USD remains the app-wide default (see DEFAULT_CURRENCY).
 */
const UNSORTED: Currency[] = [
  { code: 'ARS', name: 'Argentine Peso', symbol: '$', position: 'before', decimals: 2 },
  { code: 'AUD', name: 'Australian Dollar', symbol: 'A$', position: 'before', decimals: 2 },
  { code: 'BHD', name: 'Bahraini Dinar', symbol: 'BD', position: 'before', decimals: 3 },
  { code: 'BDT', name: 'Bangladeshi Taka', symbol: '৳', position: 'before', decimals: 2 },
  { code: 'BRL', name: 'Brazilian Real', symbol: 'R$', position: 'before', decimals: 2 },
  { code: 'BGN', name: 'Bulgarian Lev', symbol: 'лв', position: 'after', decimals: 2 },
  { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$', position: 'before', decimals: 2 },
  { code: 'CLP', name: 'Chilean Peso', symbol: '$', position: 'before', decimals: 0 },
  { code: 'CNY', name: 'Chinese Yuan', symbol: '¥', position: 'before', decimals: 2 },
  { code: 'COP', name: 'Colombian Peso', symbol: '$', position: 'before', decimals: 0 },
  { code: 'CRC', name: 'Costa Rican Colón', symbol: '₡', position: 'before', decimals: 2 },
  { code: 'CZK', name: 'Czech Koruna', symbol: 'Kč', position: 'after', decimals: 2 },
  { code: 'DKK', name: 'Danish Krone', symbol: 'kr', position: 'after', decimals: 2 },
  { code: 'DOP', name: 'Dominican Peso', symbol: 'RD$', position: 'before', decimals: 2 },
  { code: 'EGP', name: 'Egyptian Pound', symbol: 'E£', position: 'before', decimals: 2 },
  { code: 'EUR', name: 'Euro', symbol: '€', position: 'before', decimals: 2 },
  { code: 'GHS', name: 'Ghanaian Cedi', symbol: '₵', position: 'before', decimals: 2 },
  { code: 'GTQ', name: 'Guatemalan Quetzal', symbol: 'Q', position: 'before', decimals: 2 },
  { code: 'HKD', name: 'Hong Kong Dollar', symbol: 'HK$', position: 'before', decimals: 2 },
  { code: 'HUF', name: 'Hungarian Forint', symbol: 'Ft', position: 'after', decimals: 0 },
  { code: 'ISK', name: 'Icelandic Króna', symbol: 'kr', position: 'after', decimals: 0 },
  { code: 'INR', name: 'Indian Rupee', symbol: '₹', position: 'before', decimals: 2 },
  { code: 'IDR', name: 'Indonesian Rupiah', symbol: 'Rp', position: 'before', decimals: 0 },
  { code: 'ILS', name: 'Israeli New Shekel', symbol: '₪', position: 'after', decimals: 2 },
  { code: 'JPY', name: 'Japanese Yen', symbol: '¥', position: 'before', decimals: 0 },
  { code: 'JOD', name: 'Jordanian Dinar', symbol: 'JD', position: 'before', decimals: 3 },
  { code: 'KES', name: 'Kenyan Shilling', symbol: 'KSh', position: 'before', decimals: 2 },
  { code: 'KWD', name: 'Kuwaiti Dinar', symbol: 'KD', position: 'before', decimals: 3 },
  { code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM', position: 'before', decimals: 2 },
  { code: 'MXN', name: 'Mexican Peso', symbol: '$', position: 'before', decimals: 2 },
  { code: 'MAD', name: 'Moroccan Dirham', symbol: 'MAD', position: 'after', decimals: 2 },
  { code: 'TWD', name: 'New Taiwan Dollar', symbol: 'NT$', position: 'before', decimals: 2 },
  { code: 'NZD', name: 'New Zealand Dollar', symbol: 'NZ$', position: 'before', decimals: 2 },
  { code: 'NGN', name: 'Nigerian Naira', symbol: '₦', position: 'before', decimals: 2 },
  { code: 'NOK', name: 'Norwegian Krone', symbol: 'kr', position: 'after', decimals: 2 },
  { code: 'OMR', name: 'Omani Rial', symbol: '﷼', position: 'before', decimals: 3 },
  { code: 'PKR', name: 'Pakistani Rupee', symbol: '₨', position: 'before', decimals: 2 },
  { code: 'PEN', name: 'Peruvian Sol', symbol: 'S/', position: 'before', decimals: 2 },
  { code: 'PHP', name: 'Philippine Peso', symbol: '₱', position: 'before', decimals: 2 },
  { code: 'PLN', name: 'Polish Złoty', symbol: 'zł', position: 'after', decimals: 2 },
  { code: 'GBP', name: 'Pound Sterling', symbol: '£', position: 'before', decimals: 2 },
  { code: 'QAR', name: 'Qatari Riyal', symbol: 'QAR', position: 'after', decimals: 2 },
  { code: 'RON', name: 'Romanian Leu', symbol: 'lei', position: 'after', decimals: 2 },
  { code: 'RUB', name: 'Russian Ruble', symbol: '₽', position: 'after', decimals: 2 },
  { code: 'SAR', name: 'Saudi Riyal', symbol: 'SAR', position: 'after', decimals: 2 },
  { code: 'RSD', name: 'Serbian Dinar', symbol: 'дин', position: 'after', decimals: 2 },
  { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$', position: 'before', decimals: 2 },
  { code: 'ZAR', name: 'South African Rand', symbol: 'R', position: 'before', decimals: 2 },
  { code: 'KRW', name: 'South Korean Won', symbol: '₩', position: 'before', decimals: 0 },
  { code: 'LKR', name: 'Sri Lankan Rupee', symbol: '₨', position: 'before', decimals: 2 },
  { code: 'SEK', name: 'Swedish Krona', symbol: 'kr', position: 'after', decimals: 2 },
  { code: 'CHF', name: 'Swiss Franc', symbol: 'CHF', position: 'before', decimals: 2 },
  { code: 'THB', name: 'Thai Baht', symbol: '฿', position: 'before', decimals: 2 },
  { code: 'TRY', name: 'Turkish Lira', symbol: '₺', position: 'before', decimals: 2 },
  { code: 'AED', name: 'UAE Dirham', symbol: 'AED', position: 'after', decimals: 2 },
  { code: 'UAH', name: 'Ukrainian Hryvnia', symbol: '₴', position: 'after', decimals: 2 },
  { code: 'USD', name: 'United States Dollar', symbol: '$', position: 'before', decimals: 2 },
  { code: 'UYU', name: 'Uruguayan Peso', symbol: '$U', position: 'before', decimals: 2 },
  { code: 'VND', name: 'Vietnamese Dong', symbol: '₫', position: 'after', decimals: 0 },
]

export const CURRENCIES: Currency[] = [...UNSORTED].sort((a, b) => a.name.localeCompare(b.name))

const BY_CODE = new Map(CURRENCIES.map((c) => [c.code, c]))

export const DEFAULT_CURRENCY = 'USD'

export function currencyByCode(code: string | null | undefined): Currency | undefined {
  if (!code) return undefined
  return BY_CODE.get(code.toUpperCase())
}

/**
 * Resolve a currency to its definition, falling back to a synthetic entry that
 * shows the raw code before the amount. This keeps unknown codes displayable.
 */
export function resolveCurrency(code: string | null | undefined): Currency {
  const known = currencyByCode(code)
  if (known) return known
  const upper = (code ?? DEFAULT_CURRENCY).toUpperCase()
  return { code: upper, name: upper, symbol: upper, position: 'before', decimals: 2 }
}
