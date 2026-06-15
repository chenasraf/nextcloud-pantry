import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { Note } from '@/api/types'

const mockApi = vi.hoisted(() => ({
  listNotes: vi.fn(),
  createNote: vi.fn(),
  updateNote: vi.fn(),
  deleteNote: vi.fn(),
  reorderNotes: vi.fn(),
  listDeletedNotes: vi.fn(),
  restoreNote: vi.fn(),
  permanentlyDeleteNote: vi.fn(),
  emptyNotesTrash: vi.fn(),
}))

vi.mock('@/api/notes', () => mockApi)

import { useNotes } from './useNotes'

function makeNote(overrides: Partial<Note> = {}): Note {
  return {
    id: 1,
    houseId: 1,
    title: 'Groceries',
    content: null,
    color: null,
    createdBy: 'admin',
    sortOrder: 0,
    isPinned: false,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    ...overrides,
  }
}

describe('useNotes', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('load', () => {
    it('loads notes', async () => {
      const notes = [makeNote({ id: 1 }), makeNote({ id: 2 })]
      mockApi.listNotes.mockResolvedValue(notes)

      const wall = useNotes(1)
      await wall.load()

      expect(wall.notes.value).toEqual(notes)
      expect(wall.loading.value).toBe(false)
      expect(wall.error.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockApi.listNotes.mockRejectedValue(new Error('Network error'))

      const wall = useNotes(1)
      await wall.load()

      expect(wall.error.value).toBe('Network error')
      expect(wall.loading.value).toBe(false)
    })
  })

  describe('create', () => {
    it('creates and appends to list', async () => {
      mockApi.listNotes.mockResolvedValue([])
      const newNote = makeNote({ id: 10 })
      mockApi.createNote.mockResolvedValue(newNote)

      const wall = useNotes(1)
      await wall.load()
      const result = await wall.create('New Note', 'content', '#ff0000')

      expect(mockApi.createNote).toHaveBeenCalledWith(1, 'New Note', 'content', '#ff0000')
      expect(result).toEqual(newNote)
      expect(wall.notes.value).toHaveLength(1)
    })
  })

  describe('update', () => {
    it('updates note in list', async () => {
      const original = makeNote({ id: 1, title: 'Old' })
      const updated = makeNote({ id: 1, title: 'New' })
      mockApi.listNotes.mockResolvedValue([original])
      mockApi.updateNote.mockResolvedValue(updated)

      const wall = useNotes(1)
      await wall.load()
      await wall.update(1, { title: 'New' })

      expect(wall.notes.value[0].title).toBe('New')
    })
  })

  describe('remove', () => {
    it('removes note from list', async () => {
      mockApi.listNotes.mockResolvedValue([makeNote({ id: 1 }), makeNote({ id: 2 })])
      mockApi.deleteNote.mockResolvedValue(undefined)

      const wall = useNotes(1)
      await wall.load()
      await wall.remove(1)

      expect(wall.notes.value).toHaveLength(1)
      expect(wall.notes.value[0].id).toBe(2)
    })
  })

  describe('reorder', () => {
    it('updates sort orders locally and sorts', async () => {
      mockApi.listNotes.mockResolvedValue([
        makeNote({ id: 1, sortOrder: 0 }),
        makeNote({ id: 2, sortOrder: 1 }),
      ])
      mockApi.reorderNotes.mockResolvedValue(undefined)

      const wall = useNotes(1)
      await wall.load()
      await wall.reorder([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(wall.notes.value[0].id).toBe(2)
      expect(wall.notes.value[1].id).toBe(1)
    })
  })

  describe('sortBy', () => {
    it('defaults to custom', () => {
      const wall = useNotes(1)
      expect(wall.sortBy.value).toBe('custom')
    })

    it('passes sortBy value to listNotes', async () => {
      mockApi.listNotes.mockResolvedValue([])

      const wall = useNotes(1)
      wall.sortBy.value = 'newest'
      await wall.load()

      expect(mockApi.listNotes).toHaveBeenCalledWith(1, 'newest')
    })

    it('uses sort argument when provided to load()', async () => {
      mockApi.listNotes.mockResolvedValue([])

      const wall = useNotes(1)
      wall.sortBy.value = 'custom'
      await wall.load('title_asc')

      expect(mockApi.listNotes).toHaveBeenCalledWith(1, 'title_asc')
    })

    it('uses default custom sort when no argument given', async () => {
      mockApi.listNotes.mockResolvedValue([])

      const wall = useNotes(1)
      await wall.load()

      expect(mockApi.listNotes).toHaveBeenCalledWith(1, 'custom')
    })
  })

  describe('trash', () => {
    it('loadDeleted populates deletedNotes', async () => {
      const deleted = [makeNote({ id: 9, deletedAt: 123 })]
      mockApi.listDeletedNotes.mockResolvedValue(deleted)

      const wall = useNotes(1)
      await wall.loadDeleted()

      expect(wall.deletedNotes.value).toEqual(deleted)
    })

    it('restore moves note from deletedNotes back to notes', async () => {
      const restored = makeNote({ id: 9, deletedAt: null })
      mockApi.listDeletedNotes.mockResolvedValue([makeNote({ id: 9, deletedAt: 123 })])
      mockApi.restoreNote.mockResolvedValue(restored)

      const wall = useNotes(1)
      await wall.loadDeleted()
      await wall.restore(9)

      expect(wall.deletedNotes.value).toHaveLength(0)
      expect(wall.notes.value).toContainEqual(restored)
    })

    it('removePermanently drops the note from both arrays', async () => {
      mockApi.listNotes.mockResolvedValue([makeNote({ id: 9 })])
      mockApi.listDeletedNotes.mockResolvedValue([makeNote({ id: 9, deletedAt: 123 })])
      mockApi.permanentlyDeleteNote.mockResolvedValue(undefined)

      const wall = useNotes(1)
      await wall.load()
      await wall.loadDeleted()
      await wall.removePermanently(9)

      expect(wall.deletedNotes.value).toHaveLength(0)
      expect(wall.notes.value).toHaveLength(0)
      expect(mockApi.permanentlyDeleteNote).toHaveBeenCalledWith(1, 9)
    })

    it('emptyTrash clears deletedNotes', async () => {
      mockApi.listDeletedNotes.mockResolvedValue([makeNote({ id: 1 }), makeNote({ id: 2 })])
      mockApi.emptyNotesTrash.mockResolvedValue(undefined)

      const wall = useNotes(1)
      await wall.loadDeleted()
      await wall.emptyTrash()

      expect(wall.deletedNotes.value).toHaveLength(0)
      expect(mockApi.emptyNotesTrash).toHaveBeenCalledWith(1)
    })
  })
})
