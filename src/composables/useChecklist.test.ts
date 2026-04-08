import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { Checklist, ChecklistItem } from '@/api/types'

const mockApi = vi.hoisted(() => ({
  listLists: vi.fn(),
  createList: vi.fn(),
  updateList: vi.fn(),
  deleteList: vi.fn(),
  getList: vi.fn(),
  listItems: vi.fn(),
  addItem: vi.fn(),
  updateItem: vi.fn(),
  toggleItem: vi.fn(),
  deleteItem: vi.fn(),
  reorderItems: vi.fn(),
  uploadItemImage: vi.fn(),
  clearItemImage: vi.fn(),
}))

vi.mock('@/api/lists', () => mockApi)

import { useChecklists, useChecklistItems } from './useChecklist'

function makeList(overrides: Partial<Checklist> = {}): Checklist {
  return {
    id: 1,
    houseId: 1,
    name: 'Groceries',
    description: null,
    icon: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

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
    nextDueAt: null,
    imageFileId: null,
    imageUploadedBy: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('useChecklists', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('load', () => {
    it('loads lists', async () => {
      const lists = [makeList({ id: 1 }), makeList({ id: 2 })]
      mockApi.listLists.mockResolvedValue(lists)

      const c = useChecklists(1)
      await c.load()

      expect(c.lists.value).toEqual(lists)
      expect(c.loading.value).toBe(false)
      expect(c.error.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockApi.listLists.mockRejectedValue(new Error('fail'))

      const c = useChecklists(1)
      await c.load()

      expect(c.error.value).toBe('fail')
    })
  })

  describe('create', () => {
    it('creates and appends to list', async () => {
      mockApi.listLists.mockResolvedValue([])
      const newList = makeList({ id: 10 })
      mockApi.createList.mockResolvedValue(newList)

      const c = useChecklists(1)
      await c.load()
      const result = await c.create('New', 'desc', 'cart')

      expect(mockApi.createList).toHaveBeenCalledWith(1, 'New', 'desc', 'cart')
      expect(result).toEqual(newList)
      expect(c.lists.value).toHaveLength(1)
    })
  })

  describe('remove', () => {
    it('removes list from state', async () => {
      mockApi.listLists.mockResolvedValue([makeList({ id: 1 }), makeList({ id: 2 })])
      mockApi.deleteList.mockResolvedValue(undefined)

      const c = useChecklists(1)
      await c.load()
      await c.remove(1)

      expect(c.lists.value).toHaveLength(1)
      expect(c.lists.value[0].id).toBe(2)
    })
  })
})

describe('useChecklistItems', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('load', () => {
    it('loads items', async () => {
      const items = [makeItem({ id: 1 }), makeItem({ id: 2 })]
      mockApi.listItems.mockResolvedValue(items)

      const c = useChecklistItems(1, 10)
      await c.load()

      expect(c.items.value).toEqual(items)
      expect(c.loading.value).toBe(false)
    })

    it('sets error on failure', async () => {
      mockApi.listItems.mockRejectedValue(new Error('fail'))

      const c = useChecklistItems(1, 10)
      await c.load()

      expect(c.error.value).toBe('fail')
    })
  })

  describe('add', () => {
    it('adds and appends to items', async () => {
      mockApi.listItems.mockResolvedValue([])
      const newItem = makeItem({ id: 10 })
      mockApi.addItem.mockResolvedValue(newItem)

      const c = useChecklistItems(1, 10)
      await c.load()
      const result = await c.add({ name: 'Eggs' })

      expect(mockApi.addItem).toHaveBeenCalledWith(1, 10, { name: 'Eggs' })
      expect(result).toEqual(newItem)
      expect(c.items.value).toHaveLength(1)
    })
  })

  describe('update', () => {
    it('updates item in list', async () => {
      const original = makeItem({ id: 1, name: 'Old' })
      const updated = makeItem({ id: 1, name: 'New' })
      mockApi.listItems.mockResolvedValue([original])
      mockApi.updateItem.mockResolvedValue(updated)

      const c = useChecklistItems(1, 10)
      await c.load()
      await c.update(1, { name: 'New' })

      expect(c.items.value[0].name).toBe('New')
    })
  })

  describe('toggle', () => {
    it('optimistically flips done then updates from server', async () => {
      const item = makeItem({ id: 1, done: false })
      const toggled = makeItem({ id: 1, done: true, doneAt: 1000, doneBy: 'admin' })
      mockApi.listItems.mockResolvedValue([item])
      mockApi.toggleItem.mockResolvedValue(toggled)

      const c = useChecklistItems(1, 10)
      await c.load()

      // During toggle, done should flip optimistically
      const togglePromise = c.toggle(1)
      expect(c.items.value[0].done).toBe(true)

      await togglePromise
      expect(c.items.value[0].doneBy).toBe('admin')
    })

    it('rolls back on failure', async () => {
      const item = makeItem({ id: 1, done: false })
      mockApi.listItems.mockResolvedValue([item])
      mockApi.toggleItem.mockRejectedValue(new Error('fail'))

      const c = useChecklistItems(1, 10)
      await c.load()

      await expect(c.toggle(1)).rejects.toThrow('fail')
      expect(c.items.value[0].done).toBe(false)
    })
  })

  describe('remove', () => {
    it('removes item from list', async () => {
      mockApi.listItems.mockResolvedValue([makeItem({ id: 1 }), makeItem({ id: 2 })])
      mockApi.deleteItem.mockResolvedValue(undefined)

      const c = useChecklistItems(1, 10)
      await c.load()
      await c.remove(1)

      expect(c.items.value).toHaveLength(1)
      expect(c.items.value[0].id).toBe(2)
    })
  })

  describe('reorderItems', () => {
    it('updates sort orders locally and sorts', async () => {
      mockApi.listItems.mockResolvedValue([
        makeItem({ id: 1, sortOrder: 0 }),
        makeItem({ id: 2, sortOrder: 1 }),
      ])
      mockApi.reorderItems.mockResolvedValue(undefined)

      const c = useChecklistItems(1, 10)
      await c.load()
      await c.reorderItems([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(c.items.value[0].id).toBe(2)
      expect(c.items.value[1].id).toBe(1)
      expect(mockApi.reorderItems).toHaveBeenCalledWith(1, 10, [
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])
    })
  })

  describe('sortBy', () => {
    it('defaults to custom', () => {
      const c = useChecklistItems(1, 10)
      expect(c.sortBy.value).toBe('custom')
    })

    it('passes sortBy value to listItems', async () => {
      mockApi.listItems.mockResolvedValue([])

      const c = useChecklistItems(1, 10)
      c.sortBy.value = 'newest'
      await c.load()

      expect(mockApi.listItems).toHaveBeenCalledWith(1, 10, 'newest')
    })

    it('uses sort argument when provided to load()', async () => {
      mockApi.listItems.mockResolvedValue([])

      const c = useChecklistItems(1, 10)
      c.sortBy.value = 'custom'
      await c.load('name_asc')

      expect(mockApi.listItems).toHaveBeenCalledWith(1, 10, 'name_asc')
    })

    it('uses default custom sort when no argument given', async () => {
      mockApi.listItems.mockResolvedValue([])

      const c = useChecklistItems(1, 10)
      await c.load()

      expect(mockApi.listItems).toHaveBeenCalledWith(1, 10, 'custom')
    })
  })
})
