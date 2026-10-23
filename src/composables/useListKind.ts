import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import { t } from '@nextcloud/l10n'
import type { ListKind } from '@/api/types'

const LIST_KIND_KEY: InjectionKey<Readonly<Ref<ListKind>>> = Symbol('pantry-list-kind')

/** Route names of each kind's index and list pages. */
export const LIST_KIND_ROUTES: Record<ListKind, { index: string; detail: string }> = {
  checklist: { index: 'lists', detail: 'list-detail' },
  template: { index: 'templates', detail: 'template-detail' },
  inventory: { index: 'inventories', detail: 'inventory-detail' },
}

/** Make `kind` the list kind of every component below this one. */
export function provideListKind(kind: Readonly<Ref<ListKind>>): void {
  provide(LIST_KIND_KEY, kind)
}

/**
 * The kind of list the surrounding page shows. Components outside a list page
 * see a checklist. Only checklists are checked off, recur, and shop.
 */
export function useListKind(): {
  kind: Readonly<Ref<ListKind>>
  isChecklist: ComputedRef<boolean>
} {
  const kind = inject(
    LIST_KIND_KEY,
    computed<ListKind>(() => 'checklist'),
    false,
  )
  return { kind, isChecklist: computed(() => kind.value === 'checklist') }
}

/**
 * Strings that name the kind inside its own section. Each kind gets its own
 * string rather than a shared "list" one, since many languages need per-noun
 * grammar.
 */
export function listKindStrings(kind: ListKind) {
  switch (kind) {
    case 'template':
      return templateStrings()
    case 'inventory':
      return inventoryStrings()
    default:
      return checklistStrings()
  }
}

export type ListKindStrings = ReturnType<typeof checklistStrings>

function checklistStrings() {
  return {
    title: t('pantry', 'Checklists'),
    trashTitle: t('pantry', 'Checklists trash'),
    archiveTitle: t('pantry', 'Archived checklists'),
    newList: t('pantry', 'New checklist'),
    createTitle: t('pantry', 'Create a checklist'),
    editTitle: t('pantry', 'Edit checklist'),
    duplicateTitle: t('pantry', 'Duplicate checklist'),
    duplicateFailed: t('pantry', 'Could not duplicate the checklist'),
    back: t('pantry', 'Back to checklists'),
    listMenu: t('pantry', 'Checklist actions'),
    deleteDialogTitle: t('pantry', 'Remove checklist'),
    deletePermanentlyDialogTitle: t('pantry', 'Delete checklist permanently'),
    emptyTitle: t('pantry', 'No checklists yet'),
    emptyBody: t('pantry', 'Create your first checklist to start adding items.'),
    trashEmptyBody: t('pantry', 'Deleted checklists will appear here.'),
    archiveEmptyTitle: t('pantry', 'No archived checklists'),
    archiveEmptyBody: t('pantry', 'Archived checklists will appear here.'),
    emptyTrashBody: t(
      'pantry',
      'This will permanently delete every checklist in the trash, along with all of their items. This cannot be undone.',
    ),
    listRestored: t('pantry', 'Checklist restored'),
    listMovedToTrash: t('pantry', 'Checklist moved to trash'),
    listPermanentlyDeleted: t('pantry', 'Checklist permanently deleted'),
    listArchived: t('pantry', 'Checklist archived'),
    listUnarchived: t('pantry', 'Checklist unarchived'),
  }
}

function templateStrings(): ListKindStrings {
  return {
    // TRANSLATORS: Noun (plural), lists of reusable items that other lists draw from. Navigation entry and page title.
    title: t('pantry', 'Templates'),
    trashTitle: t('pantry', 'Templates trash'),
    archiveTitle: t('pantry', 'Archived templates'),
    newList: t('pantry', 'New template'),
    createTitle: t('pantry', 'Create a template'),
    editTitle: t('pantry', 'Edit template'),
    duplicateTitle: t('pantry', 'Duplicate template'),
    duplicateFailed: t('pantry', 'Could not duplicate the template'),
    back: t('pantry', 'Back to templates'),
    listMenu: t('pantry', 'Template actions'),
    deleteDialogTitle: t('pantry', 'Remove template'),
    deletePermanentlyDialogTitle: t('pantry', 'Delete template permanently'),
    emptyTitle: t('pantry', 'No templates yet'),
    emptyBody: t('pantry', 'Create your first template to start adding items.'),
    trashEmptyBody: t('pantry', 'Deleted templates will appear here.'),
    archiveEmptyTitle: t('pantry', 'No archived templates'),
    archiveEmptyBody: t('pantry', 'Archived templates will appear here.'),
    emptyTrashBody: t(
      'pantry',
      'This will permanently delete every template in the trash, along with all of their items. This cannot be undone.',
    ),
    listRestored: t('pantry', 'Template restored'),
    listMovedToTrash: t('pantry', 'Template moved to trash'),
    listPermanentlyDeleted: t('pantry', 'Template permanently deleted'),
    listArchived: t('pantry', 'Template archived'),
    listUnarchived: t('pantry', 'Template unarchived'),
  }
}

function inventoryStrings(): ListKindStrings {
  return {
    // TRANSLATORS: Noun (plural), lists of what the household keeps on hand. Navigation entry and page title.
    title: t('pantry', 'Inventories'),
    trashTitle: t('pantry', 'Inventories trash'),
    archiveTitle: t('pantry', 'Archived inventories'),
    newList: t('pantry', 'New inventory'),
    createTitle: t('pantry', 'Create an inventory'),
    editTitle: t('pantry', 'Edit inventory'),
    duplicateTitle: t('pantry', 'Duplicate inventory'),
    duplicateFailed: t('pantry', 'Could not duplicate the inventory'),
    back: t('pantry', 'Back to inventories'),
    listMenu: t('pantry', 'Inventory actions'),
    deleteDialogTitle: t('pantry', 'Remove inventory'),
    deletePermanentlyDialogTitle: t('pantry', 'Delete inventory permanently'),
    emptyTitle: t('pantry', 'No inventories yet'),
    emptyBody: t('pantry', 'Create your first inventory to start adding items.'),
    trashEmptyBody: t('pantry', 'Deleted inventories will appear here.'),
    archiveEmptyTitle: t('pantry', 'No archived inventories'),
    archiveEmptyBody: t('pantry', 'Archived inventories will appear here.'),
    emptyTrashBody: t(
      'pantry',
      'This will permanently delete every inventory in the trash, along with all of their items. This cannot be undone.',
    ),
    listRestored: t('pantry', 'Inventory restored'),
    listMovedToTrash: t('pantry', 'Inventory moved to trash'),
    listPermanentlyDeleted: t('pantry', 'Inventory permanently deleted'),
    listArchived: t('pantry', 'Inventory archived'),
    listUnarchived: t('pantry', 'Inventory unarchived'),
  }
}
