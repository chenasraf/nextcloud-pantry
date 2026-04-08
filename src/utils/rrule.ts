import { RRule } from 'rrule'

export function formatRrule(rrule: string): string {
  try {
    const rule = RRule.fromString('RRULE:' + rrule.replace(/^RRULE:/i, ''))
    return rule.toText()
  } catch {
    return rrule
  }
}
