import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Note } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
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
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcRichText', () => ({
  default: {
    name: 'NcRichText',
    template: '<div class="nc-rich-text">{{ text }}</div>',
    props: ['text', 'useMarkdown', 'useExtendedMarkdown'],
  },
}))

import NoteCard from './NoteCard.vue'

function makeNote(overrides: Partial<Note> = {}): Note {
  return {
    id: 1,
    houseId: 1,
    title: 'Groceries',
    content: null,
    color: null,
    createdBy: 'admin',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('NoteCard', () => {
  describe('rendering', () => {
    it('renders the title', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote({ title: 'My Note' }) } })
      expect(wrapper.find('.note-card__title').text()).toBe('My Note')
    })

    it('renders content with NcRichText when present', () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote({ content: '**bold**' }) },
      })
      expect(wrapper.find('.note-card__content').exists()).toBe(true)
      expect(wrapper.find('.nc-rich-text').text()).toBe('**bold**')
    })

    it('does not render content section when null', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      expect(wrapper.find('.note-card__content').exists()).toBe(false)
    })

    it('applies color as CSS custom properties', () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote({ color: '#ff0000' }) },
      })
      const style = wrapper.find('.note-card').attributes('style')
      expect(style).toContain('--note-bg: #ff0000')
      expect(style).toContain('--note-fg:')
    })

    it('uses white text on dark background', () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote({ color: '#1a1a1a' }) },
      })
      const style = wrapper.find('.note-card').attributes('style')
      expect(style).toContain('--note-fg: #ffffff')
    })

    it('uses black text on light background', () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote({ color: '#ffeb3b' }) },
      })
      const style = wrapper.find('.note-card').attributes('style')
      expect(style).toContain('--note-fg: #000000')
    })

    it('has no inline style when no color', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      expect(wrapper.find('.note-card').attributes('style')).toBeUndefined()
    })

    it('is draggable', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      expect(wrapper.find('.note-card').attributes('draggable')).toBe('true')
    })
  })

  describe('actions', () => {
    it('shows delete action', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      const texts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(texts).toContain('Delete')
    })

    it('does not emit edit when actions wrapper is clicked', async () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      await wrapper.find('.note-card__actions').trigger('click')
      expect(wrapper.emitted('edit')).toBeFalsy()
    })
  })

  describe('events', () => {
    it('emits edit on card click', async () => {
      const note = makeNote()
      const wrapper = mount(NoteCard, { props: { note } })
      await wrapper.find('.note-card').trigger('click')
      expect(wrapper.emitted('edit')).toBeTruthy()
      expect(wrapper.emitted('edit')![0]).toEqual([note])
    })

    it('emits delete when Delete action is clicked', async () => {
      const note = makeNote()
      const wrapper = mount(NoteCard, { props: { note } })
      const delBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Delete')!
      await delBtn.trigger('click')
      expect(wrapper.emitted('delete')).toBeTruthy()
      expect(wrapper.emitted('delete')![0]).toEqual([note])
    })

    it('emits drag-start on dragstart', async () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote({ id: 7 }) } })
      await wrapper.find('.note-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeTruthy()
      expect(wrapper.emitted('drag-start')![0]).toEqual([7])
    })

    it('applies dragging class on dragstart and removes on dragend', async () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      const card = wrapper.find('.note-card')

      await card.trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(card.classes()).toContain('note-card--dragging')

      await card.trigger('dragend')
      expect(card.classes()).not.toContain('note-card--dragging')
    })
  })

  describe('draggableEnabled', () => {
    it('is draggable by default', () => {
      const wrapper = mount(NoteCard, { props: { note: makeNote() } })
      expect(wrapper.find('.note-card').attributes('draggable')).toBe('true')
    })

    it('is not draggable when draggableEnabled is false', () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote(), draggableEnabled: false },
      })
      expect(wrapper.find('.note-card').attributes('draggable')).toBe('false')
    })

    it('does not emit drag-start when draggableEnabled is false', async () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote({ id: 7 }), draggableEnabled: false },
      })
      await wrapper.find('.note-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeFalsy()
    })

    it('does not apply dragging class when draggableEnabled is false', async () => {
      const wrapper = mount(NoteCard, {
        props: { note: makeNote(), draggableEnabled: false },
      })
      await wrapper.find('.note-card').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.find('.note-card').classes()).not.toContain('note-card--dragging')
    })
  })
})
