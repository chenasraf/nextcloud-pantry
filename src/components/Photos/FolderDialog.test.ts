import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { PhotoFolder } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button class="nc-button" :disabled="disabled"><slot /></button>',
    props: ['variant', 'form', 'type', 'disabled'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder'],
    emits: ['update:modelValue'],
  },
}))

import FolderDialog from './FolderDialog.vue'

function makeFolder(overrides: Partial<PhotoFolder> = {}): PhotoFolder {
  return {
    id: 1,
    houseId: 1,
    name: 'Existing Folder',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('FolderDialog', () => {
  describe('create mode', () => {
    it('renders dialog when open', () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('starts with empty name field', () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      const input = wrapper.find('.nc-text-field')
      expect((input.element as HTMLInputElement).value).toBe('')
    })

    it('uses "Create folder" as title', () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('name')).toBe('Create folder')
    })

    it('disables save button when name is empty', () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      const saveBtn = wrapper.findAll('.nc-button').find((b) => b.text() === 'Save')!
      expect(saveBtn.attributes('disabled')).toBeDefined()
    })
  })

  describe('edit mode', () => {
    it('pre-fills name when editing an existing folder', () => {
      const wrapper = mount(FolderDialog, {
        props: { open: true, folder: makeFolder() },
      })
      const input = wrapper.find('.nc-text-field')
      expect((input.element as HTMLInputElement).value).toBe('Existing Folder')
    })

    it('uses "Rename folder" as title', () => {
      const wrapper = mount(FolderDialog, {
        props: { open: true, folder: makeFolder() },
      })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('name')).toBe('Rename folder')
    })
  })

  describe('submission', () => {
    it('emits save with trimmed name on submit', async () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      await wrapper.find('.nc-text-field').setValue('  New Name  ')
      await wrapper.find('form').trigger('submit')
      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0]).toEqual(['New Name'])
    })

    it('does not emit save with empty name', async () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      await wrapper.find('form').trigger('submit')
      expect(wrapper.emitted('save')).toBeFalsy()
    })

    it('does not emit save with whitespace-only name', async () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      await wrapper.find('.nc-text-field').setValue('   ')
      await wrapper.find('form').trigger('submit')
      expect(wrapper.emitted('save')).toBeFalsy()
    })

    it('emits update:open false when cancel is clicked', async () => {
      const wrapper = mount(FolderDialog, { props: { open: true } })
      const cancelBtn = wrapper.findAll('.nc-button').find((b) => b.text() === 'Cancel')!
      await cancelBtn.trigger('click')
      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })
})
