import { ocs } from '@/axios'

// ----- User-level prefs (not per-house) -----

export type CategorySpacing = 'disabled' | 'divider' | 'spacing'

export interface UserPrefs {
  lastHouseId: number | null
  /** 0 = Sunday, 1 = Monday, …, 6 = Saturday. Read-only from server. */
  firstDayOfWeek: number
  /** When true, clicking anywhere on a checklist row marks the item done. */
  tapRowToComplete: boolean
  /** Separator style between categories when checklists are sorted by category. */
  categorySpacing: CategorySpacing
}

const userPrefsDefaults: UserPrefs = {
  lastHouseId: null,
  firstDayOfWeek: 1,
  tapRowToComplete: false,
  categorySpacing: 'disabled',
}

let userPrefsInflight: Promise<UserPrefs> | null = null

export function getUserPrefs(): Promise<UserPrefs> {
  if (userPrefsInflight) return userPrefsInflight

  userPrefsInflight = ocs
    .get<UserPrefs>('/prefs')
    .then((resp) => ({ ...userPrefsDefaults, ...resp.data }))
    .finally(() => {
      userPrefsInflight = null
    })

  return userPrefsInflight
}

export async function setUserPrefs(patch: Partial<UserPrefs>): Promise<UserPrefs> {
  const resp = await ocs.put<UserPrefs>('/prefs', patch)
  return { ...userPrefsDefaults, ...resp.data }
}

// Convenience wrappers (used widely, keep the simple API)
export async function getLastHouse(): Promise<number | null> {
  const prefs = await getUserPrefs()
  return prefs.lastHouseId
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await setUserPrefs({ lastHouseId: houseId })
}

export async function getTapRowToComplete(): Promise<boolean> {
  const prefs = await getUserPrefs()
  return prefs.tapRowToComplete
}

export async function setTapRowToComplete(value: boolean): Promise<boolean> {
  const prefs = await setUserPrefs({ tapRowToComplete: value })
  return prefs.tapRowToComplete
}

export async function getCategorySpacing(): Promise<CategorySpacing> {
  const prefs = await getUserPrefs()
  return prefs.categorySpacing
}

export async function setCategorySpacing(value: CategorySpacing): Promise<CategorySpacing> {
  const prefs = await setUserPrefs({ categorySpacing: value })
  return prefs.categorySpacing
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
export type CategorySort = 'name_asc' | 'name_desc' | 'custom'
export type ChecklistSort = 'name_asc' | 'name_desc' | 'custom'

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
  checklistSort: ChecklistSort
  categorySort: CategorySort
  /** When true, show the avatar of the user who added each checklist item. */
  showAddedBy: boolean
}

const housePrefsDefaults: HousePrefs = {
  imageFolder: '/Pantry',
  photoSort: 'custom',
  photoFoldersFirst: true,
  noteSort: 'custom',
  checklistItemSort: 'custom',
  checklistSort: 'custom',
  categorySort: 'name_asc',
  showAddedBy: false,
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

export async function getChecklistSort(houseId: number): Promise<{ sort: ChecklistSort }> {
  const p = await getHousePrefs(houseId)
  return { sort: p.checklistSort }
}

export async function setChecklistSort(
  houseId: number,
  sort: ChecklistSort,
): Promise<{ sort: ChecklistSort }> {
  const p = await setHousePrefs(houseId, { checklistSort: sort })
  return { sort: p.checklistSort }
}

export async function getCategorySort(houseId: number): Promise<{ sort: CategorySort }> {
  const p = await getHousePrefs(houseId)
  return { sort: p.categorySort }
}

export async function setCategorySort(
  houseId: number,
  sort: CategorySort,
): Promise<{ sort: CategorySort }> {
  const p = await setHousePrefs(houseId, { categorySort: sort })
  return { sort: p.categorySort }
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
