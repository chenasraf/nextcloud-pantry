import { mount } from '@vue/test-utils'
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Note } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))

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
    template:
      '<button class="nc-button" :disabled="disabled"><slot name="icon" /><slot /></button>',
    props: ['variant', 'form', 'type', 'disabled', 'ariaLabel'],
  },
}))
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'maxHeight', 'rows'],
    emits: ['update:modelValue'],
    methods: {
      getTextareaEl() {
        return this.$el?.tagName === 'TEXTAREA' ? this.$el : this.$el?.querySelector('textarea')
      },
      resize() {},
    },
  },
}))
vi.mock('@nextcloud/vue/components/NcRichText', () => ({
  default: {
    name: 'NcRichText',
    template: '<div class="nc-rich-text">{{ text }}</div>',
    props: ['text', 'useMarkdown', 'useExtendedMarkdown'],
  },
}))

import NoteDialog from './NoteDialog.vue'

function makeNote(overrides: Partial<Note> = {}): Note {
  return {
    id: 1,
    houseId: 1,
    title: 'Existing Note',
    content: 'Some content',
    color: '#f44336',
    createdBy: 'admin',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('NoteDialog', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('create mode (no note)', () => {
    it('opens in edit mode with editable fields', () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(true)
      expect(wrapper.find('.nc-text-area').exists()).toBe(true)
    })

    it('starts with empty fields', () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      expect((wrapper.find('.note-dialog__title-input').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('.nc-text-area').element as HTMLTextAreaElement).value).toBe('')
    })

    it('saves on close when title is set', async () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      await wrapper.find('.note-dialog__title-input').setValue('New Note')
      await wrapper.find('.nc-text-area').setValue('Content')
      // Simulate dialog close (click outside / X button)
      wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
      await wrapper.vm.$nextTick()
      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0][0].title).toBe('New Note')
    })

    it('does not save on close when title is empty', async () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
      await wrapper.vm.$nextTick()
      expect(wrapper.emitted('save')).toBeFalsy()
    })
  })

  describe('view mode (existing note)', () => {
    it('opens in view mode showing rendered content', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      expect(wrapper.find('.nc-rich-text').exists()).toBe(true)
      expect(wrapper.find('.nc-text-area').exists()).toBe(false)
    })

    it('shows title as heading text', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ title: 'My Note' }) },
      })
      expect(wrapper.find('.note-dialog__title-text').text()).toBe('My Note')
    })

    it('shows empty message when no content', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ content: null }) },
      })
      expect(wrapper.find('.note-dialog__empty').exists()).toBe(true)
    })

    it('switches to edit mode on content click', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      await wrapper.find('.note-dialog__content').trigger('click')
      expect(wrapper.find('.nc-text-area').exists()).toBe(true)
    })

    it('switches to edit mode on title click', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      await wrapper.find('.note-dialog__title-text').trigger('click')
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(true)
    })

    it('does not show title input in view mode', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(false)
    })

    it('passes empty name to NcDialog', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      const dialog = wrapper.findComponent({ name: 'NcDialog' })
      expect(dialog.props('name')).toBe('')
    })
  })

  describe('edit/view toggle button', () => {
    it('shows edit icon in view mode', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      expect(wrapper.find('.mock-pencil-icon').exists()).toBe(true)
    })

    it('shows eye icon in edit mode', () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      expect(wrapper.find('.mock-eye-icon').exists()).toBe(true)
    })

    it('toggles between edit and view on click', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Start in view mode — no text field
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(false)
      // Click toggle to switch to edit
      const toggleBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.find('.mock-pencil-icon').exists())!
      await toggleBtn.trigger('click')
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(true)
      // Click toggle to switch back to view
      const viewBtn = wrapper.findAll('.nc-button').find((b) => b.find('.mock-eye-icon').exists())!
      await viewBtn.trigger('click')
      expect(wrapper.find('.note-dialog__title-input').exists()).toBe(false)
    })
  })

  describe('auto-save', () => {
    it('debounces saves on text changes', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Switch to edit mode via toggle
      const toggleBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.find('.mock-pencil-icon').exists())!
      await toggleBtn.trigger('click')
      await wrapper.find('.note-dialog__title-input').setValue('Updated Title')

      expect(wrapper.emitted('save')).toBeFalsy()
      vi.advanceTimersByTime(1000)
      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0][0].title).toBe('Updated Title')
    })

    it('debounces saves on content changes', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Switch to edit mode
      const toggleBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.find('.mock-pencil-icon').exists())!
      await toggleBtn.trigger('click')
      await wrapper.find('.nc-text-area').setValue('New content')

      expect(wrapper.emitted('save')).toBeFalsy()
      vi.advanceTimersByTime(1000)
      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0][0].content).toBe('New content')
    })

    it('flushes save when toggling back to view mode', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Switch to edit
      const editBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.find('.mock-pencil-icon').exists())!
      await editBtn.trigger('click')
      await wrapper.find('.note-dialog__title-input').setValue('Modified')
      // Toggle back to view (should flush)
      const viewBtn = wrapper.findAll('.nc-button').find((b) => b.find('.mock-eye-icon').exists())!
      await viewBtn.trigger('click')
      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0][0].title).toBe('Modified')
    })

    it('does not auto-save for new notes', async () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      await wrapper.find('.note-dialog__title-input').setValue('New Title')
      vi.advanceTimersByTime(1000)
      // Should not auto-save — only saves on close
      expect(wrapper.emitted('save')).toBeFalsy()
    })

    it('flushes save on close', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Switch to edit mode via toggle
      const toggleBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.find('.mock-pencil-icon').exists())!
      await toggleBtn.trigger('click')
      await wrapper.find('.note-dialog__title-input').setValue('Changed')
      // Simulate dialog close
      wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
      await wrapper.vm.$nextTick()
      expect(wrapper.emitted('save')).toBeTruthy()
    })
  })

  describe('color swatches', () => {
    it('renders 17 swatches (no-color + 16 colors)', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      expect(wrapper.findAll('.note-dialog__swatch')).toHaveLength(17)
    })

    it('has no-color swatch active by default when note has no color', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ color: null }) },
      })
      const noColor = wrapper.find('.note-dialog__swatch--none')
      expect(noColor.classes()).toContain('note-dialog__swatch--active')
    })

    it('toggles color on swatch click', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ color: null }) },
      })
      // Click a color swatch (skip the first "no color" swatch)
      const colorSwatch = wrapper.findAll('.note-dialog__swatch').at(1)!
      await colorSwatch.trigger('click')
      expect(colorSwatch.classes()).toContain('note-dialog__swatch--active')
      await colorSwatch.trigger('click')
      expect(colorSwatch.classes()).not.toContain('note-dialog__swatch--active')
    })

    it('saves immediately on color change for existing notes', async () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      await wrapper.findAll('.note-dialog__swatch').at(5)!.trigger('click')
      expect(wrapper.emitted('save')).toBeTruthy()
    })

    it('pre-selects existing note color', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ color: '#f44336' }) },
      })
      const activeSwatch = wrapper.find('.note-dialog__swatch--active')
      expect(activeSwatch.exists()).toBe(true)
      expect(activeSwatch.attributes('style')).toContain('#f44336')
    })

    it('uses contrast color for active swatch border', () => {
      // Dark color → white contrast → white border
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote({ color: '#3f51b5' }) },
      })
      const activeSwatch = wrapper.find('.note-dialog__swatch--active')
      expect(activeSwatch.attributes('style')).toContain('border-color: #ffffff')
    })

    it('does not save on color change for new notes', async () => {
      const wrapper = mount(NoteDialog, { props: { open: true } })
      await wrapper.find('.note-dialog__swatch').trigger('click')
      vi.advanceTimersByTime(1000)
      // New notes don't auto-save
      expect(wrapper.emitted('save')).toBeFalsy()
    })

    it('shows swatches in view mode too', () => {
      const wrapper = mount(NoteDialog, {
        props: { open: true, note: makeNote() },
      })
      // Should be in view mode
      expect(wrapper.find('.nc-text-area').exists()).toBe(false)
      // Swatches still visible
      expect(wrapper.findAll('.note-dialog__swatch')).toHaveLength(17)
    })
  })
})
