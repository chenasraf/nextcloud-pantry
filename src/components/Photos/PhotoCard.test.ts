import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Photo } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
  generateOcsUrl: (path: string, params: Record<string, unknown>) => {
    let url = path
    for (const [key, value] of Object.entries(params)) {
      url = url.replace(`{${key}}`, String(value))
    }
    return url
  },
}))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/ArrowUp.vue', () => createIconMock('ArrowUpIcon'))

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
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
  },
}))

import PhotoCard from './PhotoCard.vue'

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

function mountCard(overrides: Partial<Photo> = {}, reorderEnabled = true) {
  return mount(PhotoCard, { props: { photo: makePhoto(overrides), houseId: 1, reorderEnabled } })
}

describe('PhotoCard', () => {
  describe('rendering', () => {
    it('renders an image with correct preview URL', () => {
      const wrapper = mountCard()
      const img = wrapper.find('.photo-card__img')
      expect(img.exists()).toBe(true)
      expect(img.attributes('src')).toContain('photos/1/preview')
      expect(img.attributes('src')).toContain('size=300')
    })

    it('shows caption when provided', () => {
      const wrapper = mountCard({ caption: 'Test caption' })
      const caption = wrapper.find('.photo-card__caption')
      expect(caption.exists()).toBe(true)
      expect(caption.text()).toBe('Test caption')
    })

    it('does not show caption when null', () => {
      const wrapper = mountCard()
      expect(wrapper.find('.photo-card__caption').exists()).toBe(false)
    })

    it('uses caption as alt text on image', () => {
      const wrapper = mountCard({ caption: 'Alt text' })
      expect(wrapper.find('.photo-card__img').attributes('alt')).toBe('Alt text')
    })

    it('is draggable', () => {
      const wrapper = mountCard()
      expect(wrapper.find('.photo-card').attributes('draggable')).toBe('true')
    })
  })

  describe('actions', () => {
    it('always shows Edit action', () => {
      const wrapper = mountCard()
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).toContain('Edit')
    })

    it('always shows Delete action', () => {
      const wrapper = mountCard()
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).toContain('Delete')
    })

    it('shows "Move to board" action when photo is in a folder', () => {
      const wrapper = mountCard({ folderId: 5 })
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).toContain('Move to board')
    })

    it('hides "Move to board" action when photo is at root', () => {
      const wrapper = mountCard({ folderId: null })
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).not.toContain('Move to board')
    })
  })

  describe('events', () => {
    it('emits preview on click', async () => {
      const photo = makePhoto()
      const wrapper = mount(PhotoCard, { props: { photo, houseId: 1 } })
      await wrapper.find('.photo-card').trigger('click')
      expect(wrapper.emitted('preview')).toBeTruthy()
      expect(wrapper.emitted('preview')![0]).toEqual([photo])
    })

    it('emits edit when Edit action is clicked', async () => {
      const photo = makePhoto()
      const wrapper = mount(PhotoCard, { props: { photo, houseId: 1 } })
      const editBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Edit')!
      await editBtn.trigger('click')
      expect(wrapper.emitted('edit')).toBeTruthy()
      expect(wrapper.emitted('edit')![0]).toEqual([photo])
    })

    it('emits delete when Delete action is clicked', async () => {
      const photo = makePhoto()
      const wrapper = mount(PhotoCard, { props: { photo, houseId: 1 } })
      const delBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Delete')!
      await delBtn.trigger('click')
      expect(wrapper.emitted('delete')).toBeTruthy()
      expect(wrapper.emitted('delete')![0]).toEqual([photo])
    })

    it('emits move-to-root when "Move to board" is clicked', async () => {
      const photo = makePhoto({ folderId: 5 })
      const wrapper = mount(PhotoCard, { props: { photo, houseId: 1 } })
      const moveBtn = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text() === 'Move to board')!
      await moveBtn.trigger('click')
      expect(wrapper.emitted('move-to-root')).toBeTruthy()
      expect(wrapper.emitted('move-to-root')![0]).toEqual([photo])
    })

    it('does not emit preview when actions wrapper is clicked', async () => {
      const wrapper = mountCard()
      await wrapper.find('.photo-card__actions').trigger('click')
      expect(wrapper.emitted('preview')).toBeFalsy()
    })

    it('emits drag-start on dragstart', async () => {
      const photo = makePhoto({ id: 7 })
      const wrapper = mount(PhotoCard, { props: { photo, houseId: 1 } })
      await wrapper.find('.photo-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeTruthy()
      expect(wrapper.emitted('drag-start')![0]).toEqual([7])
    })

    it('applies dragging class on dragstart and removes on dragend', async () => {
      const wrapper = mountCard()
      const card = wrapper.find('.photo-card')

      await card.trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(card.classes()).toContain('photo-card--dragging')

      await card.trigger('dragend')
      expect(card.classes()).not.toContain('photo-card--dragging')
    })
  })

  describe('reorderEnabled', () => {
    it('is draggable even when reorder is disabled', () => {
      const wrapper = mountCard({}, false)
      expect(wrapper.find('.photo-card').attributes('draggable')).toBe('true')
    })

    it('does not emit drag-start when reorder is disabled', async () => {
      const wrapper = mountCard({ id: 7 }, false)
      await wrapper.find('.photo-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeFalsy()
    })

    it('still sets drag data when reorder is disabled (for folder drops)', async () => {
      const setData = vi.fn()
      const wrapper = mountCard({ id: 3, folderId: null }, false)
      await wrapper.find('.photo-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData },
      })
      expect(setData).toHaveBeenCalledWith(
        'application/x-pantry-photo',
        JSON.stringify({ id: 3, folderId: null }),
      )
    })

    it('does not emit reorder-over when reorder is disabled', async () => {
      const wrapper = mountCard({}, false)
      await wrapper.find('.photo-card').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-photo'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeFalsy()
    })

    it('emits reorder-over when reorder is enabled', async () => {
      const wrapper = mountCard({ id: 2 }, true)
      await wrapper.find('.photo-card').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-photo'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeTruthy()
    })
  })
})
