import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/ChevronDown.vue', () => createIconMock('ChevronDownIcon'))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :type="type" :disabled="disabled" :aria-label="ariaLabel"><slot name="icon" /><slot /></button>',
    props: ['variant', 'type', 'disabled', 'ariaLabel'],
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
vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'maxHeight', 'rows'],
    emits: ['update:modelValue'],
    methods: {
      getTextareaEl() {
        return this.$el?.tagName === 'TEXTAREA' ? this.$el : this.$el?.querySelector('textarea')
      },
      resize() {},
    },
  },
}))
vi.mock('@/components/RecurrenceEditor', () => ({
  default: {
    name: 'RecurrenceEditor',
    template: '<div class="mock-recurrence-editor" />',
    props: ['modelValue', 'open', 'fromCompletion'],
    emits: ['update:modelValue', 'update:open', 'update:fromCompletion'],
  },
}))
vi.mock('@/components/CategoryPicker', () => ({
  default: {
    name: 'CategoryPicker',
    template: '<div class="mock-category-picker" />',
    props: ['modelValue', 'houseId', 'placeholder'],
    emits: ['update:modelValue'],
  },
}))

import ChecklistAddForm from './ChecklistAddForm.vue'

function mountForm(props: { houseId?: number; adding?: boolean } = {}) {
  return mount(ChecklistAddForm, {
    props: {
      houseId: props.houseId ?? 1,
      adding: props.adding ?? false,
    },
  })
}

describe('ChecklistAddForm', () => {
  it('renders the form with all fields', () => {
    const wrapper = mountForm()
    const textFields = wrapper.findAll('.nc-text-field')
    expect(textFields).toHaveLength(2)
    expect(wrapper.find('.mock-category-picker').exists()).toBe(true)
    expect(wrapper.find('.mock-recurrence-editor').exists()).toBe(true)
    // Submit button exists
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')
    expect(submitBtn).toBeTruthy()
  })

  it('submit button is disabled when name is empty', () => {
    const wrapper = mountForm()
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')!
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })

  it('submit button is disabled when adding prop is true', async () => {
    const wrapper = mountForm({ adding: true })
    // Set a name so the only reason for disabled is the adding prop
    const nameInput = wrapper.findAll('.nc-text-field').at(0)!
    await nameInput.setValue('Milk')
    const submitBtn = wrapper.findAll('.nc-button').find((b) => b.attributes('type') === 'submit')!
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })

  it('emits add event with correct ItemInput on submit', async () => {
    const wrapper = mountForm()
    const textFields = wrapper.findAll('.nc-text-field')
    await textFields.at(0)!.setValue('Milk')
    await textFields.at(1)!.setValue('2 L')

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('add')).toBeTruthy()
    const payload = wrapper.emitted('add')![0][0]
    expect(payload).toEqual({
      name: 'Milk',
      description: null,
      quantity: '2 L',
      categoryId: null,
      rrule: null,
      repeatFromCompletion: false,
    })
  })

  it('resets all fields after submit', async () => {
    const wrapper = mountForm()
    const textFields = wrapper.findAll('.nc-text-field')
    await textFields.at(0)!.setValue('Milk')
    await textFields.at(1)!.setValue('2 L')

    await wrapper.find('form').trigger('submit')

    const textFieldsAfter = wrapper.findAll('.nc-text-field')
    expect((textFieldsAfter.at(0)!.element as HTMLInputElement).value).toBe('')
    expect((textFieldsAfter.at(1)!.element as HTMLInputElement).value).toBe('')
  })

  it('description field is hidden by default', () => {
    const wrapper = mountForm()
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)
  })

  it('clicking chevron toggles description visibility', async () => {
    const wrapper = mountForm()
    const chevronBtn = wrapper
      .findAll('.nc-button')
      .find((b) => b.find('.mock-chevron-down-icon').exists())!
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)

    await chevronBtn.trigger('click')
    expect(wrapper.find('.nc-text-area').exists()).toBe(true)

    await chevronBtn.trigger('click')
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)
  })

  it('description is included in the emitted add event when provided', async () => {
    const wrapper = mountForm()
    // Set name
    await wrapper.findAll('.nc-text-field').at(0)!.setValue('Milk')

    // Open description and fill it
    const chevronBtn = wrapper
      .findAll('.nc-button')
      .find((b) => b.find('.mock-chevron-down-icon').exists())!
    await chevronBtn.trigger('click')
    await wrapper.find('.nc-text-area').setValue('Whole milk preferred')

    await wrapper.find('form').trigger('submit')

    const payload = wrapper.emitted('add')![0][0]
    expect(payload.description).toBe('Whole milk preferred')
  })

  it('description area collapses after submit', async () => {
    const wrapper = mountForm()
    await wrapper.findAll('.nc-text-field').at(0)!.setValue('Milk')

    // Open description
    const chevronBtn = wrapper
      .findAll('.nc-button')
      .find((b) => b.find('.mock-chevron-down-icon').exists())!
    await chevronBtn.trigger('click')
    expect(wrapper.find('.nc-text-area').exists()).toBe(true)

    await wrapper.find('form').trigger('submit')
    expect(wrapper.find('.nc-text-area').exists()).toBe(false)
  })
})
