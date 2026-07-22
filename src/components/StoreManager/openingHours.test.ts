import { describe, it, expect, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
  getFirstDay: () => 1, // Monday
  getDayNames: () => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
  getDayNamesShort: () => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
  getCanonicalLocale: vi.fn(() => 'en-US'),
}))

import { getCanonicalLocale } from '@nextcloud/l10n'
import { orderedDays, dayName, dayNameShort, groupByDay, formatTime } from './openingHours'

describe('openingHours helpers', () => {
  it('orders ISO days starting from the locale first day', () => {
    // Monday-first locale: Monday (1) .. Sunday (7).
    expect(orderedDays()).toEqual([1, 2, 3, 4, 5, 6, 7])
  })

  it('maps ISO day values (1 = Monday .. 7 = Sunday) to localized names', () => {
    expect(dayName(1)).toBe('Monday')
    expect(dayName(7)).toBe('Sunday')
    expect(dayNameShort(1)).toBe('Mon')
    expect(dayNameShort(7)).toBe('Sun')
  })

  it('groups intervals by day in locale order and sorts ranges', () => {
    const grouped = groupByDay([
      { day: 7, start: '10:00', end: '14:00' },
      { day: 1, start: '16:00', end: '20:00' },
      { day: 1, start: '09:00', end: '13:00' },
    ])
    expect(grouped.map((g) => g.day)).toEqual([1, 7])
    expect(grouped[0]!.ranges).toEqual([
      { day: 1, start: '09:00', end: '13:00' },
      { day: 1, start: '16:00', end: '20:00' },
    ])
    expect(grouped[1]!.name).toBe('Sunday')
  })

  it('omits days without intervals', () => {
    const grouped = groupByDay([{ day: 3, start: '08:00', end: '12:00' }])
    expect(grouped).toHaveLength(1)
    expect(grouped[0]!.day).toBe(3)
  })

  describe('formatTime', () => {
    it('formats 24h times in a 12-hour locale', () => {
      vi.mocked(getCanonicalLocale).mockReturnValue('en-US')
      // ICU may use a narrow no-break space before AM/PM; \s matches it.
      expect(formatTime('19:00')).toMatch(/^7:00\sPM$/i)
      expect(formatTime('09:05')).toMatch(/^9:05\sAM$/i)
      expect(formatTime('00:00')).toMatch(/^12:00\sAM$/i)
    })

    it('formats 24h times in a 24-hour locale', () => {
      vi.mocked(getCanonicalLocale).mockReturnValue('de-DE')
      expect(formatTime('19:00')).toBe('19:00')
      expect(formatTime('09:05')).toBe('09:05')
    })

    it('returns malformed input unchanged', () => {
      vi.mocked(getCanonicalLocale).mockReturnValue('en-US')
      expect(formatTime('nope')).toBe('nope')
      expect(formatTime('7:00')).toBe('7:00')
    })
  })
})
