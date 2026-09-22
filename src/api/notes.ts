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
  patch: {
    title?: string
    content?: string
    color?: string
    sortOrder?: number
    isPinned?: boolean
  },
): Promise<Note> {
  const resp = await ocs.patch<Note>(`/houses/${houseId}/notes/${noteId}`, patch)
  return resp.data
}

export async function deleteNote(houseId: number, noteId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/notes/${noteId}`)
}

export async function listDeletedNotes(houseId: number): Promise<Note[]> {
  const resp = await ocs.get<Note[]>(`/houses/${houseId}/notes/trash`)
  return resp.data ?? []
}

export async function restoreNote(houseId: number, noteId: number): Promise<Note> {
  const resp = await ocs.post<Note>(`/houses/${houseId}/notes/${noteId}/restore`)
  return resp.data
}

export async function permanentlyDeleteNote(houseId: number, noteId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/notes/${noteId}/permanent`)
}

export async function emptyNotesTrash(houseId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/notes/trash`)
}

/**
 * Create a file under `folderPath` seeded with the note, and keep the two in
 * sync from then on.
 */
export async function startNoteSync(
  houseId: number,
  noteId: number,
  folderPath: string,
): Promise<Note> {
  const resp = await ocs.post<Note>(`/houses/${houseId}/notes/${noteId}/sync`, { folderPath })
  return resp.data
}

/** Stop syncing, leaving the note and the file as they are. */
export async function stopNoteSync(houseId: number, noteId: number): Promise<Note> {
  const resp = await ocs.delete<Note>(`/houses/${houseId}/notes/${noteId}/sync`)
  return resp.data
}

/** Create a note from a text file, optionally keeping the two in sync. */
export async function importNoteFromFile(
  houseId: number,
  path: string,
  sync = true,
): Promise<Note> {
  const resp = await ocs.post<Note>(`/houses/${houseId}/notes/import`, { path, sync })
  return resp.data
}

export async function reorderNotes(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/notes/reorder`, { items })
}
