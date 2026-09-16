import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem } from '@/api/types'

import ShoppingItemPicker from './ShoppingItemPicker.vue'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    props: ['variant', 'disabled'],
    template: '<button class="nc-button" :disabled="disabled"><slot /></button>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: defineComponent({
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template:
      '<label class="nc-check"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
  }),
}))
vi.mock('@icons/SelectAll.vue', () => createIconMock('SelectAllIcon'))
vi.mock('@icons/SelectInverse.vue', () => createIconMock('SelectInverseIcon'))
vi.mock('@icons/SelectOff.vue', () => createIconMock('SelectOffIcon'))
vi.mock('@icons/TagOutline.vue', () => createIconMock('TagOutlineIcon'))

vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'CatIcon', template: '<span class="cat-icon" />' }),
}))
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    items: ref([
      { id: 1, name: 'Produce', icon: 'carrot', color: '#0f0' },
      { id: 2, name: 'Dairy', icon: 'cow', color: '#00f' },
    ]),
    load: vi.fn(() => Promise.resolve()),
  }),
}))

function makeItem(id: number, name: string, categoryId: number | null): ChecklistItem {
  return { id, name, categoryId, quantity: null } as ChecklistItem
}

const ITEMS = [
  makeItem(1, 'Apples', 1),
  makeItem(2, 'Milk', 2),
  makeItem(3, 'Cheese', 2),
  makeItem(4, 'Batteries', null),
]

function render(excludedIds: number[] = []) {
  return mount(ShoppingItemPicker, {
    props: { open: true, houseId: 1, items: ITEMS, excludedIds },
  })
}

function itemBoxes(wrapper: ReturnType<typeof render>) {
  return wrapper.findAll('.shop-pick__items input')
}

function buttonByText(wrapper: ReturnType<typeof render>, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim() === text)!
}

describe('ShoppingItemPicker', () => {
  it('checks every item by default', () => {
    const wrapper = render()
    const boxes = itemBoxes(wrapper)
    expect(boxes).toHaveLength(4)
    expect(boxes.every((b) => (b.element as HTMLInputElement).checked)).toBe(true)
  })

  it('groups items by category with the uncategorized ones last', () => {
    const names = render()
      .findAll('.shop-pick__group-name')
      .map((el) => el.text())
    expect(names).toEqual(['Produce', 'Dairy', 'Uncategorized'])
  })

  it('starts from the excluded ids it was given', () => {
    const boxes = itemBoxes(render([2]))
    expect((boxes[0].element as HTMLInputElement).checked).toBe(true)
    expect((boxes[1].element as HTMLInputElement).checked).toBe(false)
  })

  it('emits the unchecked items as the exclusion on done', async () => {
    const wrapper = render()
    await itemBoxes(wrapper)[1].trigger('change')
    await buttonByText(wrapper, 'Done').trigger('click')

    expect(wrapper.emitted('update:excludedIds')?.[0]).toEqual([[2]])
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  })

  it('leaves the exclusion untouched when closed without confirming', async () => {
    const wrapper = render()
    await itemBoxes(wrapper)[1].trigger('change')
    await buttonByText(wrapper, 'Cancel').trigger('click')

    expect(wrapper.emitted('update:excludedIds')).toBeUndefined()
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  })

  it('unchecks everything with None and checks it back with All', async () => {
    const wrapper = render()
    await buttonByText(wrapper, 'None').trigger('click')
    expect(itemBoxes(wrapper).every((b) => (b.element as HTMLInputElement).checked)).toBe(false)

    await buttonByText(wrapper, 'All').trigger('click')
    expect(itemBoxes(wrapper).every((b) => (b.element as HTMLInputElement).checked)).toBe(true)
  })

  it('flips the selection with Invert', async () => {
    const wrapper = render([2])
    await buttonByText(wrapper, 'Invert').trigger('click')
    await buttonByText(wrapper, 'Done').trigger('click')

    expect(wrapper.emitted('update:excludedIds')?.[0]).toEqual([[1, 3, 4]])
  })

  it('toggles a whole category from its header', async () => {
    const wrapper = render()
    const dairyHeader = wrapper.findAll('.shop-pick__group-head input')[1]
    await dairyHeader.trigger('change')
    await buttonByText(wrapper, 'Done').trigger('click')

    expect(wrapper.emitted('update:excludedIds')?.[0]).toEqual([[2, 3]])
  })
})
