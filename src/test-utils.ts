/**
 * Test utilities and mock factories for Nextcloud dependencies.
 *
 * Usage in test files:
 *
 *   import { vi } from 'vitest'
 *   import { nextcloudL10nMock, createIconMock } from '@/test-utils'
 *
 *   vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
 *   vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
 */

/**
 * Mock implementation for @nextcloud/l10n.
 *
 * - `t()` returns the text as-is, with variable substitution for {key} patterns
 * - `n()` returns singular or plural based on count
 *
 * @example
 * vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
 */
export const nextcloudL10nMock = {
  t: (_app: string, text: string, vars?: Record<string, unknown>) => {
    if (vars) {
      return Object.entries(vars).reduce(
        (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
        text,
      )
    }
    return text
  },
  n: (_app: string, singular: string, plural: string, count: number) => {
    return count === 1 ? singular : plural
  },
}

/**
 * Create a mock for an icon component from vue-material-design-icons.
 *
 * @param name - Component name (e.g., 'CheckIcon')
 * @param className - Optional CSS class (defaults to 'mock-{kebab-case-name}')
 * @returns Mock factory object for vi.mock()
 *
 * @example
 * vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
 * // Creates: <span class="mock-check-icon" />
 *
 * vi.mock('@icons/Alert.vue', () => createIconMock('AlertIcon', 'my-alert'))
 * // Creates: <span class="my-alert" />
 */
export function createIconMock(name: string, className?: string) {
  const cssClass = className ?? `mock-${name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()}`
  return {
    default: {
      name,
      template: `<span class="${cssClass}" data-icon="${name}" />`,
      props: ['size'],
    },
  }
}
