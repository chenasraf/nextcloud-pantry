// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { t, n } from '@nextcloud/l10n'

/**
 * Humanize a duration (in seconds) as a compact label: "< 1 min", "45 min",
 * "2 h", or "1 h 20 min". Shared by the trip history and the resume banner.
 */
export function formatDuration(seconds: number): string {
  const minutes = Math.floor(Math.max(0, seconds) / 60)
  // sanitize:false so the leading "<" is not turned into "&lt;" by DOMPurify
  // (the string is a hardcoded literal and rendered as plain text anyway).
  if (minutes < 1)
    return t('pantry', '< 1 min', undefined, undefined, { escape: false, sanitize: false })
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (hours < 1) return n('pantry', '%n min', '%n min', mins)
  if (mins === 0) return n('pantry', '%n h', '%n h', hours)
  // TRANSLATORS: A duration like "1 h 20 min". {hours} and {minutes} are numbers.
  return t('pantry', '{hours} h {minutes} min', { hours: String(hours), minutes: String(mins) })
}

/** Format an epoch-seconds timestamp as a locale-aware date + short time. */
export function formatDateTime(epochSeconds: number): string {
  return new Date(epochSeconds * 1000).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}
