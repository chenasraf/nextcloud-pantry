import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { Checklist, ChecklistItem, ListKind } from '@/api/types'

const listLists = vi.fn()
const listItems = vi.fn()
const getAttachedTemplates = vi.fn()
const batchCopyItems = vi.fn()
const showSuccess = vi.fn()

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({
  showError: vi.fn(),
  showSuccess: (m: string) => showSuccess(m),
}))
vi.mock('@/api/lists', () => ({
  listApi: (kind: ListKind) => ({
    listLists: (houseId: number) => listLists(kind, houseId),
    listItems: (houseId: number, listId: number) => listItems(kind, listId),
    getAttachedTemplates: (houseId: number, listId: number) => getAttachedTemplates(kind, listId),
  }),
  batchCopyItems: (...args: unknown[]) => batchCopyItems(...args),
}))
vi.mock('@/components/ChecklistIconPicker/checklistIcons', () => ({
  checklistIconComponent: () => ({ template: '<span />' }),
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size', 'closeOnClickOutside'],
    emits: ['update:open'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    props: ['variant', 'disabled', 'wide'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: { name: 'NcLoadingIcon', template: '<span />' },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))

import ApplyTemplateDialog from './ApplyTemplateDialog.vue'

function list(id: number, kind: ListKind, name: string): Checklist {
  return { id, houseId: 1, kind, name, icon: null } as unknown as Checklist
}

function item(id: number, name: string, done = false): ChecklistItem {
  return { id, name, done, quantity: null } as ChecklistItem
}

const weekly = list(9, 'template', 'Weekly basics')
const camping = list(10, 'template', 'Camping')
const groceries = list(7, 'checklist', 'Groceries')
const pantry = list(8, 'inventory', 'Pantry')

const pickButtons = (w: ReturnType<typeof mount>) =>
  w.findAll('.pantry-apply__pick-button').map((b) => b.text())

beforeEach(() => {
  vi.clearAllMocks()
  listLists.mockImplementation(
    async (kind: ListKind) =>
      ({ template: [camping, weekly], checklist: [groceries], inventory: [pantry] })[kind],
  )
  getAttachedTemplates.mockResolvedValue({ templateIds: [9], everyListTemplateIds: [] })
  listItems.mockImplementation(async (kind: ListKind) =>
    kind === 'template'
      ? [item(30, 'Milk'), item(31, 'Bread'), item(32, 'Eggs')]
      : [item(1, ' milk '), item(2, 'Bread', true)],
  )
  batchCopyItems.mockResolvedValue({ success: true, items: [], skipped: [], reused: [] })
})

describe('ApplyTemplateDialog', () => {
  it('opened from a list, offers its attached templates first', async () => {
    const w = mount(ApplyTemplateDialog, { props: { open: true, houseId: 1, target: groceries } })
    await flushPromises()

    expect(getAttachedTemplates).toHaveBeenCalledWith('checklist', 7)
    expect(pickButtons(w)).toEqual(['Weekly basics', 'Camping'])
  })

  it('opened from a template, offers checklists and inventories only', async () => {
    const w = mount(ApplyTemplateDialog, { props: { open: true, houseId: 1, template: weekly } })
    await flushPromises()

    expect(pickButtons(w)).toEqual(['Groceries', 'Pantry'])
  })

  it('leaves items already open on the list unticked, and applies the ticked ones', async () => {
    const w = mount(ApplyTemplateDialog, { props: { open: true, houseId: 1, template: weekly } })
    await flushPromises()
    await w.findAll('.pantry-apply__pick-button')[0].trigger('click')
    await flushPromises()

    const rows = w.findAll('.pantry-apply__item')
    expect(rows.map((r) => r.find('input').element.checked)).toEqual([false, true, true])
    expect(rows[0].text()).toContain('Already on list')
    expect(rows[1].text()).not.toContain('Already on list')

    await rows[2].find('input').setValue(false)
    await w.find('.pantry-apply__confirm').trigger('click')
    await flushPromises()

    expect(batchCopyItems).toHaveBeenCalledWith(1, [31], 7)
    expect(w.emitted('applied')?.[0]?.[1]).toEqual(groceries)
    expect(w.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('reports added and reused counts when both happen', async () => {
    batchCopyItems.mockResolvedValue({
      success: true,
      items: [item(40, 'Bread'), item(2, 'Eggs')],
      skipped: [],
      reused: [2],
    })
    const w = mount(ApplyTemplateDialog, {
      props: { open: true, houseId: 1, template: weekly, target: groceries },
    })
    await flushPromises()
    await w.find('.pantry-apply__confirm').trigger('click')
    await flushPromises()

    expect(showSuccess).toHaveBeenCalledWith('Groceries: 1 added, 1 brought back')
  })
})
