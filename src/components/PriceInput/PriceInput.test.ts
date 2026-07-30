import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { PriceValue } from '@/utils/price'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@icons/Close.vue', () => createIconMock('CloseIcon'))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button class="nc-button" @click="$emit(\'click\')"><slot /></button>',
    props: ['variant', 'type'],
    emits: ['click'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder', 'type', 'inputmode', 'min', 'step'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<div class="nc-select" />',
    props: ['modelValue', 'options', 'clearable', 'searchable', 'label', 'inputLabel'],
    emits: ['update:modelValue'],
  },
}))

import PriceInput from './PriceInput.vue'

// A host that binds v-model so every emitted value flows back into the prop —
// exactly the echo that used to revert the Set/Range toggle.
const Host = defineComponent({
  setup() {
    const model = ref<PriceValue>({
      priceType: null,
      priceMin: null,
      priceMax: null,
      priceCurrency: 'USD',
    })
    return () =>
      h(PriceInput, {
        modelValue: model.value,
        'onUpdate:modelValue': (v: PriceValue) => (model.value = v),
      })
  },
})

function labelsOf(wrapper: ReturnType<typeof mount>): (string | undefined)[] {
  return wrapper.findAll('.nc-text-field').map((i) => i.attributes('aria-label'))
}

function fieldByLabel(wrapper: ReturnType<typeof mount>, label: string) {
  return wrapper.findAll('.nc-text-field').find((f) => f.attributes('aria-label') === label)!
}

function lastEmit(wrapper: ReturnType<typeof mount>): PriceValue {
  const child = wrapper.findComponent(PriceInput)
  const emissions = child.emitted('update:modelValue') as PriceValue[][]
  return emissions.at(-1)![0]
}

describe('PriceInput', () => {
  it('keeps Range selected after clicking it with no amount entered', async () => {
    const wrapper = mount(Host)
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Range')!
      .trigger('click')
    await wrapper.vm.$nextTick()

    // The Max field only renders in range mode — its presence proves the toggle
    // stuck despite the emitted value still having no price.
    expect(labelsOf(wrapper)).toContain('Max')
  })

  it('emits a range price once both amounts are entered', async () => {
    const wrapper = mount(Host)
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Range')!
      .trigger('click')
    await wrapper.vm.$nextTick()

    await fieldByLabel(wrapper, 'Min').setValue('1')
    await fieldByLabel(wrapper, 'Max').setValue('10')

    expect(lastEmit(wrapper)).toEqual({
      priceType: 'range',
      priceMin: 1,
      priceMax: 10,
      priceCurrency: 'USD',
    })
  })

  it('emits a set price for a single amount', async () => {
    const wrapper = mount(Host)
    await fieldByLabel(wrapper, 'Price').setValue('9.99')

    expect(lastEmit(wrapper)).toEqual({
      priceType: 'set',
      priceMin: 9.99,
      priceMax: null,
      priceCurrency: 'USD',
    })
  })
})
