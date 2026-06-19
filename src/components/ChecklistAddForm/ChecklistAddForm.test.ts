import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ItemInput } from '@/api/lists'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/TagOutline.vue', () => createIconMock('TagOutlineIcon'))
vi.mock('@icons/ScaleBalance.vue', () => createIconMock('ScaleBalanceIcon'))
vi.mock('@icons/Text.vue', () => createIconMock('TextIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Image.vue', () => createIconMock('ImageIcon'))
vi.mock('@icons/ImagePlus.vue', () => createIconMock('ImagePlusIcon'))
vi.mock('@icons/Upload.vue', () => createIconMock('UploadIcon'))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :type="type" :disabled="disabled" :aria-label="ariaLabel"><slot name="icon" /><slot /></button>',
    props: ['variant', 'type', 'disabled', 'ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<div class="nc-select"></div>',
    props: ['modelValue', 'options', 'clearable', 'placeholder', 'inputLabel'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'autocomplete'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcChip', () => ({
  default: {
    name: 'NcChip',
    template:
      '<button class="nc-chip" :data-variant="variant" type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'noClose'],
    emits: ['click'],
  },
}))
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'autocomplete'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/RecurrenceEditor', () => ({
  default: {
    name: 'RecurrenceEditor',
    template: '<div class="mock-recurrence-editor" />',
    props: ['modelValue', 'open', 'fromCompletion'],
    emits: ['update:modelValue', 'update:open', 'update:fromCompletion'],
  },
  RecurrenceForm: {
    name: 'RecurrenceForm',
    template: '<div class="mock-recurrence-form" />',
    props: ['modelValue', 'fromCompletion'],
    emits: ['update:modelValue', 'update:fromCompletion'],
  },
}))
vi.mock('@/components/CategoryChipList', () => ({
  default: {
    name: 'CategoryChipList',
    template: '<div class="mock-category-chip-list" />',
    props: ['modelValue', 'houseId'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/QuantityInput', () => ({
  default: {
    name: 'QuantityInput',
    template:
      '<input class="mock-quantity-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@/components/ItemTypeSelector', () => ({
  default: {
    name: 'ItemTypeSelector',
    template:
      '<div class="mock-item-type-selector" :data-delete-on-done="deleteOnDone" :data-rrule="rrule || \'\'">' +
      '<button class="mock-staple" type="button" @click="$emit(\'select-staple\')">staple</button>' +
      '<button class="mock-one-time" type="button" @click="$emit(\'select-one-time\')">one-time</button>' +
      '<button class="mock-recurring" type="button" @click="$emit(\'select-recurring\')">recurring</button>' +
      '</div>',
    props: ['deleteOnDone', 'rrule'],
    emits: ['select-staple', 'select-one-time', 'select-recurring'],
  },
}))
vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  categoryIconComponent: () => ({ name: 'StubCategoryIcon', template: '<span />' }),
}))
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    items: { value: [] },
    load: vi.fn().mockResolvedValue(undefined),
  }),
}))
vi.mock('@/utils/rrule', () => ({
  formatRrule: (s: string) => `text(${s})`,
}))

import ChecklistAddForm from './ChecklistAddForm.vue'

function mountForm(
  props: { houseId?: number; adding?: boolean; deleteOnDoneDefault?: boolean } = {},
) {
  return mount(ChecklistAddForm, {
    props: {
      houseId: props.houseId ?? 1,
      adding: props.adding ?? false,
      deleteOnDoneDefault: props.deleteOnDoneDefault ?? false,
    },
  })
}

function chipForKey(wrapper: ReturnType<typeof mountForm>, label: string) {
  return wrapper.findAll('.nc-chip').find((c) => c.text().includes(label))!
}

describe('ChecklistAddForm', () => {
  it('renders only the name input + add button initially', () => {
    const wrapper = mountForm()
    // Only one text field is visible (the name); no expanded section yet.
    expect(wrapper.findAll('.nc-text-field')).toHaveLength(1)
    expect(wrapper.find('.mock-category-chip-list').exists()).toBe(false)
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(false)
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)
    expect(wrapper.find('.mock-item-type-selector').exists()).toBe(false)
  })

  it('renders one chip per field section', () => {
    const wrapper = mountForm()
    const chips = wrapper.findAll('.nc-chip')
    expect(chips).toHaveLength(5)
    expect(chips[0].text()).toContain('Category')
    expect(chips[1].text()).toContain('Quantity')
    expect(chips[2].text()).toContain('Description')
    expect(chips[3].text()).toContain('Item type')
    expect(chips[4].text()).toContain('Image')
  })

  it('item type chip shows the chosen type text only after explicit selection', async () => {
    const wrapper = mountForm()
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-staple').trigger('click')
    // After picking Staple, the chip's neutral label is replaced.
    expect(chipForKey(wrapper, 'Staple').exists()).toBe(true)
  })

  it('item type chip stays neutral when only the list default is one-time', () => {
    const wrapper = mountForm({ deleteOnDoneDefault: true })
    // No explicit pick yet — chip should read "Item type", not "One-time".
    expect(wrapper.text()).toContain('Item type')
    expect(wrapper.findAll('.nc-chip').some((c) => c.text() === 'One-time')).toBe(false)
  })

  it('submit button is disabled when name is empty', () => {
    const wrapper = mountForm()
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')!
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })

  it('submit button is disabled when adding prop is true', async () => {
    const wrapper = mountForm({ adding: true })
    await wrapper.find('.nc-text-field').setValue('Milk')
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')!
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })

  it('emits add event with correct ItemInput and null pendingImage on submit', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-text-field').setValue('Milk')

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('add')).toBeTruthy()
    const [input, pendingImage] = wrapper.emitted('add')![0] as [ItemInput, File | null]
    expect(input).toEqual({
      name: 'Milk',
      description: null,
      quantity: null,
      categoryId: null,
      rrule: null,
      repeatFromCompletion: false,
      deleteOnDone: false,
    })
    expect(pendingImage).toBeNull()
  })

  it('toggles section open and closed when chip is clicked', async () => {
    const wrapper = mountForm()
    const qtyChip = chipForKey(wrapper, 'Quantity')
    await qtyChip.trigger('click')
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(true)

    await qtyChip.trigger('click')
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(false)
  })

  it('only one section is open at a time', async () => {
    const wrapper = mountForm()
    await chipForKey(wrapper, 'Quantity').trigger('click')
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(true)

    await chipForKey(wrapper, 'Description').trigger('click')
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(false)
    expect(wrapper.find('.nc-text-area').exists()).toBe(true)
  })

  it('typed quantity is included in the emitted add input', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-text-field').setValue('Milk')
    await chipForKey(wrapper, 'Quantity').trigger('click')
    await wrapper.find('.mock-quantity-input').setValue('2 L')

    await wrapper.find('form').trigger('submit')

    const [input] = wrapper.emitted('add')![0] as [ItemInput, File | null]
    expect(input.quantity).toBe('2 L')
  })

  it('typed description is included in the emitted add input', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-text-field').setValue('Milk')
    await chipForKey(wrapper, 'Description').trigger('click')
    await wrapper.find('.nc-text-area').setValue('Whole milk preferred')

    await wrapper.find('form').trigger('submit')

    const [input] = wrapper.emitted('add')![0] as [ItemInput, File | null]
    expect(input.description).toBe('Whole milk preferred')
  })

  it('selecting One-time emits deleteOnDone=true and update:deleteOnDoneDefault', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-text-field').setValue('Milk')
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-one-time').trigger('click')

    expect(wrapper.emitted('update:deleteOnDoneDefault')![0]).toEqual([true])

    await wrapper.find('form').trigger('submit')
    const [input] = wrapper.emitted('add')![0] as [ItemInput, File | null]
    expect(input.deleteOnDone).toBe(true)
  })

  it('selecting Recurring opens the inline RecurrenceForm and seeds an rrule', async () => {
    const wrapper = mountForm()
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-recurring').trigger('click')
    await flushPromises()

    expect(wrapper.find('.mock-recurrence-form').exists()).toBe(true)
  })

  it('initializes deleteOnDone from deleteOnDoneDefault prop and emits it on submit', async () => {
    const wrapper = mountForm({ deleteOnDoneDefault: true })
    await wrapper.find('.nc-text-field').setValue('Milk')

    await wrapper.find('form').trigger('submit')
    const [input] = wrapper.emitted('add')![0] as [ItemInput, File | null]
    expect(input.deleteOnDone).toBe(true)
  })

  it('does not emit update:deleteOnDoneDefault when choice already matches the default', async () => {
    const wrapper = mountForm({ deleteOnDoneDefault: true })
    // Pick One-time again — it already matches the list default.
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-one-time').trigger('click')

    expect(wrapper.emitted('update:deleteOnDoneDefault')).toBeFalsy()
  })

  it('does not change deleteOnDoneDefault when the user picks Recurring', async () => {
    const wrapper = mountForm()
    await chipForKey(wrapper, 'Item type').trigger('click')
    await wrapper.find('.mock-recurring').trigger('click')

    expect(wrapper.emitted('update:deleteOnDoneDefault')).toBeFalsy()
  })

  it('toggling Multiple swaps the name input for a textarea and shows a hint', async () => {
    const wrapper = mountForm()
    expect(wrapper.find('.nc-text-field').exists()).toBe(true)
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)

    await wrapper.find('.nc-checkbox input').setValue(true)

    expect(wrapper.find('.nc-text-field').exists()).toBe(false)
    expect(wrapper.find('.nc-text-area').exists()).toBe(true)
    expect(wrapper.text()).toContain('Separate items by new lines')
  })

  it('emits one add event per non-empty line when Multiple is on', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-checkbox input').setValue(true)
    await wrapper.find('.nc-text-area').setValue('Milk\nEggs\n\n   \nBread\n')

    await wrapper.find('form').trigger('submit')

    const events = wrapper.emitted('add')!
    expect(events).toHaveLength(3)
    const names = events.map((e) => (e[0] as ItemInput).name)
    expect(names).toEqual(['Milk', 'Eggs', 'Bread'])
  })

  it('submit is disabled in Multiple mode when no non-empty lines are present', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-checkbox input').setValue(true)
    await wrapper.find('.nc-text-area').setValue('   \n\n  ')
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')!
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })

  it('resets the open section and inputs after submit', async () => {
    const wrapper = mountForm()
    await wrapper.find('.nc-text-field').setValue('Milk')
    await chipForKey(wrapper, 'Quantity').trigger('click')
    await wrapper.find('.mock-quantity-input').setValue('2 L')

    await wrapper.find('form').trigger('submit')

    // Section closes
    expect(wrapper.find('.mock-quantity-input').exists()).toBe(false)
    // Name field is cleared
    expect((wrapper.find('.nc-text-field').element as HTMLInputElement).value).toBe('')
  })
})
