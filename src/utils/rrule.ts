import { t } from '@nextcloud/l10n'
import { RRule } from 'rrule'

export function formatRrule(rrule: string): string {
  try {
    const rule = RRule.fromString('RRULE:' + rrule.replace(/^RRULE:/i, ''))
    return rule.toText()
  } catch {
    return rrule
  }
}

/**
 * Return a human-readable "next recurrence" string for a checklist item.
 */
export function formatNextRecurrence(
  nextDueAt: number | null,
  repeatFromCompletion: boolean,
  done: boolean,
): string | null {
  if (nextDueAt) {
    const date = new Date(nextDueAt * 1000)
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }
  if (repeatFromCompletion && !done) {
    return t('pantry', 'Starts after completion')
  }
  return null
}
