import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Text.vue', () => createIconMock('TextIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Image.vue', () => createIconMock('ImageIcon'))
vi.mock('@icons/ImagePlus.vue', () => createIconMock('ImagePlusIcon'))
vi.mock('@icons/Upload.vue', () => createIconMock('UploadIcon'))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: { name: 'NcButton', template: '<button class="nc-button"><slot /></button>' },
}))
vi.mock('@/components/PantryChip', () => ({
  default: {
    name: 'PantryChip',
    template:
      '<button class="pantry-chip" :data-variant="variant" type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant'],
    emits: ['click'],
  },
}))
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: { name: 'AutoResizeTextarea', template: '<textarea class="nc-text-area" />' },
}))
vi.mock('@/components/RecurrenceEditor', () => ({
  RecurrenceForm: { name: 'RecurrenceForm', template: '<div class="mock-recurrence-form" />' },
}))
vi.mock('@/components/ItemCustomFieldsEditor', () => ({
  default: { name: 'ItemCustomFieldsEditor', template: '<div />' },
}))
vi.mock('@/components/ItemPricesEditor', () => ({
  default: { name: 'ItemPricesEditor', template: '<div />' },
}))
vi.mock('@/components/CategoryChipList', () => ({
  default: { name: 'CategoryChipList', template: '<div class="mock-category-chip-list" />' },
}))
vi.mock('@/components/StoreChipList', () => ({
  default: {
    name: 'StoreChipList',
    template:
      '<button class="mock-store-chip-list" type="button" @click="$emit(\'update:modelValue\', [7])" />',
    props: ['modelValue', 'houseId'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/LabelChipList', () => ({
  default: { name: 'LabelChipList', template: '<div />' },
}))
vi.mock('@/components/QuantityInput', () => ({
  default: { name: 'QuantityInput', template: '<input class="mock-quantity-input" />' },
}))
vi.mock('@/components/ItemTypeSelector', () => ({
  default: {
    name: 'ItemTypeSelector',
    template:
      '<button class="mock-one-time" type="button" @click="$emit(\'select-one-time\')">one-time</button>',
    emits: ['select-one-time'],
  },
}))
vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ template: '<span />' }),
}))
vi.mock('@/components/LabelPicker/labelIcons', () => ({
  labelIconComponent: () => ({ template: '<span />' }),
}))
vi.mock('@/components/StoreMultiPicker/storeIcons', () => ({
  storeIconComponent: () => ({ template: '<span />' }),
}))
const loaded = vi.hoisted(() => () => ({ items: { value: [] }, load: () => Promise.resolve() }))
vi.mock('@/composables/useCategories', () => ({ useCategories: loaded }))
vi.mock('@/composables/useStores', () => ({ useStores: loaded }))
vi.mock('@/composables/useLabels', () => ({ useLabels: loaded }))
vi.mock('@/composables/useCustomFields', () => ({ useCustomFields: loaded }))
vi.mock('@/utils/rrule', () => ({
  DEFAULT_RRULE: 'FREQ=WEEKLY;INTERVAL=1',
  formatRrule: (s: string) => `text(${s})`,
}))

import ItemFieldChips from './ItemFieldChips.vue'

function chipTexts(wrapper: ReturnType<typeof mount>): string[] {
  return wrapper.findAll('.pantry-chip').map((c) => c.text())
}

describe('ItemFieldChips', () => {
  it('shows every item field when composing', () => {
    const wrapper = mount(ItemFieldChips, { props: { houseId: 1, listId: 3 } })
    expect(chipTexts(wrapper)).toEqual([
      'Category',
      'Stores',
      'Labels',
      'Quantity',
      'Price',
      'Description',
      'Recurrence',
      'Image',
    ])
  })

  it('leaves out the fields a list cannot pre-fill in defaults mode', () => {
    const wrapper = mount(ItemFieldChips, { props: { houseId: 1, listId: 3, mode: 'defaults' } })
    expect(chipTexts(wrapper)).toEqual(['Category', 'Stores', 'Labels', 'Quantity', 'Recurrence'])
  })

  it('hides the image chip on request', () => {
    const wrapper = mount(ItemFieldChips, { props: { houseId: 1, listId: 3, hideImage: true } })
    expect(chipTexts(wrapper)).not.toContain('Image')
  })

  it('opens one section at a time and closes it on a second click', async () => {
    const wrapper = mount(ItemFieldChips, { props: { houseId: 1, listId: 3 } })
    const chip = (label: string) => wrapper.findAll('.pantry-chip').find((c) => c.text() === label)!

    await chip('Category').trigger('click')
    expect(wrapper.find('.mock-category-chip-list').exists()).toBe(true)

    await chip('Quantity').trigger('click')
    expect(wrapper.find('.mock-category-chip-list').exists()).toBe(false)
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(true)

    await chip('Quantity').trigger('click')
    expect(wrapper.find('.item-field-chips__section').exists()).toBe(false)
  })

  it('reports edits through its models', async () => {
    const wrapper = mount(ItemFieldChips, {
      props: { houseId: 1, listId: 3, openSection: 'stores' },
    })
    await wrapper.find('.mock-store-chip-list').trigger('click')
    expect(wrapper.emitted('update:storeIds')).toEqual([[[7]]])
  })

  it('marks the item type as picked when a type is chosen', async () => {
    const wrapper = mount(ItemFieldChips, { props: { houseId: 1, listId: 3, openSection: 'type' } })
    await wrapper.find('.mock-one-time').trigger('click')
    expect(wrapper.emitted('update:deleteOnDone')).toEqual([[true]])
    expect(wrapper.emitted('update:typePicked')).toEqual([[true]])
  })

  it('renders slotted chips after its own and a header above the open section', () => {
    const wrapper = mount(ItemFieldChips, {
      props: { houseId: 1, listId: 3, openSection: 'category' },
      slots: {
        'chips-after': '<span class="extra-chip" />',
        'section-header':
          '<template #section-header="{ section }"><p class="header">{{ section }}</p></template>',
      },
    })
    expect(wrapper.find('.item-field-chips__row .extra-chip').exists()).toBe(true)
    expect(wrapper.find('.header').text()).toBe('category')
  })
})
