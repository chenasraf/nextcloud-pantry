import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Category, Checklist } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Sort.vue', () => createIconMock('SortIcon'))
vi.mock('@icons/RadioboxBlank.vue', () => createIconMock('RadioboxBlankIcon'))
vi.mock('@icons/RadioboxMarked.vue', () => createIconMock('RadioboxMarkedIcon'))
vi.mock('@icons/DragVertical.vue', () => createIconMock('DragVerticalIcon'))
vi.mock('@icons/Store.vue', () => createIconMock('StoreIcon'))

vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'CategoryIcon', template: '<span />', props: ['size'] }),
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
      '<button class="nc-button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'ariaLabel', 'title'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: { name: 'NcLoadingIcon', template: '<span />', props: ['size'] },
}))
vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions"><slot /></div>',
    props: ['ariaLabel', 'title', 'type'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template: '<button class="nc-action" @click="$emit(\'click\')"><slot /></button>',
    emits: ['click'],
  },
}))

vi.mock('./CategoryFormDialog.vue', () => ({
  default: {
    name: 'CategoryFormDialog',
    template: '<div />',
    props: ['open', 'houseId', 'category', 'defaultListId', 'saving', 'error'],
    emits: ['update:open', 'save'],
  },
}))
vi.mock('./StoreCategoryOrderDialog.vue', () => ({
  default: {
    name: 'StoreCategoryOrderDialog',
    template: '<div />',
    props: ['open', 'houseId'],
    emits: ['update:open'],
  },
}))

vi.mock('@/composables/useTouchReorder', () => ({ useTouchReorder: vi.fn() }))

const sortPref = ref<'name_asc' | 'name_desc' | 'custom'>('name_asc')
vi.mock('@/api/prefs', () => ({
  getCategorySort: () => Promise.resolve({ sort: sortPref.value }),
  setCategorySort: vi.fn().mockResolvedValue(undefined),
}))

const mockItems = ref<Category[]>([])
const mockReorder = vi.fn().mockResolvedValue(undefined)
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    items: mockItems,
    loading: ref(false),
    error: ref(null),
    loaded: ref(true),
    sortBy: ref('name_asc'),
    load: vi.fn().mockResolvedValue(undefined),
    setSortBy: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    reorder: mockReorder,
    findById: vi.fn(),
    categoriesForList: (listId: number | null) =>
      mockItems.value.filter((c) => c.listId == null || c.listId === listId),
  }),
}))

const mockLists = ref<Checklist[]>([])
vi.mock('@/composables/useChecklist', () => ({
  useChecklists: () => ({
    lists: mockLists,
    load: vi.fn().mockResolvedValue(undefined),
  }),
}))

import CategoryManagerDialog from './CategoryManagerDialog.vue'

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    houseId: 10,
    listId: null,
    name: 'Dairy',
    icon: 'dairy',
    color: '#22c55e',
    sortOrder: 0,
    createdAt: 1000,
    updatedAt: 1000,
    ...overrides,
  }
}

function makeList(id: number, name: string): Checklist {
  return { id, houseId: 10, name } as Checklist
}

function names(wrapper: ReturnType<typeof mount>): string[] {
  return wrapper.findAll('.pantry-cat-list__name').map((el) => el.text())
}

function headers(wrapper: ReturnType<typeof mount>): string[] {
  return wrapper.findAll('.pantry-cat-list__group').map((el) => el.text())
}

async function mountDialog(listId?: number | null) {
  const wrapper = mount(CategoryManagerDialog, {
    props: { open: true, houseId: 10, listId },
  })
  await vi.waitFor(() => expect(wrapper.find('.pantry-cat-list').exists()).toBe(true))
  return wrapper
}

describe('CategoryManagerDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sortPref.value = 'name_asc'
    mockLists.value = [makeList(1, 'Groceries'), makeList(2, 'Hardware')]
    mockItems.value = [
      makeCategory({ id: 1, name: 'Dairy', listId: null, sortOrder: 0 }),
      makeCategory({ id: 2, name: 'Produce', listId: null, sortOrder: 1 }),
      makeCategory({ id: 3, name: 'Fruit', listId: 1, sortOrder: 2 }),
      makeCategory({ id: 4, name: 'Screws', listId: 2, sortOrder: 3 }),
    ]
  })

  describe('list scope', () => {
    it('shows every category in the house when opened without a list', async () => {
      const wrapper = await mountDialog(null)

      expect(names(wrapper)).toEqual(['Dairy', 'Produce', 'Fruit', 'Screws'])
      expect(headers(wrapper)).toEqual(['All lists', 'Groceries', 'Hardware'])
    })

    it('shows only globals and the open list when opened from a list', async () => {
      const wrapper = await mountDialog(1)

      expect(names(wrapper)).toEqual(['Dairy', 'Produce', 'Fruit'])
      expect(headers(wrapper)).toEqual(['All lists', 'Groceries'])
    })

    it('hints at emptiness when the open list has nothing and globals are gone', async () => {
      mockItems.value = [makeCategory({ id: 4, name: 'Screws', listId: 2, sortOrder: 0 })]

      const wrapper = mount(CategoryManagerDialog, {
        props: { open: true, houseId: 10, listId: 1 },
      })
      await vi.waitFor(() => expect(wrapper.find('.pantry-cat-hint').exists()).toBe(true))

      expect(wrapper.find('.pantry-cat-list').exists()).toBe(false)
    })

    it('offers the open list as the default scope for a new category', async () => {
      const wrapper = await mountDialog(1)

      expect(wrapper.findComponent({ name: 'CategoryFormDialog' }).props('defaultListId')).toBe(1)
    })
  })

  describe('reordering a narrowed view', () => {
    // sort_order is one house-wide sequence, so a reorder made while other
    // lists' categories are hidden still has to place them.
    it('renumbers the hidden categories along with the visible ones', async () => {
      sortPref.value = 'custom'
      const wrapper = await mountDialog(1)

      const rows = wrapper.findAll('.pantry-cat-list__item')
      await rows[0]!.trigger('dragstart', { dataTransfer: { setData: vi.fn() } })
      await rows[1]!.trigger('dragover', { clientY: 10 })
      await rows[1]!.trigger('drop')

      expect(mockReorder).toHaveBeenCalledWith([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
        { id: 3, sortOrder: 2 },
        { id: 4, sortOrder: 3 },
      ])
    })
  })
})
