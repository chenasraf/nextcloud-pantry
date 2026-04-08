import { ocs } from '@/axios'

export async function getLastHouse(): Promise<number | null> {
  const resp = await ocs.get<{ houseId: number | null }>('/prefs/last-house')
  return resp.data?.houseId ?? null
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await ocs.put('/prefs/last-house', { houseId })
}

export async function getImageFolder(houseId: number): Promise<string> {
  const resp = await ocs.get<{ folder: string }>(`/houses/${houseId}/prefs/image-folder`)
  return resp.data?.folder ?? '/Pantry'
}

export async function setImageFolder(houseId: number, folder: string): Promise<string> {
  const resp = await ocs.put<{ folder: string }>(`/houses/${houseId}/prefs/image-folder`, {
    folder,
  })
  return resp.data?.folder ?? folder
}

export type PhotoSort = 'custom' | 'newest' | 'oldest' | 'description_asc' | 'description_desc'
export type NoteSort = 'custom' | 'newest' | 'oldest' | 'title_asc' | 'title_desc'

export interface PhotoSortPrefs {
  sort: PhotoSort
  foldersFirst: boolean
}

export async function getPhotoSort(houseId: number): Promise<PhotoSortPrefs> {
  const resp = await ocs.get<PhotoSortPrefs>(`/houses/${houseId}/prefs/photo-sort`)
  return resp.data ?? { sort: 'custom', foldersFirst: true }
}

export async function setPhotoSort(
  houseId: number,
  prefs: Partial<PhotoSortPrefs>,
): Promise<PhotoSortPrefs> {
  const resp = await ocs.put<PhotoSortPrefs>(`/houses/${houseId}/prefs/photo-sort`, prefs)
  return resp.data ?? { sort: 'custom', foldersFirst: true }
}

export async function getNoteSort(houseId: number): Promise<{ sort: NoteSort }> {
  const resp = await ocs.get<{ sort: NoteSort }>(`/houses/${houseId}/prefs/note-sort`)
  return resp.data ?? { sort: 'custom' }
}

export async function setNoteSort(houseId: number, sort: NoteSort): Promise<{ sort: NoteSort }> {
  const resp = await ocs.put<{ sort: NoteSort }>(`/houses/${houseId}/prefs/note-sort`, { sort })
  return resp.data ?? { sort }
}

export interface NotificationPrefs {
  notifyPhoto: boolean
  notifyNoteCreate: boolean
  notifyNoteEdit: boolean
  notifyItemAdd: boolean
  notifyItemRecur: boolean
  notifyItemDone: boolean
}

export async function getNotificationPrefs(houseId: number): Promise<NotificationPrefs> {
  const resp = await ocs.get<NotificationPrefs>(`/houses/${houseId}/prefs/notifications`)
  return (
    resp.data ?? {
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
      notifyItemAdd: true,
      notifyItemRecur: true,
      notifyItemDone: true,
    }
  )
}

export async function setNotificationPrefs(
  houseId: number,
  prefs: Partial<NotificationPrefs>,
): Promise<NotificationPrefs> {
  const resp = await ocs.put<NotificationPrefs>(`/houses/${houseId}/prefs/notifications`, prefs)
  return (
    resp.data ?? {
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
      notifyItemAdd: true,
      notifyItemRecur: true,
      notifyItemDone: true,
    }
  )
}
