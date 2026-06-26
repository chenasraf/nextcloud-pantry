import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ItemInput } from '@/api/lists'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/FileUpload.vue', () => createIconMock('FileUploadIcon'))
vi.mock('@icons/TagOutline.vue', () => createIconMock('TagOutlineIcon'))
vi.mock('@icons/FormatListBulleted.vue', () => createIconMock('FormatListBulletedIcon'))
vi.mock('@icons/Text.vue', () => createIconMock('TextIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))

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
      '<button class="nc-button" :type="type" :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'type', 'disabled'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'rows', 'autocomplete'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/RecurrenceEditor', () => ({
  RecurrenceForm: {
    name: 'RecurrenceForm',
    template: '<div class="mock-recurrence-form" />',
    props: ['modelValue', 'fromCompletion'],
    emits: ['update:modelValue', 'update:fromCompletion'],
  },
}))
vi.mock('@/components/CategoryChipList', () => ({
  default: {
    name: 'CategoryChipList',
    template: '<div class="mock-category-chip-list" />',
    props: ['modelValue', 'houseId'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/QuantityInput', () => ({
  default: {
    name: 'QuantityInput',
    template:
      '<input class="mock-quantity-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/ItemTypeSelector', () => ({
  default: {
    name: 'ItemTypeSelector',
    template:
      '<div class="mock-item-type-selector">' +
      '<button class="mock-one-time" type="button" @click="$emit(\'select-one-time\')">one-time</button>' +
      '</div>',
    props: ['deleteOnDone', 'rrule'],
    emits: ['select-staple', 'select-one-time', 'select-recurring'],
  },
}))
vi.mock('@/components/PantryChip', () => ({
  default: {
    name: 'PantryChip',
    template:
      '<button class="pantry-chip" type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant'],
    emits: ['click'],
  },
}))
vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'StubCategoryIcon', template: '<span />' }),
}))
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    items: { value: [] },
    load: vi.fn().mockResolvedValue(undefined),
  }),
}))
vi.mock('@/utils/rrule', () => ({
  formatRrule: (s: string) => `text(${s})`,
}))

import MarkdownImportDialog from './MarkdownImportDialog.vue'

function mountDialog(reusePref: 'ask' | 'reuse' | 'never' = 'ask') {
  return mount(MarkdownImportDialog, {
    props: { open: true, houseId: 1, importing: false, reusePref },
  })
}

function pasteTextarea(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.find('.md-import__text')
}

function addButton(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.findAll('.nc-button').find((b) => b.text().includes('Add'))!
}

function chipForKey(wrapper: ReturnType<typeof mountDialog>, label: string) {
  return wrapper.findAll('.pantry-chip').find((c) => c.text().includes(label))!
}

describe('MarkdownImportDialog', () => {
  it('shows no rows or chips before any text is pasted', () => {
    const wrapper = mountDialog()
    expect(wrapper.findAll('.md-import__row')).toHaveLength(0)
    expect(wrapper.findAll('.pantry-chip')).toHaveLength(0)
  })

  it('parses pasted markdown into selectable rows, all selected by default', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- [ ] Milk\n- [x] Eggs')
    const rows = wrapper.findAll('.md-import__row')
    expect(rows).toHaveLength(2)
    expect(wrapper.text()).toContain('Milk')
    expect(wrapper.text()).toContain('Eggs')
    const boxes = wrapper.findAll('.md-import__row input[type="checkbox"]')
    expect(boxes.every((b) => (b.element as HTMLInputElement).checked)).toBe(true)
  })

  it('shows an empty notice when the text has no list items', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('just some prose')
    expect(wrapper.find('.md-import__row').exists()).toBe(false)
    expect(wrapper.text()).toContain('No list items found')
  })

  it('emits import with one ItemInput per selected item', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk\n- Eggs')
    await addButton(wrapper).trigger('click')

    const inputs = wrapper.emitted('import')![0][0] as ItemInput[]
    expect(inputs).toHaveLength(2)
    expect(inputs.map((i) => i.name)).toEqual(['Milk', 'Eggs'])
    expect(inputs[0]).toEqual({
      name: 'Milk',
      description: null,
      quantity: null,
      categoryId: null,
      rrule: null,
      repeatFromCompletion: false,
      deleteOnDone: false,
    })
  })

  it('excludes deselected items from the import', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk\n- Eggs')
    // Deselect the first row.
    await wrapper.findAll('.md-import__row input[type="checkbox"]')[0].trigger('change')
    await addButton(wrapper).trigger('click')

    const inputs = wrapper.emitted('import')![0][0] as ItemInput[]
    expect(inputs.map((i) => i.name)).toEqual(['Eggs'])
  })

  it('disables the add button when nothing is selected', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk')
    await wrapper.find('.md-import__row input[type="checkbox"]').trigger('change')
    expect(addButton(wrapper).attributes('disabled')).toBeDefined()
  })

  it('applies a shared quantity to every imported item', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk\n- Eggs')
    await chipForKey(wrapper, 'Quantity').trigger('click')
    await wrapper.find('.mock-quantity-input').setValue('2 L')
    await addButton(wrapper).trigger('click')

    const inputs = wrapper.emitted('import')![0][0] as ItemInput[]
    expect(inputs.every((i) => i.quantity === '2 L')).toBe(true)
  })

  it('applies a shared one-time type to every imported item', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk\n- Eggs')
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-one-time').trigger('click')
    await addButton(wrapper).trigger('click')

    const inputs = wrapper.emitted('import')![0][0] as ItemInput[]
    expect(inputs.every((i) => i.deleteOnDone === true)).toBe(true)
  })

  it('emits forceReuse=false by default', async () => {
    const wrapper = mountDialog('ask')
    await pasteTextarea(wrapper).setValue('- Milk')
    await addButton(wrapper).trigger('click')
    expect(wrapper.emitted('import')![0][1]).toBe(false)
  })

  it('offers the reuse override only when the pref is not already "reuse"', async () => {
    const ask = mountDialog('ask')
    await pasteTextarea(ask).setValue('- Milk')
    expect(ask.find('.md-import__reuse').exists()).toBe(true)

    const never = mountDialog('never')
    await pasteTextarea(never).setValue('- Milk')
    expect(never.find('.md-import__reuse').exists()).toBe(true)

    const reuse = mountDialog('reuse')
    await pasteTextarea(reuse).setValue('- Milk')
    expect(reuse.find('.md-import__reuse').exists()).toBe(false)
  })

  it('emits forceReuse=true when the override checkbox is ticked', async () => {
    const wrapper = mountDialog('ask')
    await pasteTextarea(wrapper).setValue('- Milk')
    await wrapper.find('.md-import__reuse input[type="checkbox"]').trigger('change')
    await addButton(wrapper).trigger('click')
    expect(wrapper.emitted('import')![0][1]).toBe(true)
  })

  it('shows the drop overlay while a file is dragged over the dialog', async () => {
    const wrapper = mountDialog()
    expect(wrapper.find('.md-import__drop-overlay').exists()).toBe(false)
    await wrapper.find('.md-import').trigger('dragover')
    expect(wrapper.find('.md-import__drop-overlay').exists()).toBe(true)
  })

  it('parses a file dropped anywhere on the dialog', async () => {
    const wrapper = mountDialog()
    const file = new File(['- Milk\n- Eggs'], 'list.md', { type: 'text/markdown' })
    await wrapper.find('.md-import').trigger('drop', { dataTransfer: { files: [file] } })
    await new Promise((r) => setTimeout(r))
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('.md-import__row')).toHaveLength(2)
    expect(wrapper.find('.md-import__drop-overlay').exists()).toBe(false)
  })

  it('resets the pasted text when reopened', async () => {
    const wrapper = mountDialog()
    await pasteTextarea(wrapper).setValue('- Milk')
    expect(wrapper.findAll('.md-import__row')).toHaveLength(1)

    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })

    expect(wrapper.findAll('.md-import__row')).toHaveLength(0)
    expect((pasteTextarea(wrapper).element as HTMLTextAreaElement).value).toBe('')
  })
})
