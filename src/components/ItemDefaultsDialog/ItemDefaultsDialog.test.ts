import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { nextcloudL10nMock } from '@/test-utils'
import type { Checklist, ItemDefaults } from '@/api/types'

const updateItemDefaults = vi.fn()

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@/api/lists', () => ({ listApi: () => ({ updateItemDefaults }) }))
vi.mock('@/composables/useCustomFields', () => ({
  useCustomFields: () => ({ items: ref([]), load: vi.fn() }),
}))
vi.mock('@/components/ItemFieldChips', () => ({
  default: { name: 'ItemFieldChips', template: '<div class="item-field-chips" />' },
}))
vi.mock('@/components/ItemCustomFieldsEditor', () => ({
  default: { name: 'ItemCustomFieldsEditor', template: '<div />' },
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
    props: ['variant', 'disabled'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    props: [
      'modelValue',
      'disabled',
      'type',
      'value',
      'name',
      'buttonVariant',
      'buttonVariantGrouped',
    ],
    emits: ['update:modelValue'],
  },
}))

import ItemDefaultsDialog from './ItemDefaultsDialog.vue'

const defaults: ItemDefaults = {
  stores: { mode: 'none' },
  category: { mode: 'none' },
  labels: { mode: 'none' },
  quantity: { mode: 'none' },
  fields: [],
}

function makeList(overrides: Partial<Checklist> = {}): Checklist {
  return {
    id: 7,
    houseId: 1,
    kind: 'inventory',
    name: 'Pantry shelf',
    description: null,
    icon: 'clipboard-check',
    color: null,
    sortOrder: 0,
    itemDefaults: defaults,
    fillFromDefaults: true,
    deleteOnDoneDefault: false,
    defaultRecurrenceMode: 'remember',
    defaultRecurrenceKind: 'none',
    defaultRrule: null,
    defaultRepeatFromCompletion: false,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    archivedAt: null,
    lastCompletedAt: null,
    canEdit: true,
    ...overrides,
  }
}

function mountDialog(list: Checklist) {
  return mount(ItemDefaultsDialog, { props: { open: true, list } })
}

const fillSwitch = (wrapper: ReturnType<typeof mountDialog>) =>
  wrapper.find('[data-testid="item-defaults-fill"] input')

const saveButton = (wrapper: ReturnType<typeof mountDialog>) =>
  wrapper.findAll('.nc-button').find((b) => b.text() === 'Save')!

describe('ItemDefaultsDialog fill switch', () => {
  beforeEach(() => {
    updateItemDefaults.mockReset()
  })

  it('shows the list setting', () => {
    const wrapper = mountDialog(makeList({ fillFromDefaults: false }))

    expect((fillSwitch(wrapper).element as HTMLInputElement).checked).toBe(false)
  })

  it('is not offered on a template', () => {
    const wrapper = mountDialog(makeList({ kind: 'template', fillFromDefaults: undefined }))

    expect(wrapper.find('[data-testid="item-defaults-fill"]').exists()).toBe(false)
  })

  it('saves the switch with the item defaults', async () => {
    const list = makeList()
    updateItemDefaults.mockResolvedValue({ ...list, fillFromDefaults: false })
    const wrapper = mountDialog(list)

    await fillSwitch(wrapper).setValue(false)
    await saveButton(wrapper).trigger('click')
    await flushPromises()

    expect(updateItemDefaults).toHaveBeenCalledWith(1, 7, { fillFromDefaults: false })
    expect(wrapper.emitted('saved')?.[0]?.[0]).toMatchObject({ fillFromDefaults: false })
  })

  it('leaves the switch out of the patch when unchanged', async () => {
    const wrapper = mountDialog(makeList())

    await saveButton(wrapper).trigger('click')
    await flushPromises()

    expect(updateItemDefaults).not.toHaveBeenCalled()
  })

  it('cannot be changed without edit rights', () => {
    const wrapper = mountDialog(makeList({ canEdit: false }))

    expect(fillSwitch(wrapper).attributes('disabled')).toBeDefined()
  })
})
