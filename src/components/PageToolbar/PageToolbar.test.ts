import { mount } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { markRaw } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import PageToolbar from './PageToolbar.vue'
import type { ToolbarAction } from './types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :aria-pressed="ariaPressed" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'ariaPressed'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template:
      '<div class="nc-actions"><span class="nc-actions__name">{{ menuName }}</span><slot /></div>',
    props: ['menuName', 'ariaLabel', 'title', 'type'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template:
      '<button class="nc-action-button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionCheckbox', () => ({
  default: {
    name: 'NcActionCheckbox',
    template:
      '<label class="nc-action-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionCaption', () => ({
  default: {
    name: 'NcActionCaption',
    template: '<span class="nc-action-caption">{{ name }}</span>',
    props: ['name'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionSeparator', () => ({
  default: {
    name: 'NcActionSeparator',
    template: '<hr class="nc-action-separator" />',
  },
}))
vi.mock('@icons/DotsHorizontal.vue', () => createIconMock('DotsHorizontalIcon'))
vi.mock('@icons/RadioboxBlank.vue', () => createIconMock('RadioboxBlankIcon'))
vi.mock('@icons/RadioboxMarked.vue', () => createIconMock('RadioboxMarkedIcon'))

const StubIcon = markRaw({ name: 'StubIcon', template: '<span class="stub-icon" />' })

describe('PageToolbar', () => {
  beforeAll(() => {
    // happy-dom does not implement ResizeObserver; the toolbar only uses it to
    // recompute overflow on resize, so a no-op stub is enough for unit tests.
    if (!('ResizeObserver' in globalThis)) {
      globalThis.ResizeObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
      } as unknown as typeof ResizeObserver
    }
  })

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

    it('does not render actions container when no actions slot or prop', () => {
      const wrapper = mount(PageToolbar, {
        props: { title: 'Title' },
      })

      expect(wrapper.find('.pantry-toolbar__actions').exists()).toBe(false)
    })
  })

  describe('actions prop', () => {
    const actions: ToolbarAction[] = [
      {
        key: 'sort',
        type: 'menu',
        label: 'Sort by: Newest',
        caption: 'Sort order',
        icon: StubIcon,
        options: [
          { key: 'newest', label: 'Newest', active: true, onClick: () => {} },
          { key: 'oldest', label: 'Oldest', active: false, onClick: () => {} },
        ],
      },
      { key: 'edit', label: 'Edit list', icon: StubIcon, onClick: () => {} },
    ]

    it('renders a button action with its label', () => {
      const onClick = vi.fn()
      const wrapper = mount(PageToolbar, {
        props: { actions: [{ key: 'edit', label: 'Edit list', icon: StubIcon, onClick }] },
      })

      const btn = wrapper.find('.pantry-toolbar__actions .nc-button')
      expect(btn.exists()).toBe(true)
      expect(btn.text()).toContain('Edit list')
    })

    it('fires the button action onClick', async () => {
      const onClick = vi.fn()
      const wrapper = mount(PageToolbar, {
        props: { actions: [{ key: 'edit', label: 'Edit list', icon: StubIcon, onClick }] },
      })

      await wrapper.find('.pantry-toolbar__actions .nc-button').trigger('click')
      expect(onClick).toHaveBeenCalledOnce()
    })

    it('renders a menu action with its current-value label and options', () => {
      const wrapper = mount(PageToolbar, { props: { actions } })

      const menu = wrapper.find('.pantry-toolbar__actions .nc-actions')
      expect(menu.exists()).toBe(true)
      expect(menu.find('.nc-actions__name').text()).toBe('Sort by: Newest')
      expect(menu.findAll('.nc-action-button')).toHaveLength(2)
    })

    it('renders a checkbox option and a separator before the radio group', () => {
      const menuWithCheckbox: ToolbarAction[] = [
        {
          key: 'sort',
          type: 'menu',
          label: 'Sort by: Newest',
          icon: StubIcon,
          options: [
            {
              type: 'checkbox',
              key: 'folders-first',
              label: 'Folders first',
              checked: true,
              onChange: () => {},
            },
            { key: 'newest', label: 'Newest', active: true, onClick: () => {} },
            { key: 'oldest', label: 'Oldest', active: false, onClick: () => {} },
          ],
        },
      ]
      const wrapper = mount(PageToolbar, { props: { actions: menuWithCheckbox } })

      const menu = wrapper.find('.pantry-toolbar__actions .nc-actions')
      expect(menu.find('.nc-action-checkbox').exists()).toBe(true)
      // one separator between the checkbox group and the radio group
      expect(menu.findAll('.nc-action-separator')).toHaveLength(1)
      expect(menu.findAll('.nc-action-button')).toHaveLength(2)
    })
  })
})
