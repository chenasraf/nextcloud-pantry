import { mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import RecurrenceForm from './RecurrenceForm.vue'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))

// @nextcloud/vue components eagerly import .css assets that Node's ESM loader rejects.
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    name: 'NcButton',
    props: ['variant'],
    template: '<button class="nc-button"><slot /></button>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: defineComponent({
    name: 'NcSelect',
    props: ['modelValue', 'options', 'clearable', 'inputLabel', 'multiple', 'placeholder'],
    emits: ['update:modelValue'],
    template: '<div class="nc-select" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcDateTimePicker', () => ({
  default: defineComponent({
    name: 'NcDateTimePicker',
    props: ['modelValue', 'type', 'clearable', 'format', 'placeholder'],
    emits: ['update:modelValue'],
    template: '<div class="nc-date-time-picker" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: defineComponent({
    name: 'NcCheckboxRadioSwitch',
    props: ['modelValue', 'type', 'value', 'name'],
    emits: ['update:modelValue'],
    template:
      '<label class="nc-radio" :data-value="value" @click="$emit(\'update:modelValue\', value)"><slot /></label>',
  }),
}))

type Form = VueWrapper<InstanceType<typeof RecurrenceForm>>

async function mountForm(modelValue: string | null = null): Promise<Form> {
  const wrapper = mount(RecurrenceForm, { props: { modelValue } })
  await wrapper.vm.$nextTick()
  await wrapper.vm.$nextTick()
  return wrapper as Form
}

/** Last rrule the form pushed to its parent. */
function emittedRrule(wrapper: Form): string | null {
  const events = wrapper.emitted('update:modelValue') as [string | null][] | undefined
  if (!events?.length) return null
  return events[events.length - 1]![0]
}

type Option = { label: string; value: unknown }

function querySelect(wrapper: Form, modifier: string) {
  return wrapper
    .findAllComponents({ name: 'NcSelect' })
    .find((select) => select.classes().includes(`pantry-recurrence__select--${modifier}`))
}

function findSelect(wrapper: Form, modifier: string) {
  const select = querySelect(wrapper, modifier)
  expect(select, `no "${modifier}" select rendered`).toBeTruthy()
  return select!
}

function optionsOf(wrapper: Form, modifier: string): Option[] {
  return findSelect(wrapper, modifier).props('options') as Option[]
}

/** Drive a single-choice NcSelect by the label of the option to pick. */
async function pickOption(wrapper: Form, modifier: string, label: string): Promise<void> {
  const option = optionsOf(wrapper, modifier).find((o) => o.label === label)
  expect(option, `no option labelled "${label}"`).toBeTruthy()
  findSelect(wrapper, modifier).vm.$emit('update:modelValue', option)
  await wrapper.vm.$nextTick()
}

/** Drive the multi-choice month-day NcSelect by the labels of the days to pick. */
async function pickMonthDays(wrapper: Form, labels: string[]): Promise<void> {
  const options = optionsOf(wrapper, 'monthday')
  const picked = labels.map((label) => {
    const option = options.find((o) => o.label === label)
    expect(option, `no day option labelled "${label}"`).toBeTruthy()
    return option
  })
  findSelect(wrapper, 'monthday').vm.$emit('update:modelValue', picked)
  await wrapper.vm.$nextTick()
}

async function setFrequency(wrapper: Form, label: string): Promise<void> {
  await pickOption(wrapper, 'frequency', label)
}

async function selectRadio(wrapper: Form, value: string): Promise<void> {
  const radio = wrapper.findAll('.nc-radio').find((el) => el.attributes('data-value') === value)
  expect(radio, `no radio for "${value}"`).toBeTruthy()
  await radio!.trigger('click')
  await wrapper.vm.$nextTick()
}

describe('RecurrenceForm monthly rules', () => {
  it('keeps repeating on the same day when no day of the month is picked', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'months')
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1')
  })

  it('builds a day-of-the-month rule from the day dropdown', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'months')
    await pickMonthDays(wrapper, ['1'])
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=1')
  })

  it('sorts several picked days of the month', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'months')
    await pickMonthDays(wrapper, ['17', '3'])
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=3,17')
  })

  it('builds an ordinal weekday rule', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'months')
    await selectRadio(wrapper, 'weekday')
    await pickOption(wrapper, 'ordinal', 'Second')
    await pickOption(wrapper, 'ordinal-weekday', 'Monday')
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1;BYDAY=+2MO')
  })

  it('builds a last-weekday rule', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'months')
    await selectRadio(wrapper, 'weekday')
    await pickOption(wrapper, 'ordinal', 'Last')
    await pickOption(wrapper, 'ordinal-weekday', 'Friday')
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1;BYDAY=-1FR')
  })

  it('drops the day-of-the-month selection when switching to an ordinal weekday', async () => {
    const wrapper = await mountForm('FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=5')
    await selectRadio(wrapper, 'weekday')
    expect(emittedRrule(wrapper)).toBe('FREQ=MONTHLY;INTERVAL=1;BYDAY=+1MO')
  })

  it('loads an ordinal weekday rule back into the dropdowns', async () => {
    const wrapper = await mountForm('FREQ=MONTHLY;INTERVAL=3;BYDAY=+3WE')
    expect((findSelect(wrapper, 'ordinal').props('modelValue') as Option).label).toBe('Third')
    expect((findSelect(wrapper, 'ordinal-weekday').props('modelValue') as Option).label).toBe(
      'Wednesday',
    )
    expect(querySelect(wrapper, 'monthday')).toBeUndefined()
  })

  it('loads a day-of-the-month rule back into the day dropdown', async () => {
    const wrapper = await mountForm('FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=3,17')
    const picked = findSelect(wrapper, 'monthday').props('modelValue') as Option[]
    expect(picked.map((o) => o.label)).toEqual(['3', '17'])
  })
})

describe('RecurrenceForm yearly rules', () => {
  it('repeats on the anchor date when no date is picked', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'years')
    expect(emittedRrule(wrapper)).toBe('FREQ=YEARLY;INTERVAL=1')
  })

  it('builds a month-and-day rule from the picked date', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'years')
    const picker = wrapper.findComponent({ name: 'NcDateTimePicker' })
    picker.vm.$emit('update:modelValue', new Date(2024, 2, 15))
    await wrapper.vm.$nextTick()
    expect(emittedRrule(wrapper)).toBe('FREQ=YEARLY;INTERVAL=1;BYMONTH=3;BYMONTHDAY=15')
  })

  it('loads a month-and-day rule back into the picker without a meaningful year', async () => {
    const wrapper = await mountForm('FREQ=YEARLY;INTERVAL=1;BYMONTH=11;BYMONTHDAY=29')
    const picked = wrapper.findComponent({ name: 'NcDateTimePicker' }).props('modelValue') as Date
    expect(picked.getMonth()).toBe(10)
    expect(picked.getDate()).toBe(29)
  })

  it('offers 29 February, which only a leap year can hold', async () => {
    const wrapper = await mountForm('FREQ=YEARLY;INTERVAL=1;BYMONTH=2;BYMONTHDAY=29')
    const picked = wrapper.findComponent({ name: 'NcDateTimePicker' }).props('modelValue') as Date
    expect(picked.getMonth()).toBe(1)
    expect(picked.getDate()).toBe(29)
  })

  it('formats the picked date without a year', async () => {
    const wrapper = await mountForm()
    await setFrequency(wrapper, 'years')
    const format = wrapper.findComponent({ name: 'NcDateTimePicker' }).props('format') as (
      d: Date,
    ) => string
    expect(format(new Date(2024, 2, 15))).not.toMatch(/2024/)
  })
})
