import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Upload.vue', () => createIconMock('UploadIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :disabled="disabled" :type="type"><slot name="icon" /><slot /></button>',
    props: ['variant', 'form', 'type', 'disabled'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'autocomplete'],
    emits: ['update:modelValue'],
  },
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
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'autocomplete'],
    emits: ['update:modelValue'],
    methods: {
      getTextareaEl() {
        return this.$el?.tagName === 'TEXTAREA' ? this.$el : this.$el?.querySelector('textarea')
      },
      resize() {},
    },
  },
}))
vi.mock('@/components/RecurrenceEditor', () => ({
  default: {
    name: 'RecurrenceEditor',
    template: '<div class="mock-recurrence-editor" />',
    props: ['open', 'modelValue', 'fromCompletion'],
  },
}))
vi.mock('@/components/CategoryPicker', () => ({
  default: {
    name: 'CategoryPicker',
    template: '<div class="mock-category-picker" />',
    props: ['modelValue', 'houseId', 'label', 'placeholder'],
  },
  categoryIconComponent: { name: 'CategoryIcon', template: '<span />' },
}))
vi.mock('@/api/images', () => ({
  itemImagePreviewUrl: (houseId: number, fileId: number, owner: string, size: number) =>
    `/preview/${houseId}/${fileId}/${owner}/${size}`,
}))

import ChecklistItemEditDialog from './ChecklistItemEditDialog.vue'

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 42,
    listId: 1,
    name: 'Milk',
    description: 'Whole milk',
    categoryId: 3,
    quantity: '2 L',
    done: false,
    doneAt: null,
    doneBy: null,
    rrule: null,
    repeatFromCompletion: false,
    deleteOnDone: false,
    nextDueAt: null,
    imageFileId: null,
    imageUploadedBy: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const defaultProps = {
  open: true,
  item: makeItem(),
  houseId: 10,
  saving: false,
}

describe('ChecklistItemEditDialog', () => {
  it('renders edit form with item values pre-filled', () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    const textFields = wrapper.findAll('.nc-text-field')
    expect((textFields[0].element as HTMLInputElement).value).toBe('Milk')
    expect((wrapper.find('.nc-text-area').element as HTMLTextAreaElement).value).toBe('Whole milk')
    expect((textFields[1].element as HTMLInputElement).value).toBe('2 L')
  })

  it('save button is disabled when name is empty', async () => {
    const wrapper = mount(ChecklistItemEditDialog, {
      props: { ...defaultProps, item: makeItem({ name: '' }) },
    })
    const saveButton = wrapper.findAll('.nc-button').find((b) => b.text() === 'Save')!
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('save button is disabled when saving prop is true', () => {
    const wrapper = mount(ChecklistItemEditDialog, {
      props: { ...defaultProps, saving: true },
    })
    const saveButton = wrapper.findAll('.nc-button').find((b) => b.text() === 'Save')!
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('emits save with patch, null image, and no clear on submit', async () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    await wrapper.find('#pantry-edit-item-form').trigger('submit')
    expect(wrapper.emitted('save')).toBeTruthy()
    const [itemId, patch, pendingImage, clearImage] = wrapper.emitted('save')![0] as [
      number,
      Record<string, unknown>,
      File | null,
      boolean,
    ]
    expect(itemId).toBe(42)
    expect(patch.name).toBe('Milk')
    expect(patch.description).toBe('Whole milk')
    expect(patch.quantity).toBe('2 L')
    expect(pendingImage).toBeNull()
    expect(clearImage).toBe(false)
  })

  it('emits update:open false on cancel click', async () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    const cancelButton = wrapper.findAll('.nc-button').find((b) => b.text() === 'Cancel')!
    await cancelButton.trigger('click')
    expect(wrapper.emitted('update:open')).toBeTruthy()
    expect(wrapper.emitted('update:open')![0]).toEqual([false])
  })

  it('shows server image preview when item has imageFileId', () => {
    const wrapper = mount(ChecklistItemEditDialog, {
      props: {
        ...defaultProps,
        item: makeItem({ imageFileId: 99, imageUploadedBy: 'admin' }),
      },
    })
    const img = wrapper.find('.edit-item-form__image-preview')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/preview/10/99/admin/96')
  })

  it('does not show image preview when no imageFileId', () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    expect(wrapper.find('.edit-item-form__image-preview').exists()).toBe(false)
  })

  it('hides image after clicking remove and emits clearImage true on save', async () => {
    const wrapper = mount(ChecklistItemEditDialog, {
      props: {
        ...defaultProps,
        item: makeItem({ imageFileId: 99, imageUploadedBy: 'admin' }),
      },
    })
    expect(wrapper.find('.edit-item-form__image-preview').exists()).toBe(true)
    const removeButton = wrapper.findAll('.nc-button').find((b) => b.text() === 'Remove image')!
    await removeButton.trigger('click')
    expect(wrapper.find('.edit-item-form__image-preview').exists()).toBe(false)

    await wrapper.find('#pantry-edit-item-form').trigger('submit')
    const [, , pendingImage, clearImage] = wrapper.emitted('save')![0] as [
      number,
      Record<string, unknown>,
      File | null,
      boolean,
    ]
    expect(pendingImage).toBeNull()
    expect(clearImage).toBe(true)
  })

  it('shows description field', () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    expect(wrapper.find('.nc-text-area').exists()).toBe(true)
  })

  it('pre-fills "Once" checkbox from item.deleteOnDone and emits it on save', async () => {
    const wrapper = mount(ChecklistItemEditDialog, {
      props: { ...defaultProps, item: makeItem({ deleteOnDone: true }) },
    })
    const checkbox = wrapper.find('.nc-checkbox input[type="checkbox"]').element as HTMLInputElement
    expect(checkbox.checked).toBe(true)

    await wrapper.find('#pantry-edit-item-form').trigger('submit')
    const [, patch] = wrapper.emitted('save')![0] as [number, Record<string, unknown>]
    expect(patch.deleteOnDone).toBe(true)
  })

  it('toggles "Once" checkbox and emits updated deleteOnDone on save', async () => {
    const wrapper = mount(ChecklistItemEditDialog, { props: defaultProps })
    const checkboxInput = wrapper.find('.nc-checkbox input[type="checkbox"]')
    await checkboxInput.setValue(true)

    await wrapper.find('#pantry-edit-item-form').trigger('submit')
    const [, patch] = wrapper.emitted('save')![0] as [number, Record<string, unknown>]
    expect(patch.deleteOnDone).toBe(true)
  })
})
