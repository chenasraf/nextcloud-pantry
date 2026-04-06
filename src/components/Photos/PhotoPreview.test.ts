import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { Photo } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button class="nc-button"><slot /></button>',
    props: ['variant'],
  },
}))

import PhotoPreview from './PhotoPreview.vue'

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

describe('PhotoPreview', () => {
  describe('rendering', () => {
    it('renders a large preview image', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto() },
      })
      const img = wrapper.find('.photo-preview__img')
      expect(img.exists()).toBe(true)
      expect(img.attributes('src')).toContain('fileId=42')
      expect(img.attributes('src')).toContain('x=1600')
      expect(img.attributes('src')).toContain('y=1600')
    })

    it('renders with different file IDs', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto({ fileId: 99 }) },
      })
      expect(wrapper.find('.photo-preview__img').attributes('src')).toContain('fileId=99')
    })

    it('opens dialog in large size', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto() },
      })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('size')).toBe('large')
    })
  })

  describe('title', () => {
    it('uses caption as dialog title when available', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto({ caption: 'My Photo' }) },
      })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('name')).toBe('My Photo')
    })

    it('uses fallback title when no caption', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto() },
      })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('name')).toBe('Photo preview')
    })
  })

  describe('actions', () => {
    it('has a close button', () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto() },
      })
      const button = wrapper.find('.nc-button')
      expect(button.text()).toBe('Close')
    })

    it('emits update:open false when close is clicked', async () => {
      const wrapper = mount(PhotoPreview, {
        props: { open: true, photo: makePhoto() },
      })
      await wrapper.find('.nc-button').trigger('click')
      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })
})
