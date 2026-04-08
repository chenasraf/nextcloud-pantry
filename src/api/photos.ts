import { ocs } from '@/axios'
import type { Photo, PhotoFolder } from './types'

// ----- Folders -----

export async function listFolders(houseId: number, sortBy?: string): Promise<PhotoFolder[]> {
  const resp = await ocs.get<PhotoFolder[]>(`/houses/${houseId}/photos/folders`, {
    params: sortBy ? { sortBy } : undefined,
  })
  return resp.data ?? []
}

export async function createFolder(houseId: number, name: string): Promise<PhotoFolder> {
  const resp = await ocs.post<PhotoFolder>(`/houses/${houseId}/photos/folders`, { name })
  return resp.data
}

export async function updateFolder(
  houseId: number,
  folderId: number,
  patch: { name?: string; sortOrder?: number },
): Promise<PhotoFolder> {
  const resp = await ocs.patch<PhotoFolder>(`/houses/${houseId}/photos/folders/${folderId}`, patch)
  return resp.data
}

export async function deleteFolder(
  houseId: number,
  folderId: number,
  deleteContents = false,
): Promise<void> {
  await ocs.delete(`/houses/${houseId}/photos/folders/${folderId}`, {
    params: deleteContents ? { deleteContents: true } : undefined,
  })
}

export async function reorderFolders(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/photos/folders/reorder`, { items })
}

// ----- Photos -----

export async function listPhotos(houseId: number, sortBy?: string): Promise<Photo[]> {
  const resp = await ocs.get<Photo[]>(`/houses/${houseId}/photos`, {
    params: sortBy ? { sortBy } : undefined,
  })
  return resp.data ?? []
}

export async function uploadPhoto(
  houseId: number,
  file: File,
  folderId?: number | null,
  caption?: string | null,
  onProgress?: (progress: number) => void,
): Promise<Photo> {
  const form = new FormData()
  form.append('image', file, file.name)
  if (folderId != null && folderId > 0) {
    form.append('folderId', String(folderId))
  }
  if (caption) {
    form.append('caption', caption)
  }
  const resp = await ocs.post<Photo>(`/houses/${houseId}/photos`, form, {
    onUploadProgress: onProgress
      ? (e) => onProgress(e.total ? Math.round((e.loaded / e.total) * 100) : 0)
      : undefined,
  })
  return resp.data
}

export async function updatePhoto(
  houseId: number,
  photoId: number,
  patch: { caption?: string; folderId?: number | null; sortOrder?: number },
): Promise<Photo> {
  const resp = await ocs.patch<Photo>(`/houses/${houseId}/photos/${photoId}`, patch)
  return resp.data
}

export async function deletePhoto(houseId: number, photoId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/photos/${photoId}`)
}

export async function reorderPhotos(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/photos/reorder`, { items })
}
