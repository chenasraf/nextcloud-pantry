import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { NO_CATEGORY_ID } from './constants'
import type { Category, ChecklistItem } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Magnify.vue', () => createIconMock('MagnifyIcon'))
vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
vi.mock('@icons/TagOffOutline.vue', () => createIconMock('TagOffOutlineIcon'))

vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'CatIcon', template: '<span class="cat-icon" />' }),
}))
vi.mock('@/components/ChecklistIconPicker', () => ({
  checklistIconComponent: () => ({ name: 'ListIcon', template: '<span class="list-icon" />' }),
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template: '<div class="nc-textfield"><slot name="icon" /></div>',
    props: ['modelValue', 'placeholder', 'showTrailingButton', 'trailingButtonIcon'],
  },
}))
vi.mock('@nextcloud/vue/components/NcChip', () => ({
  default: {
    name: 'NcChip',
    template:
      '<button class="nc-chip" :data-variant="variant" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'noClose'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCounterBubble', () => ({
  default: {
    name: 'NcCounterBubble',
    template: '<span class="nc-counter">{{ count }}</span>',
    props: ['count'],
  },
}))

import ChecklistFilter from './ChecklistFilter.vue'

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
    addedBy: null,
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
    name: 'Dairy',
    icon: 'cow',
    color: '#3366ff',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const dairy = makeCategory({ id: 1, name: 'Dairy' })

function mountFilter(props: Partial<InstanceType<typeof ChecklistFilter>['$props']> = {}) {
  return mount(ChecklistFilter, {
    props: {
      query: '',
      selectedCategoryIds: [],
      items: [
        makeItem({ id: 1, categoryId: dairy.id }),
        makeItem({ id: 2, categoryId: null }),
        makeItem({ id: 3, categoryId: null }),
      ],
      categories: [dairy],
      ...props,
    },
  })
}

function chipByText(wrapper: ReturnType<typeof mountFilter>, text: string) {
  return wrapper.findAll('.nc-chip').find((c) => c.text().includes(text))
}

describe('ChecklistFilter — No category chip', () => {
  it('renders a "No category" chip with the uncategorized item count', () => {
    const wrapper = mountFilter()
    const chip = chipByText(wrapper, 'No category')
    expect(chip).toBeDefined()
    expect(chip!.find('.nc-counter').text()).toBe('2')
  })

  it('is hidden when every item has a category', () => {
    const wrapper = mountFilter({
      items: [makeItem({ id: 1, categoryId: dairy.id })],
    })
    expect(chipByText(wrapper, 'No category')).toBeUndefined()
  })

  it('emits the sentinel id when clicked, toggling it like any category chip', async () => {
    const wrapper = mountFilter()
    await chipByText(wrapper, 'No category')!.trigger('click')
    expect(wrapper.emitted('update:selectedCategoryIds')?.[0]).toEqual([[NO_CATEGORY_ID]])
  })

  it('renders as selected (primary) when the sentinel is in the selection', () => {
    const wrapper = mountFilter({ selectedCategoryIds: [NO_CATEGORY_ID] })
    expect(chipByText(wrapper, 'No category')!.attributes('data-variant')).toBe('primary')
  })

  it('deselects by emitting the selection without the sentinel', async () => {
    const wrapper = mountFilter({ selectedCategoryIds: [dairy.id, NO_CATEGORY_ID] })
    await chipByText(wrapper, 'No category')!.trigger('click')
    expect(wrapper.emitted('update:selectedCategoryIds')?.[0]).toEqual([[dairy.id]])
  })
})
