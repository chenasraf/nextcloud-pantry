import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { NO_CATEGORY_ID, NO_STORE_ID } from './constants'
import type { Category, ChecklistItem, Store } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Magnify.vue', () => createIconMock('MagnifyIcon'))
vi.mock('@icons/TagOffOutline.vue', () => createIconMock('TagOffOutlineIcon'))
vi.mock('@icons/StoreOffOutline.vue', () => createIconMock('StoreOffOutlineIcon'))
vi.mock('@icons/LabelOutline.vue', () => createIconMock('LabelOutlineIcon'))
vi.mock('@icons/TagOutline.vue', () => createIconMock('TagOutlineIcon'))
vi.mock('@icons/StorefrontOutline.vue', () => createIconMock('StorefrontOutlineIcon'))
vi.mock('@icons/FormatListChecks.vue', () => createIconMock('FormatListChecksIcon'))
vi.mock('@icons/MenuDown.vue', () => createIconMock('MenuDownIcon'))
vi.mock('@icons/CheckAll.vue', () => createIconMock('CheckAllIcon'))
vi.mock('@icons/CheckboxMarked.vue', () => createIconMock('CheckboxMarkedIcon'))
vi.mock('@icons/CheckboxBlankOutline.vue', () => createIconMock('CheckboxBlankOutlineIcon'))

vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'CatIcon', template: '<span class="cat-icon" />' }),
}))
vi.mock('@/components/ChecklistIconPicker', () => ({
  checklistIconComponent: () => ({ name: 'ListIcon', template: '<span class="list-icon" />' }),
}))
vi.mock('./PriceFilter.vue', () => ({
  default: {
    name: 'PriceFilter',
    template: '<div class="mock-price-filter" />',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template: '<div class="nc-textfield"><slot name="icon" /></div>',
    props: ['modelValue', 'placeholder', 'showTrailingButton', 'trailingButtonIcon'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCounterBubble', () => ({
  default: {
    name: 'NcCounterBubble',
    template: '<span class="nc-counter">{{ count }}</span>',
    props: ['count'],
  },
}))

// Render both the trigger and the panel so the option rows are always in the DOM.
vi.mock('@nextcloud/vue/components/NcPopover', () => ({
  default: {
    name: 'NcPopover',
    props: ['shown', 'popoverBaseClass'],
    emits: ['update:shown'],
    template: '<div class="nc-popover"><slot name="trigger" /><slot /></div>',
  },
}))

vi.mock('@/components/PantryChip', () => ({
  default: {
    name: 'PantryChip',
    props: ['filled', 'variant', 'color', 'solid', 'size', 'interactive'],
    template:
      '<span class="pantry-chip"><slot name="icon" /><slot /><slot name="trailing" /></span>',
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
    color: '#3366ff',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

function makeStore(overrides: Partial<Store> = {}): Store {
  return {
    id: 1,
    houseId: 1,
    name: 'Supermarket',
    icon: 'cart',
    color: '#22c55e',
    brand: null,
    location: null,
    openingHours: null,
    contact: null,
    responsible: null,
    notes: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const dairy = makeCategory({ id: 1, name: 'Dairy' })
const supermarket = makeStore({ id: 1, name: 'Supermarket' })

function mountFilter(props: Partial<InstanceType<typeof ChecklistFilter>['$props']> = {}) {
  return mount(ChecklistFilter, {
    props: {
      query: '',
      selectedCategoryIds: [],
      selectedStoreIds: [],
      items: [
        makeItem({ id: 1, categoryId: dairy.id, storeIds: [supermarket.id] }),
        makeItem({ id: 2, categoryId: null, storeIds: [] }),
        makeItem({ id: 3, categoryId: null, storeIds: [] }),
      ],
      categories: [dairy],
      stores: [supermarket],
      ...props,
    },
  })
}

/** The first option row across every filter chip whose label matches exactly. */
function optionByText(wrapper: ReturnType<typeof mountFilter>, text: string) {
  return wrapper
    .findAll('.filter-chip__row')
    .find((r) => r.find('.filter-chip__row-label').text() === text)
}

describe('ChecklistFilter — category filter', () => {
  it('renders a "No category" option for uncategorized items', () => {
    const wrapper = mountFilter()
    expect(optionByText(wrapper, 'No category')).toBeDefined()
  })

  it('hides the "No category" option when every item has a category', () => {
    const wrapper = mountFilter({
      items: [makeItem({ id: 1, categoryId: dairy.id })],
    })
    expect(optionByText(wrapper, 'No category')).toBeUndefined()
  })

  it('emits the sentinel id when the "No category" option is picked', async () => {
    const wrapper = mountFilter()
    await optionByText(wrapper, 'No category')!.trigger('click')
    expect(wrapper.emitted('update:selectedCategoryIds')?.[0]).toEqual([[NO_CATEGORY_ID]])
  })

  it('clears the category facet when the "All categories" row is picked', async () => {
    const wrapper = mountFilter({ selectedCategoryIds: [dairy.id] })
    await optionByText(wrapper, 'All categories')!.trigger('click')
    expect(wrapper.emitted('update:selectedCategoryIds')?.[0]).toEqual([[]])
  })
})

describe('ChecklistFilter — store filter', () => {
  it('renders a store option and a "No store" option', () => {
    const wrapper = mountFilter()
    expect(optionByText(wrapper, 'Supermarket')).toBeDefined()
    expect(optionByText(wrapper, 'No stores')).toBeDefined()
  })

  it('emits the store id when a store option is picked', async () => {
    const wrapper = mountFilter()
    await optionByText(wrapper, 'Supermarket')!.trigger('click')
    expect(wrapper.emitted('update:selectedStoreIds')?.[0]).toEqual([[supermarket.id]])
  })

  it('emits the sentinel id when the "No store" option is picked', async () => {
    const wrapper = mountFilter()
    await optionByText(wrapper, 'No stores')!.trigger('click')
    expect(wrapper.emitted('update:selectedStoreIds')?.[0]).toEqual([[NO_STORE_ID]])
  })

  it('removes an already-selected store when its row is toggled off', async () => {
    const wrapper = mountFilter({ selectedStoreIds: [supermarket.id] })
    await optionByText(wrapper, 'Supermarket')!.trigger('click')
    expect(wrapper.emitted('update:selectedStoreIds')?.[0]).toEqual([[]])
  })
})
