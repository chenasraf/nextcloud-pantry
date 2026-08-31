import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
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
      '<button class="nc-button" :disabled="disabled"><slot name="icon" /><slot /></button>',
    props: ['variant', 'form', 'type', 'disabled', 'ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: {
    name: 'NcAvatar',
    template: '<span class="nc-avatar" />',
    props: ['user', 'size', 'showUserStatus'],
  },
}))
vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
  default: {
    name: 'NcDateTime',
    template: '<span class="nc-date-time" :data-timestamp="timestamp" />',
    props: ['timestamp', 'format', 'relativeTime'],
  },
}))
// Render one checkbox input per task-list line so the component's DOM-index
// mapping (input id -> task token) can be exercised. The input id mirrors what
// NcRichText emits via `interactTodo`.
vi.mock('@nextcloud/vue/components/NcRichText', () => ({
  default: {
    name: 'NcRichText',
    props: ['text', 'useMarkdown', 'useExtendedMarkdown', 'interactive'],
    emits: ['interactTodo'],
    computed: {
      tasks(this: { text: string }) {
        return String(this.text)
          .split('\n')
          .map((line: string, i: number) => ({ line, id: `md-input-${i}` }))
          .filter((t: { line: string }) => /^\s*(?:[-*+]|\d+[.)])\s+\[[ xX]\]/.test(t.line))
      },
    },
    template: `<div class="nc-rich-text">{{ text }}<input
        v-for="task in tasks"
        :key="task.id"
        :id="task.id"
        type="checkbox"
        @click="$emit('interactTodo', task.id)"
      /></div>`,
  },
}))

vi.mock('@/components/CategoryPicker', () => ({
  categoryIconComponent: () => ({
    name: 'CategoryIcon',
    template: '<span class="mock-category-icon" />',
    props: ['size'],
  }),
}))

vi.mock('@/composables/useHouseMembers', () => ({
  useHouseMembers: () => ({ members: { value: [] }, displayNameByUid: { value: {} } }),
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
    storeIds: [],
    labelIds: [],
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
    addedBy: null,
    barcode: null,
    prices: [],
    customFields: [],
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    archivedAt: null,
    ...overrides,
  }
}

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    houseId: 1,
    listId: null,
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
    const img = wrapper.find('.item-view__image-btn img')
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

  it('shows category-tinted fallback glyph when no image but category present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ categoryId: 1 }), category: makeCategory() },
    })
    expect(wrapper.find('.item-view__image-btn').exists()).toBe(false)
    expect(wrapper.find('.item-view__glyph').exists()).toBe(true)
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

  it('emits toggle-task with the flipped description when a task is toggled', async () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: {
        ...defaultProps,
        item: makeItem({ description: '- [ ] Milk\n- [ ] Eggs' }),
      },
    })
    const boxes = wrapper.findAll('.item-view__description input[type="checkbox"]')
    expect(boxes).toHaveLength(2)

    await boxes[1].trigger('click')

    const events = wrapper.emitted('toggle-task')
    expect(events).toHaveLength(1)
    const [item, description] = events![0]
    expect((item as { id: number }).id).toBe(1)
    expect(description).toBe('- [ ] Milk\n- [x] Eggs')
  })

  it('shows quantity tile when present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ quantity: '3' }) },
    })
    const values = wrapper.findAll('.item-view__tile-value')
    expect(values.some((v) => v.text().includes('3'))).toBe(true)
  })

  it('shows category chip with its color when category provided', () => {
    const category = makeCategory({ name: 'Dairy', color: '#4caf50' })
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ categoryId: 1 }), category },
    })
    const chip = wrapper.findAll('.item-view__chip').find((c) => c.text().includes('Dairy'))
    expect(chip).toBeDefined()
    expect(chip!.attributes('style')).toContain('#4caf50')
  })

  it('shows recurrence summary in the type tile when rrule present', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ rrule: 'FREQ=WEEKLY' }) },
    })
    expect(wrapper.text()).toContain('Recurring')
    expect(wrapper.find('.item-view__tile-sub').text()).toContain('FREQ=WEEKLY')
  })

  it('shows the done timestamp when the item is done', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ done: true, doneAt: 1_700_000_000 }) },
    })
    const rows = wrapper.findAll('.item-view__meta-row')
    const doneRow = rows.find((r) => r.text().includes('Done'))
    expect(doneRow).toBeDefined()
    expect(doneRow!.find('.nc-date-time').attributes('data-timestamp')).toBe(
      String(1_700_000_000 * 1000),
    )
  })

  it('does not show the done row when the item is not done', () => {
    const wrapper = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ done: false }) },
    })
    const rows = wrapper.findAll('.item-view__meta-row')
    const doneRow = rows.find((r) => r.text().includes('Done'))
    expect(doneRow).toBeUndefined()
  })

  it('shows an added-by avatar only when show-added-by is set', () => {
    const withoutPref = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ addedBy: 'admin' }) },
    })
    expect(withoutPref.find('.nc-avatar').exists()).toBe(false)

    const withPref = mount(ChecklistItemViewDialog, {
      props: { ...defaultProps, item: makeItem({ addedBy: 'admin' }), showAddedBy: true },
    })
    expect(withPref.find('.nc-avatar').exists()).toBe(true)
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
