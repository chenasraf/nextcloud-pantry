import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { Checklist } from '@/api/types'

const updateList = vi.fn()

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@/api/lists', () => ({ listApi: () => ({ updateList }) }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size', 'closeOnClickOutside'],
    emits: ['update:open'],
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
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template:
      '<select class="nc-select" @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])"><option v-for="o in options" :key="String(o.listId)" :selected="o === modelValue">{{ o.label }}</option></select>',
    props: ['modelValue', 'options'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-switch"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    props: ['modelValue', 'type'],
    emits: ['update:modelValue'],
  },
}))

import InventorySettingsDialog from './InventorySettingsDialog.vue'

function inventory(overrides: Partial<Checklist> = {}): Checklist {
  return {
    id: 7,
    houseId: 1,
    kind: 'inventory',
    name: 'Kitchen',
    canEdit: true,
    expiryLeadDays: 2,
    ...overrides,
  } as Checklist
}

const checklists = [
  { id: 10, houseId: 1, kind: 'checklist', name: 'Groceries' },
  { id: 11, houseId: 1, kind: 'checklist', name: 'Hardware' },
] as Checklist[]

function mountDialog(list: Checklist) {
  return mount(InventorySettingsDialog, { props: { open: true, list, checklists } })
}

async function pickRestock(wrapper: ReturnType<typeof mountDialog>, index: number) {
  const select = wrapper.find('[data-testid="inventory-settings-restock"]')
  ;(select.element as HTMLSelectElement).selectedIndex = index
  await select.trigger('change')
}

describe('InventorySettingsDialog', () => {
  beforeEach(() => updateList.mockReset())

  it('reads the expiry warning as one sentence around the number', () => {
    const wrapper = mountDialog(inventory())

    const parts = wrapper.findAll('.inventory-settings__sentence > span').map((p) => p.text())
    expect(parts).toEqual(['Warn', 'days before expiry'])
    expect(
      (wrapper.find('[data-testid="inventory-settings-lead-days"]').element as HTMLInputElement)
        .value,
    ).toBe('2')
  })

  it('saves only a changed expiry warning', async () => {
    const updated = inventory({ expiryLeadDays: 0 })
    updateList.mockResolvedValue(updated)
    const wrapper = mountDialog(inventory())

    await wrapper.find('[data-testid="inventory-settings-lead-days"]').setValue('0')
    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')
    await flushPromises()

    expect(updateList).toHaveBeenCalledWith(1, 7, { expiryLeadDays: 0 })
    expect(wrapper.emitted('saved')).toEqual([[updated]])
  })

  it('will not save a warning that is not a number of days', async () => {
    const wrapper = mountDialog(inventory())

    await wrapper.find('[data-testid="inventory-settings-lead-days"]').setValue('-1')

    expect(
      wrapper.find('[data-testid="inventory-settings-save"]').attributes('disabled'),
    ).toBeDefined()
  })

  it('offers the house checklists after None, opening on the current one', () => {
    const wrapper = mountDialog(inventory({ restockChecklistId: 11 }))

    const options = wrapper.findAll('[data-testid="inventory-settings-restock"] option')
    expect(options.map((o) => o.text())).toEqual(['None', 'Groceries', 'Hardware'])
    expect(wrapper.findComponent({ name: 'NcSelect' }).props('modelValue')).toEqual({
      listId: 11,
      label: 'Hardware',
    })
    expect(wrapper.text()).toContain(
      'Items that run low or start expiring are added to this checklist.',
    )
  })

  it('says what happens without a restock checklist', () => {
    const wrapper = mountDialog(inventory({ restockChecklistId: null }))

    expect(wrapper.text()).toContain('Without one, low and expiring items are only marked here.')
  })

  it('saves a picked restock checklist', async () => {
    updateList.mockResolvedValue(inventory({ restockChecklistId: 10 }))
    const wrapper = mountDialog(inventory())

    await pickRestock(wrapper, 1)
    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')
    await flushPromises()

    expect(updateList).toHaveBeenCalledWith(1, 7, { restockChecklistId: 10 })
  })

  it('opens on the daily stock summary switch as the inventory has it', () => {
    const wrapper = mountDialog(inventory({ stockSummary: false }))

    const toggle = wrapper.find('[data-testid="inventory-settings-stock-summary"] input')
    expect((toggle.element as HTMLInputElement).checked).toBe(false)
    expect(wrapper.text()).toContain('Daily stock summary')
  })

  it('saves the daily stock summary switch', async () => {
    updateList.mockResolvedValue(inventory({ stockSummary: false }))
    const wrapper = mountDialog(inventory({ stockSummary: true }))

    await wrapper.find('[data-testid="inventory-settings-stock-summary"] input').setValue(false)
    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')
    await flushPromises()

    expect(updateList).toHaveBeenCalledWith(1, 7, { stockSummary: false })
  })

  it('clears the restock checklist by picking None', async () => {
    updateList.mockResolvedValue(inventory())
    const wrapper = mountDialog(inventory({ restockChecklistId: 10 }))

    await pickRestock(wrapper, 0)
    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')
    await flushPromises()

    expect(updateList).toHaveBeenCalledWith(1, 7, { restockChecklistId: 0 })
  })

  it('closes without a request when nothing changed', async () => {
    const wrapper = mountDialog(inventory({ expiryLeadDays: undefined }))

    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')

    expect(updateList).not.toHaveBeenCalled()
    expect(wrapper.emitted('update:open')).toEqual([[false]])
  })
})
