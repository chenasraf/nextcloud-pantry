import type { TemplateLists } from '@/api/types'

/** The list attributes {@link ChecklistFormDialog} submits. */
export interface ChecklistFormData {
  name: string
  description: string
  icon: string
  color: string
  /**
   * Attachments picked while creating a list, saved once it exists: templates
   * for a checklist or inventory, lists for a template. Editing saves them as
   * they change instead.
   */
  attachments?: { templateIds: number[] } | { templateLists: TemplateLists }
}
