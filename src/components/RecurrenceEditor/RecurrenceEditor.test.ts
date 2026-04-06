import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import RecurrenceEditor from './RecurrenceEditor.vue'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// Mock icon components
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))

// Stub Nextcloud Vue components
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog" v-if="open"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
    props: ['variant'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<select class="nc-select" />',
    props: ['modelValue', 'options', 'clearable', 'inputLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      "<label :class=\"type === 'radio' ? 'nc-radio' : 'nc-checkbox'\"><input :type=\"type === 'radio' ? 'radio' : 'checkbox'\" :checked=\"type === 'radio' ? modelValue === value : !!modelValue\" :value=\"value\" :name=\"name\" @change=\"$emit('update:modelValue', type === 'radio' ? value : $event.target.checked)\" /><slot /></label>",
    props: ['modelValue', 'type', 'value', 'name'],
    emits: ['update:modelValue'],
  },
}))

// Mock rrule to avoid complex dependency issues
vi.mock('rrule', () => {
  class MockWeekday {
    weekday: number
    constructor(weekday: number) {
      this.weekday = weekday
    }
  }

  class MockRRule {
    options: Record<string, unknown>
    origOptions: Record<string, unknown>

    static DAILY = 3
    static WEEKLY = 2
    static MONTHLY = 1
    static YEARLY = 0

    constructor(options: Record<string, unknown>) {
      this.options = { ...options }
      this.origOptions = { ...options }
    }

    toString() {
      return 'RRULE:FREQ=WEEKLY;INTERVAL=1'
    }

    toText() {
      return 'every week'
    }

    static fromString(_str: string) {
      return new MockRRule({ freq: MockRRule.WEEKLY, interval: 1 })
    }
  }

  return {
    RRule: MockRRule,
    Weekday: MockWeekday,
    Frequency: { DAILY: 3, WEEKLY: 2, MONTHLY: 1, YEARLY: 0 },
  }
})

function mountEditor(props: Record<string, unknown> = {}) {
  return mount(RecurrenceEditor, {
    props: {
      open: true,
      modelValue: null,
      ...props,
    },
  })
}

describe('RecurrenceEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('renders the dialog when open is true', () => {
      const wrapper = mountEditor({ open: true })
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
      expect(wrapper.find('.pantry-recurrence').exists()).toBe(true)
    })

    it('does not render dialog content when open is false', () => {
      const wrapper = mountEditor({ open: false })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('renders preset buttons', () => {
      const wrapper = mountEditor()
      const presets = wrapper.find('.pantry-recurrence__presets')
      expect(presets.exists()).toBe(true)

      const buttons = presets.findAll('.nc-button')
      expect(buttons.length).toBe(4)

      const labels = buttons.map((b) => b.text())
      expect(labels).toContain('Daily')
      expect(labels).toContain('Weekly')
      expect(labels).toContain('Every 2 weeks')
      expect(labels).toContain('Monthly')
    })

    it('renders interval input and frequency select', () => {
      const wrapper = mountEditor()
      const numberInput = wrapper.find('.pantry-recurrence__number')
      expect(numberInput.exists()).toBe(true)
      expect(numberInput.attributes('type')).toBe('number')
      expect(numberInput.attributes('min')).toBe('1')
      expect(numberInput.attributes('max')).toBe('999')

      const select = wrapper.find('.nc-select')
      expect(select.exists()).toBe(true)
    })

    it('renders end condition radio buttons', () => {
      const wrapper = mountEditor()
      const radios = wrapper.findAll('.nc-radio')
      expect(radios.length).toBe(3)
    })

    it('renders the summary section with repeat icon', () => {
      const wrapper = mountEditor()
      const summary = wrapper.find('.pantry-recurrence__summary')
      expect(summary.exists()).toBe(true)
      expect(summary.find('.mock-repeat-icon').exists()).toBe(true)
      expect(summary.find('strong').text()).toBe('Summary')
    })
  })

  describe('action buttons', () => {
    it('has save and cancel buttons', () => {
      const wrapper = mountEditor()
      const buttons = wrapper.findAll('.nc-button')
      const texts = buttons.map((b) => b.text())
      expect(texts).toContain('Cancel')
      expect(texts).toContain('Save')
    })

    it('shows clear button when modelValue is set', () => {
      const wrapper = mountEditor({ modelValue: 'FREQ=WEEKLY;INTERVAL=1' })
      const buttons = wrapper.findAll('.nc-button')
      const texts = buttons.map((b) => b.text())
      expect(texts).toContain('Remove recurrence')
    })

    it('does not show clear button when modelValue is null', () => {
      const wrapper = mountEditor({ modelValue: null })
      const buttons = wrapper.findAll('.nc-button')
      const texts = buttons.map((b) => b.text())
      expect(texts).not.toContain('Remove recurrence')
    })
  })

  describe('events', () => {
    it('emits update:open(false) when cancel is clicked', async () => {
      const wrapper = mountEditor()
      const buttons = wrapper.findAll('.nc-button')
      const cancelBtn = buttons.find((b) => b.text() === 'Cancel')!
      expect(cancelBtn).toBeTruthy()

      await cancelBtn.trigger('click')

      const emitted = wrapper.emitted('update:open')
      expect(emitted).toBeTruthy()
      expect(emitted!.some((args) => args[0] === false)).toBe(true)
    })

    it('emits update:modelValue(null) and update:open(false) when clear is clicked', async () => {
      const wrapper = mountEditor({ modelValue: 'FREQ=WEEKLY;INTERVAL=1' })
      const buttons = wrapper.findAll('.nc-button')
      const clearBtn = buttons.find((b) => b.text() === 'Remove recurrence')!
      expect(clearBtn).toBeTruthy()

      await clearBtn.trigger('click')

      const modelEmitted = wrapper.emitted('update:modelValue')
      expect(modelEmitted).toBeTruthy()
      expect(modelEmitted!.some((args) => args[0] === null)).toBe(true)

      const openEmitted = wrapper.emitted('update:open')
      expect(openEmitted).toBeTruthy()
      expect(openEmitted!.some((args) => args[0] === false)).toBe(true)
    })

    it('emits update:fromCompletion(false) when clear is clicked', async () => {
      const wrapper = mountEditor({
        modelValue: 'FREQ=WEEKLY;INTERVAL=1',
        fromCompletion: true,
      })
      const buttons = wrapper.findAll('.nc-button')
      const clearBtn = buttons.find((b) => b.text() === 'Remove recurrence')!

      await clearBtn.trigger('click')

      const emitted = wrapper.emitted('update:fromCompletion')
      expect(emitted).toBeTruthy()
      expect(emitted!.some((args) => args[0] === false)).toBe(true)
    })
  })

  describe('from-completion checkbox', () => {
    it('renders the from-completion checkbox', () => {
      const wrapper = mountEditor()
      const checkbox = wrapper.find('.nc-checkbox')
      expect(checkbox.exists()).toBe(true)
      expect(checkbox.text()).toContain('Count interval from when the item is ticked off')
    })

    it('reflects fromCompletion=false prop', () => {
      const wrapper = mountEditor({ fromCompletion: false })
      const input = wrapper.find('.nc-checkbox input[type="checkbox"]')
      expect((input.element as HTMLInputElement).checked).toBe(false)
    })

    it('reflects fromCompletion=true prop', () => {
      const wrapper = mountEditor({ fromCompletion: true })
      const input = wrapper.find('.nc-checkbox input[type="checkbox"]')
      expect((input.element as HTMLInputElement).checked).toBe(true)
    })

    it('shows fixed schedule hint when fromCompletion is false', () => {
      const wrapper = mountEditor({ fromCompletion: false })
      const hints = wrapper.findAll('.pantry-recurrence__hint')
      const hintTexts = hints.map((h) => h.text())
      expect(hintTexts.some((t) => t.includes('fixed'))).toBe(true)
    })

    it('shows completion-based hint when fromCompletion is true', () => {
      const wrapper = mountEditor({ fromCompletion: true })
      const hints = wrapper.findAll('.pantry-recurrence__hint')
      const hintTexts = hints.map((h) => h.text())
      expect(hintTexts.some((t) => t.includes('tick'))).toBe(true)
    })
  })

  describe('labels', () => {
    it('renders the presets label', () => {
      const wrapper = mountEditor()
      const labels = wrapper.findAll('.pantry-recurrence__label')
      const texts = labels.map((l) => l.text())
      expect(texts).toContain('Presets')
    })

    it('renders the every label', () => {
      const wrapper = mountEditor()
      const labels = wrapper.findAll('.pantry-recurrence__label')
      const texts = labels.map((l) => l.text())
      expect(texts).toContain('Every')
    })

    it('renders the ends label', () => {
      const wrapper = mountEditor()
      const labels = wrapper.findAll('.pantry-recurrence__label')
      const texts = labels.map((l) => l.text())
      expect(texts).toContain('Ends')
    })
  })
})
