import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Checklist, ChecklistItem, PutAwayStorage } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Cupboard.vue', () => createIconMock('CupboardIcon'))
vi.mock('@icons/Close.vue', () => createIconMock('CloseIcon'))
vi.mock('@/composables/useChecklist', () => ({
  useChecklists: () => ({
    lists: ref([
      { id: 20, name: 'Congel' },
      { id: 21, name: 'Frigo' },
      { id: 22, name: 'Placard' },
    ] as Checklist[]),
    load: vi.fn(),
  }),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    props: ['variant', 'ariaLabel'],
    emits: ['click'],
    template:
      '<button class="nc-button" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot name="icon" /></button>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: defineComponent({
    props: ['modelValue', 'options'],
    emits: ['update:modelValue'],
    template:
      '<select class="nc-select" @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])"><option v-for="o in options" :key="o.listId">{{ o.label }}</option></select>',
  }),
}))

import ShoppingPutAway from './ShoppingPutAway.vue'

const nuggets = { id: 10, name: 'Nuggets', quantity: '10' } as unknown as ChecklistItem

function mountWith(storages: PutAwayStorage[]) {
  const wrapper = mount(ShoppingPutAway, {
    props: {
      houseId: 1,
      items: [nuggets],
      modelValue: { 10: storages },
      'onUpdate:modelValue': (value: Record<number, PutAwayStorage[]>) =>
        wrapper.setProps({ modelValue: value }),
    },
  })
  return wrapper
}

function draftOf(wrapper: ReturnType<typeof mountWith>): PutAwayStorage[] {
  return (wrapper.props('modelValue') as Record<number, PutAwayStorage[]>)[10]
}

/** The amount is committed on change, as when the field loses focus. */
async function typeAmount(wrapper: ReturnType<typeof mountWith>, listId: number, value: string) {
  const input = wrapper.find(`[data-list="${listId}"] input`)
  ;(input.element as HTMLInputElement).value = value
  await input.trigger('change')
}

describe('ShoppingPutAway', () => {
  it('shows each storage the item goes to, with its amount', () => {
    const wrapper = mountWith([{ listId: 20, amount: 10 }])

    const storage = wrapper.find('[data-list="20"]')
    expect(storage.text()).toContain('Congel')
    expect((storage.find('input').element as HTMLInputElement).value).toBe('10')
  })

  it('splits the purchase by adding a storage with what is left', async () => {
    const wrapper = mountWith([{ listId: 20, amount: 6 }])

    const select = wrapper.find('select.nc-select')
    // Congel is taken, so Frigo is the first option offered.
    expect(select.findAll('option').map((o) => o.text())).toEqual(['Frigo', 'Placard'])
    await select.trigger('change')

    expect(draftOf(wrapper)).toEqual([
      { listId: 20, amount: 6 },
      { listId: 21, amount: 4 },
    ])
  })

  it('changes an amount, and an emptied amount fills to the target', async () => {
    const wrapper = mountWith([{ listId: 20, amount: 10 }])

    await typeAmount(wrapper, 20, '7.5')
    expect(draftOf(wrapper)).toEqual([{ listId: 20, amount: 7.5 }])

    await typeAmount(wrapper, 20, '')
    expect(draftOf(wrapper)).toEqual([{ listId: 20, amount: null }])
  })

  it('drops a storage that is removed or set to 0', async () => {
    const wrapper = mountWith([
      { listId: 20, amount: 6 },
      { listId: 21, amount: 4 },
    ])

    await wrapper.find('[data-list="20"] button').trigger('click')
    expect(draftOf(wrapper)).toEqual([{ listId: 21, amount: 4 }])

    await typeAmount(wrapper, 21, '0')
    expect(draftOf(wrapper)).toEqual([])
    expect(wrapper.text()).toContain('Not put away')
  })
})
