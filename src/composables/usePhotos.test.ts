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

import { usePhotos } from './usePhotos'

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

describe('usePhotos', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('load', () => {
    it('loads photos and folders in parallel', async () => {
      const photos = [makePhoto({ id: 1 }), makePhoto({ id: 2, folderId: 5 })]
      const folders = [makeFolder({ id: 5 })]
      mockApi.listPhotos.mockResolvedValue(photos)
      mockApi.listFolders.mockResolvedValue(folders)

      const board = usePhotos(1)
      await board.load()

      expect(board.photos.value).toEqual(photos)
      expect(board.folders.value).toEqual(folders)
      expect(board.loading.value).toBe(false)
      expect(board.error.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockApi.listPhotos.mockRejectedValue(new Error('Network error'))
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      await board.load()

      expect(board.error.value).toBe('Network error')
      expect(board.loading.value).toBe(false)
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

      const board = usePhotos(1)
      await board.load()

      expect(board.rootPhotos.value).toHaveLength(2)
      expect(board.rootPhotos.value.map((p) => p.id)).toEqual([1, 3])
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

      const board = usePhotos(1)
      await board.load()

      expect(board.photosInFolder(5)).toHaveLength(2)
      expect(board.photosInFolder(5).map((p) => p.id)).toEqual([2, 3])
      expect(board.photosInFolder(7)).toHaveLength(1)
      expect(board.photosInFolder(99)).toHaveLength(0)
    })
  })

  describe('upload', () => {
    it('uploads and appends to photos list', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])
      const newPhoto = makePhoto({ id: 10, fileId: 100 })
      mockApi.uploadPhoto.mockResolvedValue(newPhoto)

      const board = usePhotos(1)
      await board.load()
      const file = new File(['data'], 'test.jpg')
      const result = await board.upload(file, 5)

      expect(mockApi.uploadPhoto).toHaveBeenCalledWith(1, file, 5, null, expect.any(Function))
      expect(result.id).toBe(newPhoto.id)
      expect(board.photos.value).toHaveLength(1)
      expect(board.photos.value[0].id).toBe(newPhoto.id)
    })
  })

  describe('updatePhoto', () => {
    it('updates photo in the list', async () => {
      const original = makePhoto({ id: 1, caption: 'Old' })
      const updated = makePhoto({ id: 1, caption: 'New' })
      mockApi.listPhotos.mockResolvedValue([original])
      mockApi.listFolders.mockResolvedValue([])
      mockApi.updatePhoto.mockResolvedValue(updated)

      const board = usePhotos(1)
      await board.load()
      await board.updatePhoto(1, { caption: 'New' })

      expect(board.photos.value[0].caption).toBe('New')
    })
  })

  describe('removePhoto', () => {
    it('removes photo from the list', async () => {
      mockApi.listPhotos.mockResolvedValue([makePhoto({ id: 1 }), makePhoto({ id: 2 })])
      mockApi.listFolders.mockResolvedValue([])
      mockApi.deletePhoto.mockResolvedValue(undefined)

      const board = usePhotos(1)
      await board.load()
      await board.removePhoto(1)

      expect(board.photos.value).toHaveLength(1)
      expect(board.photos.value[0].id).toBe(2)
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

      const board = usePhotos(1)
      await board.load()
      await board.reorderPhotos([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(board.photos.value[0].id).toBe(2)
      expect(board.photos.value[1].id).toBe(1)
    })
  })

  describe('createFolder', () => {
    it('creates and appends to folders list', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])
      const newFolder = makeFolder({ id: 10, name: 'New' })
      mockApi.createFolder.mockResolvedValue(newFolder)

      const board = usePhotos(1)
      await board.load()
      const result = await board.createFolder('New')

      expect(mockApi.createFolder).toHaveBeenCalledWith(1, 'New')
      expect(result).toEqual(newFolder)
      expect(board.folders.value).toHaveLength(1)
    })
  })

  describe('updateFolder', () => {
    it('updates folder in the list', async () => {
      const original = makeFolder({ id: 1, name: 'Old' })
      const updated = makeFolder({ id: 1, name: 'New' })
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([original])
      mockApi.updateFolder.mockResolvedValue(updated)

      const board = usePhotos(1)
      await board.load()
      await board.updateFolder(1, { name: 'New' })

      expect(board.folders.value[0].name).toBe('New')
    })
  })

  describe('removeFolder', () => {
    it('removes folder and moves its photos to root by default', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: 5 }),
        makePhoto({ id: 2, folderId: null }),
      ])
      mockApi.listFolders.mockResolvedValue([makeFolder({ id: 5 })])
      mockApi.deleteFolder.mockResolvedValue(undefined)

      const board = usePhotos(1)
      await board.load()
      await board.removeFolder(5)

      expect(board.folders.value).toHaveLength(0)
      expect(board.photos.value[0].folderId).toBeNull()
      expect(board.photos.value[1].folderId).toBeNull()
      expect(mockApi.deleteFolder).toHaveBeenCalledWith(1, 5, false)
    })

    it('removes folder and deletes photos when deleteContents is true', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: 5 }),
        makePhoto({ id: 2, folderId: null }),
        makePhoto({ id: 3, folderId: 5 }),
      ])
      mockApi.listFolders.mockResolvedValue([makeFolder({ id: 5 })])
      mockApi.deleteFolder.mockResolvedValue(undefined)

      const board = usePhotos(1)
      await board.load()
      await board.removeFolder(5, true)

      expect(board.folders.value).toHaveLength(0)
      expect(board.photos.value).toHaveLength(1)
      expect(board.photos.value[0].id).toBe(2)
      expect(mockApi.deleteFolder).toHaveBeenCalledWith(1, 5, true)
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

      const board = usePhotos(1)
      await board.load()
      await board.reorderFolders([
        { id: 2, sortOrder: 0 },
        { id: 1, sortOrder: 1 },
      ])

      expect(board.folders.value[0].id).toBe(2)
      expect(board.folders.value[1].id).toBe(1)
    })
  })

  describe('sortBy', () => {
    it('defaults to custom', () => {
      const board = usePhotos(1)
      expect(board.sortBy.value).toBe('custom')
    })

    it('passes sortBy value to listPhotos and listFolders', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      board.sortBy.value = 'newest'
      await board.load()

      expect(mockApi.listPhotos).toHaveBeenCalledWith(1, 'newest')
      expect(mockApi.listFolders).toHaveBeenCalledWith(1, 'newest')
    })

    it('uses sort argument when provided to load()', async () => {
      mockApi.listPhotos.mockResolvedValue([])
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      board.sortBy.value = 'custom'
      await board.load('description_asc')

      expect(mockApi.listPhotos).toHaveBeenCalledWith(1, 'description_asc')
      expect(mockApi.listFolders).toHaveBeenCalledWith(1, 'description_asc')
    })

    it('rootPhotos sorts by sortOrder in custom mode', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 1, folderId: null, sortOrder: 2 }),
        makePhoto({ id: 2, folderId: null, sortOrder: 0 }),
        makePhoto({ id: 3, folderId: null, sortOrder: 1 }),
      ])
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      await board.load()

      expect(board.rootPhotos.value.map((p) => p.id)).toEqual([2, 3, 1])
    })

    it('rootPhotos preserves server order in non-custom mode', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 3, folderId: null, sortOrder: 2 }),
        makePhoto({ id: 1, folderId: null, sortOrder: 0 }),
        makePhoto({ id: 2, folderId: null, sortOrder: 1 }),
      ])
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      board.sortBy.value = 'newest'
      await board.load()

      // Should preserve the array order from the server
      expect(board.rootPhotos.value.map((p) => p.id)).toEqual([3, 1, 2])
    })

    it('photosInFolder preserves server order in non-custom mode', async () => {
      mockApi.listPhotos.mockResolvedValue([
        makePhoto({ id: 3, folderId: 5, sortOrder: 2 }),
        makePhoto({ id: 1, folderId: 5, sortOrder: 0 }),
      ])
      mockApi.listFolders.mockResolvedValue([])

      const board = usePhotos(1)
      board.sortBy.value = 'oldest'
      await board.load()

      expect(board.photosInFolder(5).map((p) => p.id)).toEqual([3, 1])
    })
  })
})
