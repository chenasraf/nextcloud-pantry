import { ref } from 'vue'
import * as api from '@/api/notes'
import type { Note } from '@/api/types'

export function useNotes(houseId: number) {
  const notes = ref<Note[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      notes.value = await api.listNotes(houseId)
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(
    title: string,
    content?: string | null,
    color?: string | null,
  ): Promise<Note> {
    const created = await api.createNote(houseId, title, content, color)
    notes.value = [...notes.value, created]
    return created
  }

  async function update(
    noteId: number,
    patch: Parameters<typeof api.updateNote>[2],
  ): Promise<void> {
    const updated = await api.updateNote(houseId, noteId, patch)
    notes.value = notes.value.map((n) => (n.id === noteId ? updated : n))
  }

  async function remove(noteId: number): Promise<void> {
    await api.deleteNote(houseId, noteId)
    notes.value = notes.value.filter((n) => n.id !== noteId)
  }

  async function reorder(items: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(items.map((i) => [i.id, i.sortOrder]))
    notes.value = notes.value
      .map((n) => (map.has(n.id) ? { ...n, sortOrder: map.get(n.id)! } : n))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await api.reorderNotes(houseId, items)
  }

  return { notes, loading, error, load, create, update, remove, reorder }
}
