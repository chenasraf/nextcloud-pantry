import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

import { createIconMock } from '@/test-utils'
import type { ShoppingSession } from '@/api/types'

import ResumeShoppingBanner from './ResumeShoppingBanner.vue'

// @nextcloud/l10n is deliberately NOT mocked here: the test-utils mock skips
// escaping and DOMPurify, which is exactly what these assertions are about.
const { sessionRef, storeName } = vi.hoisted(() => ({
  sessionRef: { current: null as ShoppingSession | null },
  storeName: { current: '' },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'list-detail' }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

vi.mock('@/composables/useCurrentHouse', () => ({
  useCurrentHouse: () => ({ houseId: computed(() => 1) }),
}))

vi.mock('@/composables/useStores', () => ({
  useStores: () => ({
    load: () => Promise.resolve(),
    findById: () => (storeName.current ? { name: storeName.current } : null),
  }),
}))

vi.mock('@/api/shopping', () => ({
  getCurrentSession: () => Promise.resolve(sessionRef.current),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: { name: 'NcButton', template: '<button><slot /></button>' },
}))
vi.mock('@icons/Cart.vue', () => createIconMock('CartIcon'))

function makeSession(createdAtMsAgo: number): ShoppingSession {
  const now = Math.floor(Date.now() / 1000)
  return {
    id: 1,
    houseId: 1,
    userId: 'admin',
    activeStoreId: null,
    lastSeenAt: now,
    closedAt: null,
    includeUnassigned: true,
    isPrivate: false,
    billedTotal: null,
    billedCurrency: null,
    live: true,
    createdAt: now - createdAtMsAgo,
    updatedAt: now,
    listIds: [1],
    stores: [],
  }
}

async function mountBanner() {
  const wrapper = mount(ResumeShoppingBanner)
  await flushPromises()
  return wrapper
}

describe('ResumeShoppingBanner', () => {
  beforeEach(() => {
    storeName.current = ''
  })

  it('shows a sub-minute duration as "< 1 min", not an HTML entity', async () => {
    sessionRef.current = makeSession(10)
    const text = (await mountBanner()).find('.resume-banner__text').text()

    expect(text).toContain('< 1 min')
    expect(text).not.toContain('&lt;')
    // DOMPurify treats a bare "<" as an unterminated tag and drops the rest of
    // the string, so the duration going missing is the other failure mode.
    expect(text).not.toBe('Shopping · ')
  })

  it('keeps the duration intact alongside a store name', async () => {
    sessionRef.current = { ...makeSession(10), activeStoreId: 4 }
    storeName.current = 'Corner Shop'
    const text = (await mountBanner()).find('.resume-banner__text').text()

    expect(text).toContain('Corner Shop')
    expect(text).toContain('< 1 min')
    expect(text).not.toContain('&lt;')
  })

  it('renders a plain duration unchanged', async () => {
    sessionRef.current = makeSession(15 * 60)
    const text = (await mountBanner()).find('.resume-banner__text').text()

    expect(text).toContain('15 min')
    expect(text).not.toContain('&')
  })

  it('escapes a store name that contains markup', async () => {
    sessionRef.current = { ...makeSession(15 * 60), activeStoreId: 4 }
    storeName.current = '<img src=x onerror=alert(1)>'
    const html = (await mountBanner()).find('.resume-banner__text').html()

    expect(html).not.toContain('<img')
  })
})
