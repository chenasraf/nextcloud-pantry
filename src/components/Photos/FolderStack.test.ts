import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Photo, PhotoFolder } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
}))
vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))

vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions"><slot /></div>',
    props: ['ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template: '<button class="nc-action-button"><slot name="icon" /><slot /></button>',
  },
}))

import FolderStack from './FolderStack.vue'

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

function makePhoto(id: number, folderId: number): Photo {
  return {
    id,
    houseId: 1,
    folderId,
    fileId: id + 100,
    caption: null,
    uploadedBy: 'admin',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
  }
}

describe('FolderStack', () => {
  describe('rendering', () => {
    it('renders the folder name overlaid at the bottom', () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder({ name: 'My Folder' }), photos: [] },
      })
      expect(wrapper.find('.folder-stack__label').text()).toBe('My Folder')
    })

    it('shows empty icon when no photos', () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      expect(wrapper.find('.folder-stack__empty').exists()).toBe(true)
      expect(wrapper.findAll('.folder-stack__photo')).toHaveLength(0)
    })

    it('shows up to 5 photo thumbnails', () => {
      const photos = Array.from({ length: 7 }, (_, i) => makePhoto(i + 1, 1))
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos },
      })
      expect(wrapper.findAll('.folder-stack__photo')).toHaveLength(5)
    })

    it('shows all photos when 5 or fewer', () => {
      const photos = Array.from({ length: 3 }, (_, i) => makePhoto(i + 1, 1))
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos },
      })
      expect(wrapper.findAll('.folder-stack__photo')).toHaveLength(3)
    })

    it('shows photo count badge', () => {
      const photos = [makePhoto(1, 1), makePhoto(2, 1)]
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos },
      })
      expect(wrapper.find('.folder-stack__count').text()).toBe('2')
    })

    it('does not show count badge when empty', () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      expect(wrapper.find('.folder-stack__count').exists()).toBe(false)
    })

    it('applies unique rotation transforms to stacked photos', () => {
      const photos = [makePhoto(1, 1), makePhoto(2, 1), makePhoto(3, 1)]
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos },
      })
      const imgs = wrapper.findAll('.folder-stack__photo')
      const transforms = imgs.map((img) => img.attributes('style'))
      const unique = new Set(transforms)
      expect(unique.size).toBe(3)
    })

    it('is draggable', () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      expect(wrapper.find('.folder-stack').attributes('draggable')).toBe('true')
    })
  })

  describe('actions', () => {
    it('has rename and delete actions', () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).toContain('Rename')
      expect(texts).toContain('Delete')
    })

    it('emits rename when Rename action is clicked', async () => {
      const folder = makeFolder()
      const wrapper = mount(FolderStack, { props: { folder, photos: [] } })
      const renameBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Rename')!
      await renameBtn.trigger('click')
      expect(wrapper.emitted('rename')).toBeTruthy()
      expect(wrapper.emitted('rename')![0]).toEqual([folder])
    })

    it('emits delete when Delete action is clicked', async () => {
      const folder = makeFolder()
      const wrapper = mount(FolderStack, { props: { folder, photos: [] } })
      const delBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Delete')!
      await delBtn.trigger('click')
      expect(wrapper.emitted('delete')).toBeTruthy()
      expect(wrapper.emitted('delete')![0]).toEqual([folder])
    })

    it('does not emit open when actions wrapper is clicked', async () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      await wrapper.find('.folder-stack__actions').trigger('click')
      expect(wrapper.emitted('open')).toBeFalsy()
    })
  })

  describe('events', () => {
    it('emits open on click', async () => {
      const folder = makeFolder()
      const wrapper = mount(FolderStack, { props: { folder, photos: [] } })
      await wrapper.find('.folder-stack').trigger('click')
      expect(wrapper.emitted('open')).toBeTruthy()
      expect(wrapper.emitted('open')![0]).toEqual([folder])
    })

    it('shows drag-over style on dragover with photo data', async () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      await wrapper.find('.folder-stack').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-photo'] },
      })
      expect(wrapper.find('.folder-stack').classes()).toContain('folder-stack--drag-over')
    })

    it('removes drag-over style on dragleave', async () => {
      const wrapper = mount(FolderStack, {
        props: { folder: makeFolder(), photos: [] },
      })
      await wrapper.find('.folder-stack').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-photo'] },
      })
      await wrapper.find('.folder-stack').trigger('dragleave')
      expect(wrapper.find('.folder-stack').classes()).not.toContain('folder-stack--drag-over')
    })
  })
})
