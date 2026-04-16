import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem, Category } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/ArrowRight.vue', () => createIconMock('ArrowRightIcon'))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions"><slot /></div>',
    props: ['ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template:
      '<button class="nc-action-button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
  },
}))

vi.mock('@/components/CategoryPicker', () => ({
  categoryIconComponent: () => ({
    name: 'MockCategoryIcon',
    template: '<span class="mock-category-icon" />',
    props: ['size'],
  }),
}))

vi.mock('@/api/images', () => ({
  itemImagePreviewUrl: (houseId: number, fileId: number, uploadedBy: string, size: number) =>
    `/mock/preview/${houseId}/${fileId}/${uploadedBy}/${size}`,
}))

vi.mock('@/utils/rrule', () => ({
  formatRrule: (rrule: string) => rrule,
  formatNextRecurrence: () => null,
}))

import ChecklistItemRow from './ChecklistItemRow.vue'

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 1,
    listId: 10,
    name: 'Milk',
    description: null,
    categoryId: null,
    quantity: null,
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

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    houseId: 1,
    name: 'Dairy',
    icon: 'cow',
    color: '#3366ff',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const defaultProps = {
  item: makeItem(),
  category: null as Category | null,
  houseId: 1,
}

describe('ChecklistItemRow', () => {
  describe('rendering', () => {
    it('renders item name', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ name: 'Eggs' }) },
      })
      expect(wrapper.find('.checklist-row__name').text()).toBe('Eggs')
    })

    it('shows done styling when item.done is true', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ done: true, doneAt: 1000, doneBy: 'admin' }) },
      })
      expect(wrapper.find('.checklist-row').classes()).toContain('checklist-row--done')
    })

    it('shows quantity badge when present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ quantity: '3' }) },
      })
      const qty = wrapper.find('.checklist-row__quantity')
      expect(qty.exists()).toBe(true)
      expect(qty.text()).toContain('3')
    })

    it('shows category badge with color when category is provided', () => {
      const category = makeCategory({ name: 'Dairy', color: '#ff0000' })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ categoryId: category.id }), category },
      })
      const cat = wrapper.find('.checklist-row__category')
      expect(cat.exists()).toBe(true)
      expect(cat.text()).toContain('Dairy')
      expect(cat.attributes('style')).toContain('color: #ff0000')
    })

    it('shows recurrence badge when rrule is present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ rrule: 'FREQ=WEEKLY' }) },
      })
      const rec = wrapper.find('.checklist-row__recurrence')
      expect(rec.exists()).toBe(true)
      expect(rec.text()).toContain('FREQ=WEEKLY')
    })

    it('shows image thumbnail when imageFileId is present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({ imageFileId: 42, imageUploadedBy: 'admin' }),
        },
      })
      const thumb = wrapper.find('.checklist-row__thumb')
      expect(thumb.exists()).toBe(true)
      expect(thumb.find('img').attributes('src')).toBe('/mock/preview/1/42/admin/64')
    })

    it('does not show thumbnail when no imageFileId', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row__thumb').exists()).toBe(false)
    })
  })

  describe('events', () => {
    it('emits toggle with item id on checkbox change', async () => {
      const item = makeItem({ id: 5 })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      await wrapper.find('input[type="checkbox"]').trigger('change')
      expect(wrapper.emitted('toggle')).toBeTruthy()
      expect(wrapper.emitted('toggle')![0]).toEqual([5])
    })

    it('emits view with item on view button click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const viewBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.attributes('aria-label') === 'View item')!
      await viewBtn.trigger('click')
      expect(wrapper.emitted('view')).toBeTruthy()
      expect(wrapper.emitted('view')![0]).toEqual([item])
    })

    it('emits edit with item on edit action click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const editBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Edit item')!
      await editBtn.trigger('click')
      expect(wrapper.emitted('edit')).toBeTruthy()
      expect(wrapper.emitted('edit')![0]).toEqual([item])
    })

    it('emits move with item on move action click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const moveBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Move to list')!
      await moveBtn.trigger('click')
      expect(wrapper.emitted('move')).toBeTruthy()
      expect(wrapper.emitted('move')![0]).toEqual([item])
    })

    it('emits remove with item id on remove action click', async () => {
      const item = makeItem({ id: 9 })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const removeBtn = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text() === 'Remove item')!
      await removeBtn.trigger('click')
      expect(wrapper.emitted('remove')).toBeTruthy()
      expect(wrapper.emitted('remove')![0]).toEqual([9])
    })

    it('emits preview with item on thumbnail click', async () => {
      const item = makeItem({ imageFileId: 42, imageUploadedBy: 'admin' })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      await wrapper.find('.checklist-row__thumb').trigger('click')
      expect(wrapper.emitted('preview')).toBeTruthy()
      expect(wrapper.emitted('preview')![0]).toEqual([item])
    })
  })

  describe('reorderEnabled', () => {
    it('is not draggable by default', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row').attributes('draggable')).toBe('false')
    })

    it('is draggable when reorderEnabled is true', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: true },
      })
      expect(wrapper.find('.checklist-row').attributes('draggable')).toBe('true')
    })

    it('emits drag-start on dragstart when reorderEnabled', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 7 }), reorderEnabled: true },
      })
      await wrapper.find('.checklist-row').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeTruthy()
      expect(wrapper.emitted('drag-start')![0]).toEqual([7])
    })

    it('does not emit drag-start when reorderEnabled is false', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 7 }), reorderEnabled: false },
      })
      await wrapper.find('.checklist-row').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeFalsy()
    })

    it('emits reorder-over on dragover when reorderEnabled', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 2 }), reorderEnabled: true },
      })
      await wrapper.find('.checklist-row').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-checklist-item'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeTruthy()
    })

    it('does not emit reorder-over when reorderEnabled is false', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: false },
      })
      await wrapper.find('.checklist-row').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-checklist-item'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeFalsy()
    })

    it('applies dragging class on dragstart and removes on dragend', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: true },
      })
      const row = wrapper.find('.checklist-row')

      await row.trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(row.classes()).toContain('checklist-row--dragging')

      await row.trigger('dragend')
      expect(row.classes()).not.toContain('checklist-row--dragging')
    })
  })
})
