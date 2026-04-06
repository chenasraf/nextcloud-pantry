import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { Note } from '@/api/types'

const mockApi = vi.hoisted(() => ({
  listNotes: vi.fn(),
  createNote: vi.fn(),
  updateNote: vi.fn(),
  deleteNote: vi.fn(),
  reorderNotes: vi.fn(),
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
    createdAt: 0,
    updatedAt: 0,
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
})
