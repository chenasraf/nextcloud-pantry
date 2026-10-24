import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { StockDraft } from '@/utils/stock'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/QuantityInput', () => ({
  default: {
    name: 'QuantityInput',
    template:
      '<input class="mock-on-hand" :data-error="error" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'error'],
    emits: ['update:modelValue'],
  },
}))

import StockCard from './StockCard.vue'

function mountCard(draft: StockDraft) {
  const state = { draft, blocked: false }
  const wrapper = mount(StockCard, {
    props: {
      modelValue: draft,
      'onUpdate:modelValue': (v: StockDraft) => {
        state.draft = v
        void wrapper.setProps({ modelValue: v })
      },
      blocked: false,
      'onUpdate:blocked': (v: boolean) => {
        state.blocked = v
        void wrapper.setProps({ blocked: v })
      },
    },
  })
  return { wrapper, state }
}

const modeButton = (w: ReturnType<typeof mount>, label: string) =>
  w.findAll('.stock-card__mode').find((b) => b.text() === label)!

describe('StockCard', () => {
  it('shows the live status of a count item', async () => {
    const { wrapper } = mountCard({
      stockMode: 'count',
      stockLevel: null,
      quantity: '12',
      target: '12 eggs',
      reorderPoint: null,
    })
    expect(wrapper.find('.stock-card__status').text()).toBe('In stock.')

    await wrapper.find('.mock-on-hand').setValue('4')

    expect(wrapper.find('.stock-card__status').text()).toBe(
      'Needs restock: the shopping list asks for 8 eggs.',
    )
    expect(wrapper.find('.stock-card__status--warning').exists()).toBe(true)
  })

  it('converts to a level and says so before saving', async () => {
    const { wrapper, state } = mountCard({
      stockMode: 'count',
      stockLevel: null,
      quantity: '4',
      target: '12 eggs',
      reorderPoint: null,
    })

    await modeButton(wrapper, 'Level').trigger('click')

    expect(state.draft).toMatchObject({ stockMode: 'level', stockLevel: 'low', quantity: '8 eggs' })
    expect(wrapper.find('.stock-card__note').text()).toBe(
      'Converted: level Low, restock amount "8 eggs".',
    )
    expect(wrapper.find('.stock-level-control').exists()).toBe(true)
  })

  it('asks for on hand when Low converts to a count without a reorder point', async () => {
    const { wrapper, state } = mountCard({
      stockMode: 'level',
      stockLevel: 'low',
      quantity: '12 eggs',
      target: null,
      reorderPoint: null,
    })

    await modeButton(wrapper, 'Count').trigger('click')

    expect(state.draft).toMatchObject({ stockMode: 'count', quantity: null, target: '12 eggs' })
    expect(state.blocked).toBe(true)
    expect(wrapper.find('.mock-on-hand').attributes('data-error')).toBe('true')
    expect(wrapper.find('.stock-card__note').text()).toBe(
      'Converted: target "12 eggs". Enter the amount on hand.',
    )

    await wrapper.find('.mock-on-hand').setValue('3')

    expect(state.blocked).toBe(false)
  })

  it('says why an item is not counted', async () => {
    const { wrapper } = mountCard({
      stockMode: 'count',
      stockLevel: null,
      quantity: 'some',
      target: '1 kg',
      reorderPoint: null,
    })
    expect(wrapper.find('.stock-card__status').text()).toBe(
      'Not counted: on hand has no number, so it never runs low.',
    )
  })
})
