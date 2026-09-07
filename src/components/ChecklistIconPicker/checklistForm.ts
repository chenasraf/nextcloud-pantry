import type { RecurrenceMode } from '@/api/types'

/** The list attributes {@link ChecklistFormDialog} submits. */
export interface ChecklistFormData {
  name: string
  description: string
  icon: string
  color: string
  defaultRecurrenceMode: RecurrenceMode
  defaultRrule: string | null
  defaultRepeatFromCompletion: boolean
}
