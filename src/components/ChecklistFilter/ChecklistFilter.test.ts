import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { NO_CATEGORY_ID, NO_STORE_ID } from './constants'
import type { Category, ChecklistItem, Store } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Magnify.vue', () => createIconMock('MagnifyIcon'))
vi.mock('@icons/TagOffOutline.vue', () => createIconMock('TagOffOutlineIcon'))
vi.mock('@icons/StoreOffOutline.vue', () => createIconMock('StoreOffOutlineIcon'))

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

// A lightweight NcSelect stand-in: renders each option as a button so tests can
// click to add it to the multi-select model. Clicking an "All"-flagged option
// emulates the clear affordance.
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    props: [
      'modelValue',
      'options',
      'multiple',
      'closeOnSelect',
      'clearable',
      'searchable',
      'placeholder',
      'inputLabel',
      'label',
    ],
    emits: ['update:modelValue'],
    template: `
      <div class="nc-select">
        <button
          v-for="(opt, i) in options"
          :key="i"
          class="nc-select-option"
          :data-all="opt.all ? 'true' : 'false'"
          @click="$emit('update:modelValue', [...modelValue, opt])"
        >{{ opt.label }}</button>
      </div>
    `,
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
    priceType: null,
    priceMin: null,
    priceMax: null,
    priceCurrency: null,
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

/** The first option across every dropdown whose label matches exactly. */
function optionByText(wrapper: ReturnType<typeof mountFilter>, text: string) {
  return wrapper.findAll('.nc-select-option').find((o) => o.text() === text)
}

describe('ChecklistFilter — category dropdown', () => {
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

  it('clears the category facet when the "All" option is picked', async () => {
    const wrapper = mountFilter({ selectedCategoryIds: [dairy.id] })
    // No list dropdown in non-meta view, so the first "All" option is the
    // category dropdown's.
    const allOption = wrapper
      .findAll('.nc-select-option')
      .find((o) => o.attributes('data-all') === 'true')
    await allOption!.trigger('click')
    expect(wrapper.emitted('update:selectedCategoryIds')?.[0]).toEqual([[]])
  })
})

describe('ChecklistFilter — store dropdown', () => {
  it('renders a store option and a "No store" option', () => {
    const wrapper = mountFilter()
    expect(optionByText(wrapper, 'Supermarket')).toBeDefined()
    expect(optionByText(wrapper, 'No store')).toBeDefined()
  })

  it('emits the store id when a store option is picked', async () => {
    const wrapper = mountFilter()
    await optionByText(wrapper, 'Supermarket')!.trigger('click')
    expect(wrapper.emitted('update:selectedStoreIds')?.[0]).toEqual([[supermarket.id]])
  })

  it('emits the sentinel id when the "No store" option is picked', async () => {
    const wrapper = mountFilter()
    await optionByText(wrapper, 'No store')!.trigger('click')
    expect(wrapper.emitted('update:selectedStoreIds')?.[0]).toEqual([[NO_STORE_ID]])
  })
})
