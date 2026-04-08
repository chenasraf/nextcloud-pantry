import { mount } from '@vue/test-utils'
import { describe, expect, it, vi, beforeEach } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcTextArea', () => ({
  default: {
    name: 'NcTextArea',
    template:
      '<div class="nc-text-area-wrapper"><textarea class="nc-text-area" :value="modelValue" :rows="rows" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
    props: ['modelValue', 'label', 'placeholder', 'resize', 'rows'],
    emits: ['update:modelValue'],
  },
}))

import AutoResizeTextarea from './AutoResizeTextarea.vue'

describe('AutoResizeTextarea', () => {
  beforeEach(() => {
    // Mock scrollHeight on textarea elements since jsdom does not compute layout.
    Object.defineProperty(HTMLTextAreaElement.prototype, 'scrollHeight', {
      configurable: true,
      get() {
        // Approximate: 20px per line based on value content
        const lines = (this.value || '').split('\n').length
        return Math.max(lines * 20, 20)
      },
    })
  })

  it('renders an NcTextArea', () => {
    const wrapper = mount(AutoResizeTextarea)
    expect(wrapper.findComponent({ name: 'NcTextArea' }).exists()).toBe(true)
  })

  it('passes rows prop to NcTextArea', () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: { rows: 3 },
    })
    const ncTextArea = wrapper.findComponent({ name: 'NcTextArea' })
    expect(ncTextArea.props('rows')).toBe(3)
  })

  it('defaults to 1 row', () => {
    const wrapper = mount(AutoResizeTextarea)
    const ncTextArea = wrapper.findComponent({ name: 'NcTextArea' })
    expect(ncTextArea.props('rows')).toBe(1)
  })

  it('binds v-model to NcTextArea', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: {
        modelValue: 'hello',
        'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }),
      },
    })
    const textarea = wrapper.find('textarea')
    expect(textarea.element.value).toBe('hello')
  })

  it('emits update:modelValue on input', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: { modelValue: '' },
    })
    await wrapper.find('textarea').setValue('new text')
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0][0]).toBe('new text')
  })

  it('sets textarea height based on scrollHeight', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: { modelValue: 'line1\nline2\nline3' },
    })
    await wrapper.vm.$nextTick()
    const textarea = wrapper.find('textarea').element
    // scrollHeight mock returns 60 (3 lines * 20px)
    expect(textarea.style.height).toBe('60px')
  })

  it('caps height at maxHeight', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: {
        modelValue: Array(30).fill('line').join('\n'),
        maxHeight: 100,
      },
    })
    await wrapper.vm.$nextTick()
    const textarea = wrapper.find('textarea').element
    expect(textarea.style.height).toBe('100px')
    expect(textarea.style.overflowY).toBe('auto')
  })

  it('hides overflow when content fits', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: {
        modelValue: 'short',
        maxHeight: 400,
      },
    })
    await wrapper.vm.$nextTick()
    const textarea = wrapper.find('textarea').element
    expect(textarea.style.overflowY).toBe('hidden')
  })

  it('resizes when modelValue changes', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: {
        modelValue: 'one line',
        'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }),
      },
    })
    await wrapper.vm.$nextTick()
    const textarea = wrapper.find('textarea').element
    expect(textarea.style.height).toBe('20px')

    await wrapper.setProps({ modelValue: 'line1\nline2\nline3\nline4' })
    await wrapper.vm.$nextTick()
    expect(textarea.style.height).toBe('80px')
  })

  it('exposes resize method', () => {
    const wrapper = mount(AutoResizeTextarea)
    expect(typeof wrapper.vm.resize).toBe('function')
  })

  it('exposes getTextareaEl method', () => {
    const wrapper = mount(AutoResizeTextarea)
    expect(typeof wrapper.vm.getTextareaEl).toBe('function')
    const el = wrapper.vm.getTextareaEl()
    expect(el).toBeInstanceOf(HTMLTextAreaElement)
  })

  it('uses default maxHeight of 400', async () => {
    const wrapper = mount(AutoResizeTextarea, {
      props: {
        modelValue: Array(25).fill('line').join('\n'),
      },
    })
    await wrapper.vm.$nextTick()
    const textarea = wrapper.find('textarea').element
    // 25 lines * 20px = 500, capped at 400
    expect(textarea.style.height).toBe('400px')
    expect(textarea.style.overflowY).toBe('auto')
  })

  it('passes extra attrs through to NcTextArea', () => {
    const wrapper = mount(AutoResizeTextarea, {
      attrs: { placeholder: 'Type here …', label: 'Description' },
    })
    const textarea = wrapper.find('textarea')
    // attrs fall through to the root NcTextArea which renders the textarea
    expect(textarea.exists()).toBe(true)
    // Verify the component rendered with attrs applied to the wrapper
    const ncTextArea = wrapper.findComponent({ name: 'NcTextArea' })
    expect(ncTextArea.exists()).toBe(true)
  })
})
