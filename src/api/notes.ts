import { ocs } from '@/axios'
import type { Note } from './types'

export async function listNotes(houseId: number, sortBy?: string): Promise<Note[]> {
  const resp = await ocs.get<Note[]>(`/houses/${houseId}/notes`, {
    params: sortBy ? { sortBy } : undefined,
  })
  return resp.data ?? []
}

export async function createNote(
  houseId: number,
  title: string,
  content?: string | null,
  color?: string | null,
): Promise<Note> {
  const resp = await ocs.post<Note>(`/houses/${houseId}/notes`, {
    title,
    content: content ?? null,
    color: color ?? null,
  })
  return resp.data
}

export async function updateNote(
  houseId: number,
  noteId: number,
  patch: { title?: string; content?: string; color?: string; sortOrder?: number },
): Promise<Note> {
  const resp = await ocs.patch<Note>(`/houses/${houseId}/notes/${noteId}`, patch)
  return resp.data
}

export async function deleteNote(houseId: number, noteId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/notes/${noteId}`)
}

export async function reorderNotes(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/notes/reorder`, { items })
}
