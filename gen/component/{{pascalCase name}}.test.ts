// SPDX-FileCopyrightText: Your Name <your@email.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Unit tests for {{pascalCase name}} component.
 *
 * Run tests:
 *   pnpm test           # watch mode
 *   pnpm test:run       # single run
 *
 * See src/components/StatusBadge.test.ts for a complete example.
 */
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import {{ pascalCase name }} from './{{pascalCase name}}.vue'

// ----------------------------------------------------------------------------
// Mocks - uncomment as needed
// ----------------------------------------------------------------------------

// Mock @nextcloud/l10n (if your component uses t() or n())
// vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// Mock icon components (adjust path and name as needed)
// vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))

// ----------------------------------------------------------------------------
// Tests
// ----------------------------------------------------------------------------

describe('{{pascalCase name}}', () => {
  // Example: Basic rendering
  // it('renders correctly', () => {
  //   const wrapper = mount({{pascalCase name}})
  //   expect(wrapper.exists()).toBe(true)
  // })

  // Example: Testing with props
  // it('renders with props', () => {
  //   const wrapper = mount({{pascalCase name}}, {
  //     props: { title: 'Hello' },
  //   })
  //   expect(wrapper.text()).toContain('Hello')
  // })

  // Example: Testing CSS classes
  // it('applies correct CSS class', () => {
  //   const wrapper = mount({{pascalCase name}}, {
  //     props: { variant: 'primary' },
  //   })
  //   expect(wrapper.classes()).toContain('is-primary')
  // })

  // Example: Testing emitted events
  // it('emits click event', async () => {
  //   const wrapper = mount({{pascalCase name}})
  //   await wrapper.trigger('click')
  //   expect(wrapper.emitted('click')).toBeTruthy()
  // })

  // Example: Testing computed properties
  // it('computes derived value', () => {
  //   const wrapper = mount({{pascalCase name}}, {
  //     props: { count: 5 },
  //   })
  //   const vm = wrapper.vm as InstanceType<typeof {{pascalCase name}}>
  //   expect(vm.doubleCount).toBe(10)
  // })

  // Example: Testing conditional rendering
  // it('shows content when condition is met', () => {
  //   const wrapper = mount({{pascalCase name}}, {
  //     props: { showDetails: true },
  //   })
  //   expect(wrapper.find('.details').exists()).toBe(true)
  // })

  // Example: Testing slots
  // it('renders slot content', () => {
  //   const wrapper = mount({{pascalCase name}}, {
  //     slots: { default: 'Slot content' },
  //   })
  //   expect(wrapper.text()).toContain('Slot content')
  // })

  it.todo('add your tests here')
})
