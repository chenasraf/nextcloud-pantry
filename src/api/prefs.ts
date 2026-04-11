import { ocs } from '@/axios'

// ----- User-level prefs (not per-house) -----

export interface UserPrefs {
  lastHouseId: number | null
  /** 0 = Sunday, 1 = Monday, …, 6 = Saturday. Read-only from server. */
  firstDayOfWeek: number
}

let userPrefsInflight: Promise<UserPrefs> | null = null

export function getUserPrefs(): Promise<UserPrefs> {
  if (userPrefsInflight) return userPrefsInflight

  userPrefsInflight = ocs
    .get<UserPrefs>('/prefs')
    .then((resp) => resp.data ?? { lastHouseId: null, firstDayOfWeek: 1 })
    .finally(() => {
      userPrefsInflight = null
    })

  return userPrefsInflight
}

export async function setUserPrefs(patch: Partial<UserPrefs>): Promise<UserPrefs> {
  const resp = await ocs.put<UserPrefs>('/prefs', patch)
  return resp.data ?? { lastHouseId: null, firstDayOfWeek: 1 }
}

// Convenience wrappers (used widely, keep the simple API)
export async function getLastHouse(): Promise<number | null> {
  const prefs = await getUserPrefs()
  return prefs.lastHouseId
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await setUserPrefs({ lastHouseId: houseId })
}

// ----- Per-house prefs -----

export type PhotoSort = 'custom' | 'newest' | 'oldest' | 'description_asc' | 'description_desc'
export type NoteSort = 'custom' | 'newest' | 'oldest' | 'title_asc' | 'title_desc'
export type ChecklistItemSort =
  | 'custom'
  | 'newest'
  | 'oldest'
  | 'name_asc'
  | 'name_desc'
  | 'category'

export interface PhotoSortPrefs {
  sort: PhotoSort
  foldersFirst: boolean
}

export interface NotificationPrefs {
  notifyPhoto: boolean
  notifyNoteCreate: boolean
  notifyNoteEdit: boolean
  notifyItemAdd: boolean
  notifyItemRecur: boolean
  notifyItemDone: boolean
}

export interface HousePrefs extends NotificationPrefs {
  imageFolder: string
  photoSort: PhotoSort
  photoFoldersFirst: boolean
  noteSort: NoteSort
  checklistItemSort: ChecklistItemSort
}

const housePrefsDefaults: HousePrefs = {
  imageFolder: '/Pantry',
  photoSort: 'custom',
  photoFoldersFirst: true,
  noteSort: 'custom',
  checklistItemSort: 'custom',
  notifyPhoto: true,
  notifyNoteCreate: true,
  notifyNoteEdit: true,
  notifyItemAdd: true,
  notifyItemRecur: true,
  notifyItemDone: true,
}

// Deduplicate concurrent getHousePrefs calls for the same house.
const housePrefsInflight = new Map<number, Promise<HousePrefs>>()

export function getHousePrefs(houseId: number): Promise<HousePrefs> {
  const existing = housePrefsInflight.get(houseId)
  if (existing) return existing

  const promise = ocs
    .get<HousePrefs>(`/houses/${houseId}/prefs`)
    .then((resp) => ({ ...housePrefsDefaults, ...resp.data }))
    .finally(() => housePrefsInflight.delete(houseId))

  housePrefsInflight.set(houseId, promise)
  return promise
}

export async function setHousePrefs(
  houseId: number,
  patch: Partial<HousePrefs>,
): Promise<HousePrefs> {
  housePrefsInflight.delete(houseId)
  const resp = await ocs.put<HousePrefs>(`/houses/${houseId}/prefs`, patch)
  return { ...housePrefsDefaults, ...resp.data }
}

// ----- Convenience wrappers for individual prefs -----

export async function getPhotoSort(houseId: number): Promise<PhotoSortPrefs> {
  const p = await getHousePrefs(houseId)
  return { sort: p.photoSort, foldersFirst: p.photoFoldersFirst }
}

export async function setPhotoSort(
  houseId: number,
  prefs: Partial<PhotoSortPrefs>,
): Promise<PhotoSortPrefs> {
  const patch: Partial<HousePrefs> = {}
  if (prefs.sort !== undefined) patch.photoSort = prefs.sort
  if (prefs.foldersFirst !== undefined) patch.photoFoldersFirst = prefs.foldersFirst
  const p = await setHousePrefs(houseId, patch)
  return { sort: p.photoSort, foldersFirst: p.photoFoldersFirst }
}

export async function getNoteSort(houseId: number): Promise<{ sort: NoteSort }> {
  const p = await getHousePrefs(houseId)
  return { sort: p.noteSort }
}

export async function setNoteSort(houseId: number, sort: NoteSort): Promise<{ sort: NoteSort }> {
  const p = await setHousePrefs(houseId, { noteSort: sort })
  return { sort: p.noteSort }
}

export async function getChecklistItemSort(houseId: number): Promise<{ sort: ChecklistItemSort }> {
  const p = await getHousePrefs(houseId)
  return { sort: p.checklistItemSort }
}

export async function setChecklistItemSort(
  houseId: number,
  sort: ChecklistItemSort,
): Promise<{ sort: ChecklistItemSort }> {
  const p = await setHousePrefs(houseId, { checklistItemSort: sort })
  return { sort: p.checklistItemSort }
}

export async function getImageFolder(houseId: number): Promise<string> {
  const p = await getHousePrefs(houseId)
  return p.imageFolder
}

export async function setImageFolder(houseId: number, folder: string): Promise<string> {
  const p = await setHousePrefs(houseId, { imageFolder: folder })
  return p.imageFolder
}

export async function getNotificationPrefs(houseId: number): Promise<NotificationPrefs> {
  const p = await getHousePrefs(houseId)
  return {
    notifyPhoto: p.notifyPhoto,
    notifyNoteCreate: p.notifyNoteCreate,
    notifyNoteEdit: p.notifyNoteEdit,
    notifyItemAdd: p.notifyItemAdd,
    notifyItemRecur: p.notifyItemRecur,
    notifyItemDone: p.notifyItemDone,
  }
}

export async function setNotificationPrefs(
  houseId: number,
  prefs: Partial<NotificationPrefs>,
): Promise<NotificationPrefs> {
  const p = await setHousePrefs(houseId, prefs)
  return {
    notifyPhoto: p.notifyPhoto,
    notifyNoteCreate: p.notifyNoteCreate,
    notifyNoteEdit: p.notifyNoteEdit,
    notifyItemAdd: p.notifyItemAdd,
    notifyItemRecur: p.notifyItemRecur,
    notifyItemDone: p.notifyItemDone,
  }
}
