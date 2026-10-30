import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@/components/FieldCard', () => ({
  default: {
    name: 'FieldCard',
    props: ['label'],
    template: '<div class="field-card"><slot name="trailing" /><slot /></div>',
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
    template:
      '<input class="nc-text-field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  },
}))

import TemplateStockCard, { type TemplateStockDraft } from './TemplateStockCard.vue'

function mountCard(draft: Partial<TemplateStockDraft> = {}, quantity = '') {
  return mount(TemplateStockCard, {
    props: {
      modelValue: { stockMode: null, target: null, reorderPoint: null, ...draft },
      quantity,
    },
  })
}

const modeButton = (wrapper: ReturnType<typeof mountCard>, label: string) =>
  wrapper.findAll('.template-stock-card__mode').find((b) => b.text() === label)!

describe('TemplateStockCard', () => {
  it('offers a target and reorder point but no amount on hand or level', () => {
    const wrapper = mountCard({ stockMode: 'count' })
    expect(wrapper.findAll('.nc-text-field').map((f) => f.attributes('aria-label'))).toEqual([
      'Target',
      'Reorder point',
    ])
  })

  it('shows no fields for a level item, which restocks its quantity', () => {
    const wrapper = mountCard({ stockMode: 'level' }, '1 kg bag')
    expect(wrapper.findAll('.nc-text-field')).toHaveLength(0)
    expect(wrapper.text()).toContain('starts Full and restocks 1 kg bag')
  })

  it('switches mode without converting anything, and clears it on a second click', async () => {
    const wrapper = mountCard({ stockMode: 'count', target: '12' })
    await modeButton(wrapper, 'Level').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
      { stockMode: 'level', target: '12', reorderPoint: null },
    ])

    const picked = mountCard({ stockMode: 'count' })
    await modeButton(picked, 'Count').trigger('click')
    expect(picked.emitted('update:modelValue')?.[0]).toEqual([
      { stockMode: null, target: null, reorderPoint: null },
    ])
  })

  it("explains that no mode leaves it to the inventory's default", () => {
    expect(mountCard().text()).toContain("uses the inventory's default")
    expect(mountCard({ stockMode: 'count' }).text()).not.toContain("inventory's default")
  })
})
