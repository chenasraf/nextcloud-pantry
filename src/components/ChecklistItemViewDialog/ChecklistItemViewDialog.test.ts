import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))

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
      '<button class="nc-button" :disabled="disabled"><slot name="icon" /><slot /></button>',
    props: ['variant', 'form', 'type', 'disabled', 'ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcRichText', () => ({
  default: {
    name: 'NcRichText',
    template: '<div class="nc-rich-text">{{ text }}</div>',
    props: ['text', 'useMarkdown', 'useExtendedMarkdown'],
  },
}))

vi.mock('@/components/CategoryPicker', () => ({
  categoryIconComponent: () => ({
    name: 'CategoryIcon',
    template: '<span class="mock-category-icon" />',
    props: ['size'],
  }),
}))

vi.mock('@/api/images', () => ({
  itemImagePreviewUrl: (houseId: number, fileId: number, uploadedBy: string, size: number) =>
    `/preview/${houseId}/${fileId}/${uploadedBy}/${size}`,
}))

vi.mock('@/utils/rrule', () => ({
  formatRrule: (rrule: string) => rrule,
  formatNextRecurrence: () => null,
}))

import ChecklistItemViewDialog from './ChecklistItemViewDialog.vue'
import type { ChecklistItem, Category } from '@/api/types'

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 1,
    listId: 1,
    name: 'Test Item',
    description: null,
    categoryId: null,
    quantity: null,
    done: false,
    doneAt: null,
    doneBy: null,
    rrule: null,
    repeatFromCompletion: false,
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
    color: '#4caf50',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const defaultProps = {
  open: true,
  item: makeItem(),
  category: null,
  houseId: 1,
}

describe('ChecklistItemViewDialog', () => {
  it('renders item name in dialog', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ name: 'Milk' }) },
    })
    const dialog = wrapper.findComponent({ name: 'NcDialog' })
    expect(dialog.props('name')).toBe('Milk')
  })

  it('shows cover image when imageFileId is present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: {
        ...defaultProps,
        item: makeItem({ imageFileId: 42, imageUploadedBy: 'admin', name: 'Milk' }),
      },
    })
    const img = wrapper.find('.item-view__image')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/preview/1/42/admin/1600')
    expect(img.attributes('alt')).toBe('Milk')
  })

  it('does not show image button when no imageFileId', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ imageFileId: null }) },
    })
    expect(wrapper.find('.item-view__image-btn').exists()).toBe(false)
  })

  it('renders description with NcRichText when present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ description: 'Buy **organic** milk' }) },
    })
    const richText = wrapper.findComponent({ name: 'NcRichText' })
    expect(richText.exists()).toBe(true)
    expect(richText.props('text')).toBe('Buy **organic** milk')
  })

  it('does not render description section when null', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ description: null }) },
    })
    expect(wrapper.find('.item-view__description').exists()).toBe(false)
  })

  it('shows quantity row when present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ quantity: '3' }) },
    })
    const rows = wrapper.findAll('.item-view__row')
    const quantityRow = rows.find((r) => r.text().includes('Quantity'))
    expect(quantityRow).toBeDefined()
    expect(quantityRow!.text()).toContain('3')
  })

  it('shows category row with color when category provided', () => {
    const category = makeCategory({ name: 'Dairy', color: '#4caf50' })
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ categoryId: 1 }), category },
    })
    const rows = wrapper.findAll('.item-view__row')
    const categoryRow = rows.find((r) => r.text().includes('Category'))
    expect(categoryRow).toBeDefined()
    const badge = categoryRow!.find('.item-view__badge')
    expect(badge.attributes('style')).toContain('color: #4caf50')
    expect(badge.text()).toContain('Dairy')
  })

  it('shows recurrence row when rrule present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ rrule: 'FREQ=WEEKLY' }) },
    })
    const rows = wrapper.findAll('.item-view__row')
    const recurrenceRow = rows.find((r) => r.text().includes('Recurrence'))
    expect(recurrenceRow).toBeDefined()
    expect(recurrenceRow!.text()).toContain('FREQ=WEEKLY')
  })

  it('shows done status row when item is done', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ done: true }) },
    })
    const rows = wrapper.findAll('.item-view__row')
    const statusRow = rows.find((r) => r.text().includes('Status'))
    expect(statusRow).toBeDefined()
    expect(statusRow!.text()).toContain('Done')
  })

  it('does not show done row when item is not done', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ done: false }) },
    })
    const rows = wrapper.findAll('.item-view__row')
    const statusRow = rows.find((r) => r.text().includes('Status'))
    expect(statusRow).toBeUndefined()
  })

  it('emits edit with item when edit button clicked', async () => {
    const item = makeItem({ name: 'Eggs' })
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item },
    })
    const editBtn = wrapper.findAll('.nc-button').find((b) => b.find('.mock-pencil-icon').exists())!
    await editBtn.trigger('click')
    expect(wrapper.emitted('edit')).toBeTruthy()
    expect(wrapper.emitted('edit')![0][0]).toEqual(item)
  })

  it('emits preview with item when image clicked', async () => {
    const item = makeItem({ imageFileId: 42, imageUploadedBy: 'admin' })
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item },
    })
    await wrapper.find('.item-view__image-btn').trigger('click')
    expect(wrapper.emitted('preview')).toBeTruthy()
    expect(wrapper.emitted('preview')![0][0]).toEqual(item)
  })

  it('emits update:open false when dialog closes', async () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: defaultProps,
    })
    wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:open')).toBeTruthy()
    expect(wrapper.emitted('update:open')![0][0]).toBe(false)
  })
})
