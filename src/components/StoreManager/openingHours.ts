import { getCanonicalLocale, getDayNames, getDayNamesShort, getFirstDay } from '@nextcloud/l10n'
import type { OpeningHoursInterval } from '@/api/types'

/**
 * Formats a canonical 24-hour `"HH:MM"` time string for display in the instance/user's locale
 * (e.g. `"19:00"` → `"7:00 PM"` in en-US, `"19:00"` in de-DE). Invalid input is returned as-is.
 */
export function formatTime(hhmm: string): string {
  const match = /^(\d{2}):(\d{2})$/.exec(hhmm)
  if (!match) return hhmm
  const date = new Date(2000, 0, 1, Number(match[1]), Number(match[2]))
  return new Intl.DateTimeFormat(getCanonicalLocale(), { timeStyle: 'short' }).format(date)
}

/**
 * Maps an ISO-8601 day (1 = Monday .. 7 = Sunday) to the 0-6 index used by the
 * `@nextcloud/l10n` day helpers, where 0 = Sunday.
 */
function isoToNameIndex(day: number): number {
  return day % 7 // Sunday: 7 -> 0; Monday..Saturday: 1..6 stay
}

/** ISO-8601 day values (1 = Monday .. 7 = Sunday) ordered by the instance/user's first day of week. */
export function orderedDays(): number[] {
  const first = getFirstDay() // 0 = Sunday .. 6 = Saturday
  return Array.from({ length: 7 }, (_, i) => {
    const nameIndex = (first + i) % 7 // 0 = Sunday
    return nameIndex === 0 ? 7 : nameIndex // back to ISO: Sunday = 7
  })
}

/** Localized full day name for an ISO-8601 day value (1 = Monday .. 7 = Sunday). */
export function dayName(day: number): string {
  const names = getDayNames()
  return names[isoToNameIndex(day)] ?? ''
}

/** Localized short day name for an ISO-8601 day value (1 = Monday .. 7 = Sunday). */
export function dayNameShort(day: number): string {
  const names = getDayNamesShort()
  return names[isoToNameIndex(day)] ?? ''
}

export interface OpeningHoursDay {
  day: number
  name: string
  ranges: OpeningHoursInterval[]
}

/**
 * Groups intervals by day, ordered by the locale's first day of the week, and sorts each day's
 * ranges by start time. Days without any interval are omitted.
 */
export function groupByDay(intervals: OpeningHoursInterval[]): OpeningHoursDay[] {
  const byDay = new Map<number, OpeningHoursInterval[]>()
  for (const iv of intervals) {
    const list = byDay.get(iv.day) ?? []
    list.push(iv)
    byDay.set(iv.day, list)
  }
  const result: OpeningHoursDay[] = []
  for (const day of orderedDays()) {
    const ranges = byDay.get(day)
    if (!ranges || ranges.length === 0) continue
    result.push({
      day,
      name: dayName(day),
      ranges: [...ranges].sort((a, b) => a.start.localeCompare(b.start)),
    })
  }
  return result
}
