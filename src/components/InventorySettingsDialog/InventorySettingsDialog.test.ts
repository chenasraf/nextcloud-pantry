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

function mountDialog(list: Checklist) {
  return mount(InventorySettingsDialog, { props: { open: true, list } })
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

  it('closes without a request when nothing changed', async () => {
    const wrapper = mountDialog(inventory({ expiryLeadDays: undefined }))

    await wrapper.find('[data-testid="inventory-settings-save"]').trigger('click')

    expect(updateList).not.toHaveBeenCalled()
    expect(wrapper.emitted('update:open')).toEqual([[false]])
  })
})
