import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock } from '@/test-utils'
import type { ShoppingReview } from '@/api/types'

import ShoppingReviewDialog from './ShoppingReviewDialog.vue'

const { summaryRef, summaryMock, reviewMock, patchStoreMock, patchSessionMock } = vi.hoisted(
  () => ({
    summaryRef: { current: null as ShoppingReview | null },
    summaryMock: vi.fn(),
    reviewMock: vi.fn(),
    patchStoreMock: vi.fn(),
    patchSessionMock: vi.fn(),
  }),
)

vi.mock('@/api/shopping', () => ({
  getSessionSummary: (...args: unknown[]) => {
    summaryMock(...args)
    return Promise.resolve(summaryRef.current)
  },
  getReview: (...args: unknown[]) => {
    reviewMock(...args)
    return Promise.resolve(summaryRef.current)
  },
  patchStoreBilled: (...args: unknown[]) => {
    patchStoreMock(...args)
    return Promise.resolve({})
  },
  patchSessionBilled: (...args: unknown[]) => {
    patchSessionMock(...args)
    return Promise.resolve({})
  },
}))

vi.mock('@/composables/useStores', () => ({
  useStores: () => ({
    load: () => Promise.resolve(),
    findById: (id: number) => (id === 7 ? { id: 7, name: 'Grocer', color: '#fff' } : null),
  }),
}))

vi.mock('@/components/StoreMultiPicker/storeIcons', () => ({
  storeIconComponent: () => ({ name: 'StoreIcon', template: '<span />', props: ['size'] }),
}))

vi.mock('@/components/ShoppingReminders', () => ({
  ShoppingReminderBlock: { name: 'ShoppingReminderBlock', template: '<div />' },
  ShoppingRemindersDialog: { name: 'ShoppingRemindersDialog', template: '<div />' },
}))

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: { name: 'NcButton', template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    props: ['open', 'name'],
    template: '<div v-if="open"><slot /><slot name="actions" /></div>',
  },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: { name: 'NcLoadingIcon', template: '<span />' },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: { name: 'NcSelect', props: ['modelValue', 'options'], template: '<div />' },
}))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))

function review(): ShoppingReview {
  return {
    stores: [
      {
        storeId: 7,
        items: [],
        estimate: [{ currency: 'USD', min: 10, max: 10 }],
        noPriceCount: 0,
        billedTotal: 12,
        billedCurrency: 'USD',
      },
    ],
    grandTotal: [{ currency: 'USD', min: 12, max: 12 }],
    uncheckedCount: 0,
  }
}

async function mountDialog(canEdit: boolean) {
  const wrapper = mount(ShoppingReviewDialog, {
    props: { open: true, houseId: 1, sessionId: 42, mode: 'history' as const, canEdit },
  })
  await flushPromises()
  return wrapper
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  return wrapper.findAll('button').find((b) => b.text() === text)
}

describe('ShoppingReviewDialog history editing', () => {
  beforeEach(() => {
    summaryRef.current = review()
    summaryMock.mockClear()
    reviewMock.mockClear()
    patchStoreMock.mockClear()
    patchSessionMock.mockClear()
  })

  it('shows a past trip read-only until the owner opts into editing', async () => {
    const wrapper = await mountDialog(true)
    expect(wrapper.find('.shop-review__billed-amount').exists()).toBe(false)

    await buttonWithText(wrapper, 'Edit totals')?.trigger('click')
    expect(wrapper.find('.shop-review__billed-amount').exists()).toBe(true)
  })

  it('offers no edit affordance to a housemate viewing someone else’s trip', async () => {
    const wrapper = await mountDialog(false)
    expect(buttonWithText(wrapper, 'Edit totals')).toBeUndefined()
  })

  it('amends the store total and reloads the summary', async () => {
    const wrapper = await mountDialog(true)
    await buttonWithText(wrapper, 'Edit totals')?.trigger('click')

    // setValue dispatches the input's change event, which is what commits the amount.
    await wrapper.find('.shop-review__billed-amount').setValue('15.5')
    await flushPromises()

    expect(patchStoreMock).toHaveBeenCalledWith(1, 42, 7, {
      billedTotal: 15.5,
      billedCurrency: 'USD',
    })
    // The reload stays on the read-only summary route, never the live review.
    expect(summaryMock).toHaveBeenCalledTimes(2)
    expect(reviewMock).not.toHaveBeenCalled()
    expect(wrapper.emitted('updated')).toHaveLength(1)
  })

  it('leaves the edit state on Done', async () => {
    const wrapper = await mountDialog(true)
    await buttonWithText(wrapper, 'Edit totals')?.trigger('click')
    await buttonWithText(wrapper, 'Done')?.trigger('click')
    expect(wrapper.find('.shop-review__billed-amount').exists()).toBe(false)
  })
})
