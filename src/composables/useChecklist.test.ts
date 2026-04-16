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

// Each test uses a unique houseId so module-level shared state doesn't leak
// between tests. That is also what the production sharing guarantees — same
// houseId → same state, different houseId → independent state.
let houseCounter = 100

describe('useChecklists', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    houseCounter++
  })

  describe('load', () => {
    it('loads lists', async () => {
      const lists = [makeList({ id: 1 }), makeList({ id: 2 })]
      mockApi.listLists.mockResolvedValue(lists)

      const c = useChecklists(houseCounter)
      await c.load()

      expect(c.lists.value).toEqual(lists)
      expect(c.loading.value).toBe(false)
      expect(c.error.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockApi.listLists.mockRejectedValue(new Error('fail'))

      const c = useChecklists(houseCounter)
      await c.load()

      expect(c.error.value).toBe('fail')
    })

    it('deduplicates concurrent calls for the same house', async () => {
      mockApi.listLists.mockResolvedValue([makeList({ id: 1 })])

      const c = useChecklists(houseCounter)
      const [a, b] = await Promise.all([c.load(), c.load()])

      expect(a).toEqual(b)
      expect(mockApi.listLists).toHaveBeenCalledTimes(1)
    })
  })

  describe('create', () => {
    it('creates and appends to list', async () => {
      mockApi.listLists.mockResolvedValue([])
      const newList = makeList({ id: 10 })
      mockApi.createList.mockResolvedValue(newList)

      const c = useChecklists(houseCounter)
      await c.load()
      const result = await c.create('New', 'desc', 'cart')

      expect(mockApi.createList).toHaveBeenCalledWith(houseCounter, 'New', 'desc', 'cart')
      expect(result).toEqual(newList)
      expect(c.lists.value).toHaveLength(1)
    })
  })

  describe('update', () => {
    it('replaces the updated list in state', async () => {
      const original = makeList({ id: 1, name: 'Old' })
      const updated = makeList({ id: 1, name: 'New' })
      mockApi.listLists.mockResolvedValue([original])
      mockApi.updateList.mockResolvedValue(updated)

      const c = useChecklists(houseCounter)
      await c.load()
      await c.update(1, { name: 'New' })

      expect(c.lists.value[0].name).toBe('New')
    })
  })

  describe('remove', () => {
    it('removes list from state', async () => {
      mockApi.listLists.mockResolvedValue([makeList({ id: 1 }), makeList({ id: 2 })])
      mockApi.deleteList.mockResolvedValue(undefined)

      const c = useChecklists(houseCounter)
      await c.load()
      await c.remove(1)

      expect(c.lists.value).toHaveLength(1)
      expect(c.lists.value[0].id).toBe(2)
    })
  })

  describe('shared state', () => {
    it('two callers for the same house share the same lists ref', async () => {
      const lists = [makeList({ id: 1 }), makeList({ id: 2 })]
      mockApi.listLists.mockResolvedValue(lists)

      const houseId = houseCounter
      const a = useChecklists(houseId)
      const b = useChecklists(houseId)

      await a.load()

      // Both consumers see the loaded lists even though only `a` triggered load.
      expect(a.lists.value).toEqual(lists)
      expect(b.lists.value).toEqual(lists)
      // And they reference the exact same ref instance.
      expect(a.lists).toBe(b.lists)
    })

    it('propagates create across consumers for the same house', async () => {
      mockApi.listLists.mockResolvedValue([])
      const newList = makeList({ id: 10, name: 'Shared' })
      mockApi.createList.mockResolvedValue(newList)

      const houseId = houseCounter
      const a = useChecklists(houseId)
      const b = useChecklists(houseId)

      await a.load()
      await a.create('Shared')

      expect(b.lists.value).toHaveLength(1)
      expect(b.lists.value[0].id).toBe(10)
    })

    it('different house ids have independent state', async () => {
      mockApi.listLists.mockImplementation((id: number) =>
        Promise.resolve([makeList({ id: id * 100 })]),
      )

      const a = useChecklists(houseCounter)
      houseCounter++
      const b = useChecklists(houseCounter)

      await a.load()
      await b.load()

      expect(a.lists.value).not.toEqual(b.lists.value)
      expect(a.lists.value[0].id).not.toBe(b.lists.value[0].id)
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

    it('removes a "Once" item from local state when marked done', async () => {
      const once = makeItem({ id: 1, done: false, deleteOnDone: true })
      const other = makeItem({ id: 2, done: false })
      mockApi.listItems.mockResolvedValue([once, other])
      // Backend returns the (now-deleted) item flagged as done.
      mockApi.toggleItem.mockResolvedValue({ ...once, done: true })

      const c = useChecklistItems(1, 10)
      await c.load()

      // Optimistic removal — the row is gone immediately.
      const togglePromise = c.toggle(1)
      expect(c.items.value.map((i) => i.id)).toEqual([2])

      await togglePromise
      // And it stays gone after the server confirms.
      expect(c.items.value.map((i) => i.id)).toEqual([2])
    })

    it('restores a "Once" item on server failure when marking done', async () => {
      const once = makeItem({ id: 1, done: false, deleteOnDone: true })
      mockApi.listItems.mockResolvedValue([once])
      mockApi.toggleItem.mockRejectedValue(new Error('fail'))

      const c = useChecklistItems(1, 10)
      await c.load()

      await expect(c.toggle(1)).rejects.toThrow('fail')
      expect(c.items.value.map((i) => i.id)).toEqual([1])
      expect(c.items.value[0].done).toBe(false)
    })

    it('does not delete a "Once" item when unchecking (done → not done)', async () => {
      const once = makeItem({ id: 1, done: true, deleteOnDone: true })
      mockApi.listItems.mockResolvedValue([once])
      mockApi.toggleItem.mockResolvedValue({ ...once, done: false })

      const c = useChecklistItems(1, 10)
      await c.load()

      await c.toggle(1)
      expect(c.items.value.map((i) => i.id)).toEqual([1])
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
