import { computed, ref } from 'vue'
import * as api from '@/api/photos'
import type { Photo, PhotoFolder } from '@/api/types'

export interface UploadEntry {
  id: string
  fileName: string
  folderId: number | null
  progress: number
}

let uploadSeq = 0

export function usePhotos(houseId: number) {
  const photos = ref<Photo[]>([])
  const folders = ref<PhotoFolder[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const uploads = ref<UploadEntry[]>([])

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const [p, f] = await Promise.all([api.listPhotos(houseId), api.listFolders(houseId)])
      photos.value = p
      folders.value = f
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  const rootPhotos = computed(() =>
    photos.value.filter((p) => p.folderId === null).sort((a, b) => a.sortOrder - b.sortOrder),
  )

  function photosInFolder(folderId: number): Photo[] {
    return photos.value
      .filter((p) => p.folderId === folderId)
      .sort((a, b) => a.sortOrder - b.sortOrder)
  }

  // ----- Photos -----

  async function upload(file: File, folderId?: number | null): Promise<Photo> {
    const entry: UploadEntry = {
      id: `upload-${++uploadSeq}`,
      fileName: file.name,
      folderId: folderId ?? null,
      progress: 0,
    }
    uploads.value = [...uploads.value, entry]
    try {
      const created = await api.uploadPhoto(houseId, file, folderId, null, (progress) => {
        uploads.value = uploads.value.map((u) => (u.id === entry.id ? { ...u, progress } : u))
      })
      // Place new photo first by giving it a sortOrder below the current minimum.
      const siblings = photos.value.filter((p) => p.folderId === (folderId ?? null))
      const minSort = siblings.length > 0 ? Math.min(...siblings.map((p) => p.sortOrder)) : 0
      const placed = { ...created, sortOrder: minSort - 1 }
      photos.value = [...photos.value, placed]
      return placed
    } finally {
      uploads.value = uploads.value.filter((u) => u.id !== entry.id)
    }
  }

  async function updatePhoto(
    photoId: number,
    patch: Parameters<typeof api.updatePhoto>[2],
  ): Promise<void> {
    const updated = await api.updatePhoto(houseId, photoId, patch)
    photos.value = photos.value.map((p) => (p.id === photoId ? updated : p))
  }

  async function removePhoto(photoId: number): Promise<void> {
    await api.deletePhoto(houseId, photoId)
    photos.value = photos.value.filter((p) => p.id !== photoId)
  }

  async function reorderPhotos(items: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(items.map((i) => [i.id, i.sortOrder]))
    photos.value = photos.value
      .map((p) => (map.has(p.id) ? { ...p, sortOrder: map.get(p.id)! } : p))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await api.reorderPhotos(houseId, items)
  }

  // ----- Folders -----

  async function createFolder(name: string): Promise<PhotoFolder> {
    const created = await api.createFolder(houseId, name)
    folders.value = [...folders.value, created]
    return created
  }

  async function updateFolder(
    folderId: number,
    patch: { name?: string; sortOrder?: number },
  ): Promise<void> {
    const updated = await api.updateFolder(houseId, folderId, patch)
    folders.value = folders.value.map((f) => (f.id === folderId ? updated : f))
  }

  async function removeFolder(folderId: number): Promise<void> {
    await api.deleteFolder(houseId, folderId)
    folders.value = folders.value.filter((f) => f.id !== folderId)
    // Photos in the deleted folder move to root
    photos.value = photos.value.map((p) => (p.folderId === folderId ? { ...p, folderId: null } : p))
  }

  async function reorderFolders(items: { id: number; sortOrder: number }[]): Promise<void> {
    const map = new Map(items.map((i) => [i.id, i.sortOrder]))
    folders.value = folders.value
      .map((f) => (map.has(f.id) ? { ...f, sortOrder: map.get(f.id)! } : f))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await api.reorderFolders(houseId, items)
  }

  return {
    photos,
    folders,
    uploads,
    loading,
    error,
    load,
    rootPhotos,
    photosInFolder,
    upload,
    updatePhoto,
    removePhoto,
    reorderPhotos,
    createFolder,
    updateFolder,
    removeFolder,
    reorderFolders,
  }
}
