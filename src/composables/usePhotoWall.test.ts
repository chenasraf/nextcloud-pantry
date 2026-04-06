import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { Photo, PhotoFolder } from '@/api/types'

const mockApi = vi.hoisted(() => ({
  listPhotos: vi.fn(),
  listFolders: vi.fn(),
  uploadPhoto: vi.fn(),
  updatePhoto: vi.fn(),
  deletePhoto: vi.fn(),
  reorderPhotos: vi.fn(),
  createFolder: vi.fn(),
  updateFolder: vi.fn(),
  deleteFolder: vi.fn(),
  reorderFolders: vi.fn(),
}))

vi.mock('@/api/photos', () => mockApi)

import { usePhotoWall } from './usePhotoWall'

function makePhoto(overrides: Partial<Photo> = {}): Photo {
  return {
    id: 1,
    houseId: 1,
    folderId: null,
    fileId: 42,
    caption: null,
    uploadedBy: 'admin',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

function makeFolder(overrides: Partial<PhotoFolder> = {}): PhotoFolder {
  return {
    id: 1,
    houseId: 1,
    name: 'Recipes',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('usePhotoWall', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('load', () => {
    it('loads photos and folders in parallel', async () => {
      const photos = [makePhoto({ id: 1 }), makePhoto({ id: 2, folderId: 5 })]
      const folders = [makeFolder({ id: 5 })]
      mockApi.listPhotos.mockResolvedValue(photos)
      mockApi.listFolders.mockResolvedValue(folders)

      const wall = usePhotoWall(1)
      await wall.load()

      expect(wall.photos.value).toEqual(photos)
      expect(wall.folders.value).toEqual(folders)
      expect(wall.loading.value).toBe(false)
      expect(wall.error.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockApi.listPhotos.mockRejectedValue(new Error('Network error'))
      mockApi.listFolders.mockResolvedValue([])

      const wall = usePhotoWall(1)
      await wall.load()

      expect(wall.error.value).toBe('Network error')
      expect(wall.loading.value).toBe(false)
    })
  })

  describe('rootPhotos', () => {
    it('filters photos with null folderId', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: null }),
        makePhoto({ id: 2, folderId: 5 }),
        makePhoto({ id: 3, folderId: null }),
      ])
      mockApi.listFolders.mockResolvedValue([])

      const wall = usePhotoWall(1)
      await wall.load()

      expect(wall.rootPhotos.value).toHaveLength(2)
      expect(wall.rootPhotos.value.map((p) => p.id)).toEqual([1, 3])
    })
  })

  describe('photosInFolder', () => {
    it('returns photos matching the given folderId', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: null }),
        makePhoto({ id: 2, folderId: 5 }),
        makePhoto({ id: 3, folderId: 5 }),
        makePhoto({ id: 4, folderId: 7 }),
      ])
      mockApi.listFolders.mockResolvedValue([])

      const wall = usePhotoWall(1)
      await wall.load()

      expect(wall.photosInFolder(5)).toHaveLength(2)
      expect(wall.photosInFolder(5).map((p) => p.id)).toEqual([2, 3])
      expect(wall.photosInFolder(7)).toHaveLength(1)
      expect(wall.photosInFolder(99)).toHaveLength(0)
    })
  })

  describe('upload', () => {
    it('uploads and appends to photos list', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])
      const newPhoto = makePhoto({ id: 10, fileId: 100 })
      mockApi.uploadPhoto.mockResolvedValue(newPhoto)

      const wall = usePhotoWall(1)
      await wall.load()
      const file = new File(['data'], 'test.jpg')
      const result = await wall.upload(file, 5)

      expect(mockApi.uploadPhoto).toHaveBeenCalledWith(1, file, 5)
      expect(result).toEqual(newPhoto)
      expect(wall.photos.value).toHaveLength(1)
      expect(wall.photos.value[0]).toEqual(newPhoto)
    })
  })

  describe('updatePhoto', () => {
    it('updates photo in the list', async () => {
      const original = makePhoto({ id: 1, caption: 'Old' })
      const updated = makePhoto({ id: 1, caption: 'New' })
      mockApi.listPhotos.mockResolvedValue([original])
      mockApi.listFolders.mockResolvedValue([])
      mockApi.updatePhoto.mockResolvedValue(updated)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.updatePhoto(1, { caption: 'New' })

      expect(wall.photos.value[0].caption).toBe('New')
    })
  })

  describe('removePhoto', () => {
    it('removes photo from the list', async () => {
      mockApi.listPhotos.mockResolvedValue([makePhoto({ id: 1 }), makePhoto({ id: 2 })])
      mockApi.listFolders.mockResolvedValue([])
      mockApi.deletePhoto.mockResolvedValue(undefined)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.removePhoto(1)

      expect(wall.photos.value).toHaveLength(1)
      expect(wall.photos.value[0].id).toBe(2)
    })
  })

  describe('reorderPhotos', () => {
    it('updates sort orders locally and sorts', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, sortOrder: 0 }),
        makePhoto({ id: 2, sortOrder: 1 }),
      ])
      mockApi.listFolders.mockResolvedValue([])
      mockApi.reorderPhotos.mockResolvedValue(undefined)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.reorderPhotos([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(wall.photos.value[0].id).toBe(2)
      expect(wall.photos.value[1].id).toBe(1)
    })
  })

  describe('createFolder', () => {
    it('creates and appends to folders list', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])
      const newFolder = makeFolder({ id: 10, name: 'New' })
      mockApi.createFolder.mockResolvedValue(newFolder)

      const wall = usePhotoWall(1)
      await wall.load()
      const result = await wall.createFolder('New')

      expect(mockApi.createFolder).toHaveBeenCalledWith(1, 'New')
      expect(result).toEqual(newFolder)
      expect(wall.folders.value).toHaveLength(1)
    })
  })

  describe('updateFolder', () => {
    it('updates folder in the list', async () => {
      const original = makeFolder({ id: 1, name: 'Old' })
      const updated = makeFolder({ id: 1, name: 'New' })
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([original])
      mockApi.updateFolder.mockResolvedValue(updated)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.updateFolder(1, { name: 'New' })

      expect(wall.folders.value[0].name).toBe('New')
    })
  })

  describe('removeFolder', () => {
    it('removes folder and moves its photos to root', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: 5 }),
        makePhoto({ id: 2, folderId: null }),
      ])
      mockApi.listFolders.mockResolvedValue([makeFolder({ id: 5 })])
      mockApi.deleteFolder.mockResolvedValue(undefined)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.removeFolder(5)

      expect(wall.folders.value).toHaveLength(0)
      // Photo that was in folder 5 should now have folderId null
      expect(wall.photos.value[0].folderId).toBeNull()
      expect(wall.photos.value[1].folderId).toBeNull()
    })
  })

  describe('reorderFolders', () => {
    it('updates sort orders locally and sorts', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([
        makeFolder({ id: 1, sortOrder: 0 }),
        makeFolder({ id: 2, sortOrder: 1 }),
      ])
      mockApi.reorderFolders.mockResolvedValue(undefined)

      const wall = usePhotoWall(1)
      await wall.load()
      await wall.reorderFolders([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(wall.folders.value[0].id).toBe(2)
      expect(wall.folders.value[1].id).toBe(1)
    })
  })
})
