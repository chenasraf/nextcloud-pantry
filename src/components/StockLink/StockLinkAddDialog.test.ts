import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { nextcloudL10nMock } from '@/test-utils'
import type { Checklist } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

const lists = ref([
  { id: 2, name: 'Hardware' },
  { id: 3, name: 'Groceries' },
] as unknown as Checklist[])
vi.mock('@/composables/useChecklist', () => ({
  useChecklists: () => ({ lists, loading: ref(false), load: vi.fn().mockResolvedValue([]) }),
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    props: ['name', 'open', 'size', 'closeOnClickOutside'],
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    props: ['variant', 'disabled'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    props: ['modelValue', 'options', 'loading', 'clearable', 'inputLabel', 'label'],
    emits: ['update:modelValue'],
    template: '<div class="nc-select" />',
  },
}))

import StockLinkAddDialog from './StockLinkAddDialog.vue'

describe('StockLinkAddDialog', () => {
  const mountDialog = (restockChecklistId: number | null) =>
    mount(StockLinkAddDialog, {
      props: { open: true, houseId: 1, itemName: 'Eggs', restockChecklistId },
    })

  it('offers the restock checklist first, picked to start with', async () => {
    const wrapper = mountDialog(3)
    await flushPromises()

    const select = wrapper.findComponent({ name: 'NcSelect' })
    expect(select.props('options').map((o: { label: string }) => o.label)).toEqual([
      'Groceries',
      'Hardware',
    ])
    expect(select.props('modelValue')).toMatchObject({ listId: 3 })
    expect(wrapper.text()).toContain('Adds "Eggs" to the list you pick and links it')
  })

  it('hands back the list picked', async () => {
    const wrapper = mountDialog(null)
    await flushPromises()

    const select = wrapper.findComponent({ name: 'NcSelect' })
    select.vm.$emit('update:modelValue', { listId: 2, label: 'Hardware' })
    await wrapper.vm.$nextTick()
    await wrapper.find('[data-testid="stock-link-add-confirm"]').trigger('click')

    expect(wrapper.emitted('pick')).toEqual([[2]])
    expect(wrapper.emitted('update:open')).toEqual([[false]])
  })
})
