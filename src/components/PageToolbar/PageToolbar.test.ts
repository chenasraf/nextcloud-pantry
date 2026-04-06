import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import PageToolbar from './PageToolbar.vue'

describe('PageToolbar', () => {
  describe('rendering', () => {
    it('renders as a header element', () => {
      const wrapper = mount(PageToolbar)
      expect(wrapper.element.tagName).toBe('HEADER')
      expect(wrapper.classes()).toContain('pantry-toolbar')
    })

    it('renders the title when provided', () => {
      const wrapper = mount(PageToolbar, {
        props: { title: 'Checklists' },
      })

      const h2 = wrapper.find('.pantry-toolbar__title')
      expect(h2.exists()).toBe(true)
      expect(h2.text()).toBe('Checklists')
    })

    it('does not render the title element when omitted', () => {
      const wrapper = mount(PageToolbar)
      expect(wrapper.find('.pantry-toolbar__title').exists()).toBe(false)
    })
  })

  describe('slots', () => {
    it('renders before-title slot content', () => {
      const wrapper = mount(PageToolbar, {
        props: { title: 'Title' },
        slots: {
          'before-title': '<button class="back-btn">Back</button>',
        },
      })

      const left = wrapper.find('.pantry-toolbar__left')
      expect(left.find('.back-btn').exists()).toBe(true)
      // before-title content should come before the h2
      const children = left.element.children
      expect(children[0]!.classList.contains('back-btn')).toBe(true)
      expect(children[1]!.classList.contains('pantry-toolbar__title')).toBe(true)
    })

    it('renders after-title slot content', () => {
      const wrapper = mount(PageToolbar, {
        props: { title: 'Title' },
        slots: {
          'after-title': '<span class="badge">3</span>',
        },
      })

      const left = wrapper.find('.pantry-toolbar__left')
      expect(left.find('.badge').exists()).toBe(true)
    })

    it('renders actions slot and its container', () => {
      const wrapper = mount(PageToolbar, {
        slots: {
          actions: '<button class="action-btn">New</button>',
        },
      })

      const actionsDiv = wrapper.find('.pantry-toolbar__actions')
      expect(actionsDiv.exists()).toBe(true)
      expect(actionsDiv.find('.action-btn').exists()).toBe(true)
    })

    it('does not render actions container when no actions slot', () => {
      const wrapper = mount(PageToolbar, {
        props: { title: 'Title' },
      })

      expect(wrapper.find('.pantry-toolbar__actions').exists()).toBe(false)
    })
  })
})
