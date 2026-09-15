import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

import { createIconMock } from '@/test-utils'
import type { ShoppingPresenceEntry, ShoppingSession } from '@/api/types'

import JoinShoppingBanner from './JoinShoppingBanner.vue'

const { presenceRef, currentRef, joinMock, closeMock, pushMock, joinResult } = vi.hoisted(() => ({
  presenceRef: { current: [] as ShoppingPresenceEntry[] },
  currentRef: { current: null as ShoppingSession | null },
  joinMock: vi.fn(),
  closeMock: vi.fn(),
  pushMock: vi.fn(),
  joinResult: { current: 'joined' as 'joined' | 'conflict' },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'list-detail', fullPath: '/lists/1' }),
  useRouter: () => ({ push: pushMock, replace: vi.fn() }),
}))

vi.mock('@/composables/useCurrentHouse', () => ({
  useCurrentHouse: () => ({ houseId: computed(() => 1) }),
}))

vi.mock('@/composables/useStores', () => ({
  useStores: () => ({
    load: () => Promise.resolve(),
    findById: (id: number) => (id === 7 ? { name: 'Grocer' } : null),
  }),
}))

vi.mock('@/composables/useHouseMembers', () => ({
  useHouseMembers: () => ({ displayNameByUid: computed(() => ({ dana: 'Dana' })) }),
}))

vi.mock('@/utils/currentUser', () => ({ getCurrentUserId: () => 'admin' }))

vi.mock('@/api/shopping', () => ({
  getPresence: () => Promise.resolve(presenceRef.current),
  getCurrentSession: () => Promise.resolve(currentRef.current),
  joinSession: (...args: unknown[]) => {
    joinMock(...args)
    return Promise.resolve({ status: joinResult.current, session: currentRef.current })
  },
  closeSession: (...args: unknown[]) => {
    closeMock(...args)
    return Promise.resolve(currentRef.current)
  },
}))

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: { name: 'NcButton', template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    props: ['open'],
    template: '<div v-if="open"><slot name="actions" /></div>',
  },
}))
vi.mock('@icons/Cart.vue', () => createIconMock('CartIcon'))

function entry(over: Partial<ShoppingPresenceEntry> = {}): ShoppingPresenceEntry {
  return {
    userId: 'dana',
    sessionId: 42,
    activeStoreId: null,
    lastSeenAt: 1000,
    memberIds: ['dana'],
    ...over,
  }
}

function session(over: Partial<ShoppingSession> = {}): ShoppingSession {
  return {
    id: 9,
    houseId: 1,
    userId: 'admin',
    activeStoreId: null,
    lastSeenAt: 1000,
    closedAt: null,
    includeUnassigned: true,
    isPrivate: false,
    billedTotal: null,
    billedCurrency: null,
    live: true,
    createdAt: 0,
    updatedAt: 0,
    listIds: [1],
    stores: [],
    memberIds: ['admin'],
    ...over,
  }
}

async function mountBanner() {
  const wrapper = mount(JoinShoppingBanner)
  await flushPromises()
  return wrapper
}

describe('JoinShoppingBanner', () => {
  beforeEach(() => {
    presenceRef.current = []
    currentRef.current = null
    joinResult.current = 'joined'
    joinMock.mockClear()
    closeMock.mockClear()
    pushMock.mockClear()
  })

  it('offers a housemate trip the shopper is not in', async () => {
    presenceRef.current = [entry()]
    const wrapper = await mountBanner()
    expect(wrapper.text()).toContain('Dana is shopping')
  })

  it('names the store when the housemate is at one', async () => {
    presenceRef.current = [entry({ activeStoreId: 7 })]
    const wrapper = await mountBanner()
    expect(wrapper.text()).toContain('Grocer')
  })

  it('stays hidden for a trip the shopper already joined', async () => {
    presenceRef.current = [entry({ memberIds: ['dana', 'admin'] })]
    const wrapper = await mountBanner()
    expect(wrapper.find('.join-banner').exists()).toBe(false)
  })

  it('stays hidden for the shopper own trip', async () => {
    presenceRef.current = [entry({ userId: 'admin', memberIds: ['admin'] })]
    const wrapper = await mountBanner()
    expect(wrapper.find('.join-banner').exists()).toBe(false)
  })

  it('joins straight away when the shopper has no trip of their own', async () => {
    presenceRef.current = [entry()]
    const wrapper = await mountBanner()
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(joinMock).toHaveBeenCalledWith(1, 42)
    expect(closeMock).not.toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalled()
  })

  it('confirms before ending the shopper own trip, then joins', async () => {
    presenceRef.current = [entry()]
    currentRef.current = session()
    const wrapper = await mountBanner()

    await wrapper.find('button').trigger('click')
    await flushPromises()
    // The join is gated behind the confirmation, not fired by the banner tap.
    expect(joinMock).not.toHaveBeenCalled()

    const confirm = wrapper.findAll('button').at(-1)
    await confirm?.trigger('click')
    await flushPromises()

    expect(closeMock).toHaveBeenCalledWith(1, 9)
    expect(joinMock).toHaveBeenCalledWith(1, 42)
  })

  it('does not confirm when the live trip is one the shopper merely joined', async () => {
    presenceRef.current = [entry()]
    currentRef.current = session({ userId: 'dana', memberIds: ['dana', 'admin'] })
    const wrapper = await mountBanner()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(closeMock).not.toHaveBeenCalled()
    expect(joinMock).toHaveBeenCalledWith(1, 42)
  })
})
