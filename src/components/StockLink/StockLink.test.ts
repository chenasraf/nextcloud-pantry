import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem, StockLinkRow } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@/api/lists', () => ({ listLinkTargets: vi.fn().mockResolvedValue([]) }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'disabled', 'wide', 'alignment'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-switch" :data-type="type"><input type="checkbox" :checked="type === \'radio\' ? modelValue === value : modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', type === \'radio\' ? value : !modelValue)" /><slot /></label>',
    props: ['modelValue', 'disabled', 'type', 'value', 'name'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: { name: 'NcTextField', template: '<input class="nc-text-field" />' },
}))
vi.mock('./StockLinkPickerDialog.vue', () => ({
  default: {
    name: 'StockLinkPickerDialog',
    template: '<div class="picker-stub" />',
    props: ['open', 'houseId', 'inventoryId'],
    emits: ['update:open', 'pick'],
  },
}))

import StockLinkCard from './StockLinkCard.vue'
import StockLinkPanel from './StockLinkPanel.vue'
import { rowOptionLabel, rowStateText, stockLinkState } from './stockLink'

const row: StockLinkRow = {
  itemId: 50,
  name: 'Eggs',
  quantity: '8',
  listId: 3,
  listName: 'Groceries',
  listColor: null,
  state: 'open',
}

const checked: StockLinkRow = { ...row, itemId: 51, quantity: null, state: 'checked' }

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 7,
    listId: 20,
    name: 'Eggs',
    description: null,
    categoryId: null,
    storeIds: [],
    labelIds: [],
    quantity: '4',
    done: false,
    doneAt: null,
    doneBy: null,
    rrule: null,
    repeatFromCompletion: false,
    deleteOnDone: false,
    nextDueAt: null,
    imageFileId: null,
    imageUploadedBy: null,
    addedBy: null,
    barcode: null,
    prices: [],
    customFields: [],
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    archivedAt: null,
    autoLink: true,
    stockLink: null,
    linkCandidates: [],
    ...overrides,
  }
}

describe('stock link wording', () => {
  it('names the four link states', () => {
    expect(stockLinkState(makeItem({ stockLink: row }))).toBe('linked')
    expect(stockLinkState(makeItem({ linkCandidates: [row, checked] }))).toBe('pick')
    expect(stockLinkState(makeItem())).toBe('auto')
    expect(stockLinkState(makeItem({ autoLink: false }))).toBe('off')
  })

  it('reads a row state as its quantity while open', () => {
    expect(rowStateText(row)).toBe('× 8')
    expect(rowStateText({ ...row, quantity: null })).toBe('open')
    expect(rowStateText(checked)).toBe('checked')
    expect(rowStateText({ ...row, state: 'trashed' })).toBe('in the trash')
  })

  it('marks archived picker options', () => {
    expect(rowOptionLabel(row)).toBe('Eggs — Groceries')
    expect(rowOptionLabel({ ...row, state: 'archived' })).toBe('Eggs — Groceries (archived)')
  })

  it('leaves names unescaped, since they are shown as text', () => {
    expect(rowOptionLabel({ ...row, listName: 'Fruit & Veg' })).toBe('Eggs — Fruit & Veg')
  })
})

describe('StockLinkPanel', () => {
  const mountPanel = (item: ChecklistItem, disabled = false) =>
    mount(StockLinkPanel, { props: { item, houseId: 1, disabled } })

  it('shows the linked row and unlinks it', async () => {
    const wrapper = mountPanel(makeItem({ stockLink: row }))

    expect(wrapper.find('[data-testid="stock-link-linked"]').text()).toBe('Eggs on Groceries · × 8')
    expect(wrapper.find('[data-testid="stock-link-linked"] strong').text()).toBe('Eggs')
    await wrapper.find('[data-testid="stock-link-unlink"]').trigger('click')

    expect(wrapper.emitted('unlink')).toHaveLength(1)
    expect(wrapper.find('[data-testid="stock-link-auto"]').exists()).toBe(false)
  })

  it('offers each candidate and the auto-link switch while unlinked', async () => {
    const wrapper = mountPanel(makeItem({ linkCandidates: [row, checked] }))

    const candidates = wrapper.findAll('[data-testid="stock-link-candidate"]')
    expect(candidates.map((c) => c.text())).toEqual([
      'Eggs, Groceries, × 8',
      'Eggs, Groceries, checked',
    ])
    await candidates[1].trigger('click')
    await wrapper.find('[data-testid="stock-link-auto"] input').trigger('change')

    expect(wrapper.emitted('link')).toEqual([[checked]])
    expect(wrapper.emitted('auto-link')).toEqual([[false]])
  })

  it('always offers linking by hand to members who can edit', () => {
    expect(mountPanel(makeItem({ stockLink: row })).text()).toContain('Link by hand')
    expect(mountPanel(makeItem({ stockLink: row }), true).text()).not.toContain('Link by hand')
  })
})

describe('StockLinkCard', () => {
  const mountCard = (item: ChecklistItem, restockChecklistName: string | null = 'Groceries') =>
    mount(StockLinkCard, { props: { item, houseId: 1, restockChecklistName } })

  it('shows the linked row with its state, and unlinks it', async () => {
    const wrapper = mountCard(makeItem({ stockLink: checked }))

    const text = wrapper.find('[data-testid="stock-link-card-linked"]')
    expect(text.text()).toBe('Linked to Eggs on Groceries · checked')
    expect(text.find('strong').text()).toBe('Eggs')
    expect(wrapper.text()).toContain(
      'When it runs low or starts expiring, this item is put back on that list.',
    )
    await wrapper.find('[data-testid="stock-link-card-unlink"]').trigger('click')

    expect(wrapper.emitted('unlink')).toHaveLength(1)
  })

  it('asks which candidate it is and links the one picked', async () => {
    const wrapper = mountCard(makeItem({ linkCandidates: [row, checked] }))

    const pick = wrapper.find('[data-testid="stock-link-card-pick"]')
    expect(pick.find('strong').text()).toBe('2 items match')
    expect(pick.text()).toContain('on the restock checklist. Which one is this?')
    const link = wrapper.find('[data-testid="stock-link-card-link"]')
    expect(link.attributes('disabled')).toBeDefined()

    await pick.findAll('[data-type="radio"] input')[0].trigger('change')
    await link.trigger('click')

    expect(wrapper.emitted('link')).toEqual([[row]])
  })

  it('says where an auto-linking item is found or added', () => {
    expect(mountCard(makeItem()).text()).toContain(
      'Finds or adds "Eggs" on Groceries when it is needed.',
    )
    expect(mountCard(makeItem({ autoLink: false })).text()).toContain(
      'This item is only marked here when it runs low.',
    )
  })

  it('switches auto-link', async () => {
    const wrapper = mountCard(makeItem({ autoLink: false }))

    await wrapper.find('[data-testid="stock-link-card-auto"] input').trigger('change')

    expect(wrapper.emitted('auto-link')).toEqual([[true]])
  })

  it('links by hand through the picker', async () => {
    const wrapper = mountCard(makeItem())

    await wrapper
      .findAll('.nc-button')
      .find((b) => b.text() === 'Link to an item\u00a0…')!
      .trigger('click')
    wrapper.findComponent({ name: 'StockLinkPickerDialog' }).vm.$emit('pick', checked)

    expect(wrapper.emitted('link')).toEqual([[checked]])
  })
})
