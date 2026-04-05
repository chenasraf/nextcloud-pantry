/**
 * Example test file demonstrating Vue component testing with mocked dependencies.
 *
 * This shows how to:
 * - Mock @nextcloud/l10n translation functions
 * - Mock icon components from vue-material-design-icons
 * - Mount components with props
 * - Test computed properties via wrapper.vm
 * - Test emitted events
 * - Test conditional rendering
 * - Test CSS classes and inline styles
 */
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import StatusBadge from './StatusBadge.vue'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// Mock icon components
vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
vi.mock('@icons/Alert.vue', () => createIconMock('AlertIcon'))
vi.mock('@icons/ClockOutline.vue', () => createIconMock('ClockIcon'))

describe('StatusBadge', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('renders with required status prop', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      expect(wrapper.exists()).toBe(true)
      expect(wrapper.classes()).toContain('status-badge')
    })

    it('renders the label when provided', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', label: 'Completed' },
      })

      expect(wrapper.find('.status-label').text()).toBe('Completed')
    })

    it('renders without label when not provided', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      expect(wrapper.find('.status-label').text()).toBe('')
    })
  })

  describe('CSS classes', () => {
    it('applies correct class for success status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      expect(wrapper.classes()).toContain('status-success')
    })

    it('applies correct class for warning status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'warning' },
      })

      expect(wrapper.classes()).toContain('status-warning')
    })

    it('applies correct class for pending status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'pending' },
      })

      expect(wrapper.classes()).toContain('status-pending')
    })

    it('applies correct class for error status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'error' },
      })

      expect(wrapper.classes()).toContain('status-error')
    })

    it('applies clickable class when clickable prop is true', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', clickable: true },
      })

      expect(wrapper.classes()).toContain('status-clickable')
    })

    it('does not apply clickable class when clickable prop is false', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', clickable: false },
      })

      expect(wrapper.classes()).not.toContain('status-clickable')
    })
  })

  describe('inline styles', () => {
    it('applies custom color style when customColor is provided', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', customColor: '#ff5500' },
      })

      const style = wrapper.attributes('style')
      // Note: happy-dom preserves hex colors instead of converting to RGB
      expect(style).toContain('background-color: #ff5500')
    })

    it('does not apply custom style when customColor is not provided', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      const style = wrapper.attributes('style')
      expect(style).toBeUndefined()
    })
  })

  describe('icon rendering', () => {
    it('renders CheckIcon for success status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', showIcon: true },
      })

      expect(wrapper.find('.mock-check-icon').exists()).toBe(true)
    })

    it('renders AlertIcon for warning status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'warning', showIcon: true },
      })

      expect(wrapper.find('.mock-alert-icon').exists()).toBe(true)
    })

    it('renders ClockIcon for pending status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'pending', showIcon: true },
      })

      expect(wrapper.find('.mock-clock-icon').exists()).toBe(true)
    })

    it('renders AlertIcon for error status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'error', showIcon: true },
      })

      expect(wrapper.find('.mock-alert-icon').exists()).toBe(true)
    })

    it('does not render icon when showIcon is false', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', showIcon: false },
      })

      expect(wrapper.find('.mock-check-icon').exists()).toBe(false)
      expect(wrapper.find('.mock-alert-icon').exists()).toBe(false)
      expect(wrapper.find('.mock-clock-icon').exists()).toBe(false)
    })
  })

  describe('computed properties', () => {
    it('computes correct tooltipText for success status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      // Access computed property via wrapper.vm
      expect((wrapper.vm as InstanceType<typeof StatusBadge>).tooltipText).toBe(
        'Completed successfully',
      )
    })

    it('computes correct tooltipText for error status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'error' },
      })

      expect((wrapper.vm as InstanceType<typeof StatusBadge>).tooltipText).toBe('Failed')
    })

    it('computes statusClass correctly', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'warning', clickable: true },
      })

      const statusClass = (wrapper.vm as InstanceType<typeof StatusBadge>).statusClass
      expect(statusClass).toEqual({
        'status-warning': true,
        'status-clickable': true,
      })
    })

    it('computes customStyle as null when no customColor', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success' },
      })

      expect((wrapper.vm as InstanceType<typeof StatusBadge>).customStyle).toBeNull()
    })

    it('computes customStyle correctly when customColor is set', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', customColor: '#123456' },
      })

      expect((wrapper.vm as InstanceType<typeof StatusBadge>).customStyle).toEqual({
        '--status-color': '#123456',
        backgroundColor: '#123456',
      })
    })
  })

  describe('events', () => {
    it('emits click event when clickable and clicked', async () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', clickable: true },
      })

      await wrapper.trigger('click')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('does not emit click event when not clickable', async () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', clickable: false },
      })

      await wrapper.trigger('click')

      expect(wrapper.emitted('click')).toBeFalsy()
    })

    it('passes the MouseEvent to the click handler', async () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'success', clickable: true },
      })

      await wrapper.trigger('click')

      const emittedEvents = wrapper.emitted('click')
      expect(emittedEvents).toBeTruthy()
      expect(emittedEvents![0][0]).toBeInstanceOf(MouseEvent)
    })
  })

  describe('title attribute', () => {
    it('sets title attribute based on status', () => {
      const wrapper = mount(StatusBadge, {
        props: { status: 'pending' },
      })

      expect(wrapper.attributes('title')).toBe('In progress')
    })
  })
})
