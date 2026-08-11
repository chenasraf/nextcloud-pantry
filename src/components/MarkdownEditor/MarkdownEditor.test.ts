import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    props: ['modelValue', 'type'],
    emits: ['update:modelValue'],
    template:
      '<label class="nc-switch"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
  },
}))

vi.mock('@/components/AutoResizeTextarea', () => ({
  AutoResizeTextarea: {
    name: 'AutoResizeTextarea',
    template:
      '<textarea class="md-source" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'placeholder', 'maxHeight', 'dir'],
    emits: ['update:modelValue'],
    methods: {
      getTextareaEl(this: { $el: HTMLTextAreaElement }) {
        return this.$el
      },
    },
  },
}))

import MarkdownEditor from './MarkdownEditor.vue'

interface FakeEditor {
  setContent: ReturnType<typeof vi.fn>
  setReadOnly: ReturnType<typeof vi.fn>
  insertAtCursor: ReturnType<typeof vi.fn>
  focus: ReturnType<typeof vi.fn>
  destroy: ReturnType<typeof vi.fn>
}

function fakeEditor(overrides: Partial<FakeEditor> = {}): FakeEditor {
  return {
    setContent: vi.fn(),
    setReadOnly: vi.fn(),
    insertAtCursor: vi.fn(),
    focus: vi.fn(),
    destroy: vi.fn(),
    ...overrides,
  }
}

interface CreateOpts {
  content?: string
  onUpdate?: (p: { markdown: string }) => void
}

/** Install a stub Text editor factory on window.OCA.Text. */
function installText(editor: FakeEditor) {
  const create = vi.fn(async (_opts: CreateOpts) => editor)
  ;(window as unknown as { OCA: { Text: unknown } }).OCA = {
    Text: { createMarkdownContentEditor: create },
  }
  return create
}

describe('MarkdownEditor', () => {
  afterEach(() => {
    delete (window as unknown as { OCA?: unknown }).OCA
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('falls back to a Markdown source textarea when Text is unavailable', async () => {
    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'hello' } })
    await flushPromises()

    expect(wrapper.find('textarea.md-source').exists()).toBe(true)
    // No rich/source toggle is offered when Text is not installed.
    expect(wrapper.find('.nc-switch').exists()).toBe(false)
  })

  it('emits update:modelValue when typing in source mode', async () => {
    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'a' } })
    await flushPromises()

    await wrapper.find('textarea.md-source').setValue('b')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['b'])
  })

  it('mounts the Text WYSIWYG editor with the current content and shows a toggle', async () => {
    const create = installText(fakeEditor())
    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'hi' } })
    await flushPromises()

    expect(create).toHaveBeenCalledOnce()
    expect(create.mock.calls[0][0]).toMatchObject({ content: 'hi' })
    expect(wrapper.find('.nc-switch').exists()).toBe(true)
    // Source textarea is not rendered while the WYSIWYG editor is active.
    expect(wrapper.find('textarea.md-source').exists()).toBe(false)
  })

  it('toggles from WYSIWYG to source, tearing the editor down', async () => {
    const editor = fakeEditor()
    installText(editor)
    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'hi' } })
    await flushPromises()

    // Switch the toggle off → source mode.
    await wrapper.find('.nc-switch input').setValue(false)
    await flushPromises()

    expect(editor.destroy).toHaveBeenCalledOnce()
    expect(wrapper.find('textarea.md-source').exists()).toBe(true)
  })

  it('propagates the editor onUpdate markdown to v-model', async () => {
    let captured: ((p: { markdown: string }) => void) | undefined
    const create = vi.fn(async (opts: { onUpdate: (p: { markdown: string }) => void }) => {
      captured = opts.onUpdate
      return fakeEditor()
    })
    ;(window as unknown as { OCA: { Text: unknown } }).OCA = {
      Text: { createMarkdownContentEditor: create },
    }

    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'hi' } })
    await flushPromises()

    captured?.({ markdown: 'edited in wysiwyg' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['edited in wysiwyg'])
  })

  it('pushes external model changes into the live editor', async () => {
    const editor = fakeEditor()
    installText(editor)
    const wrapper = mount(MarkdownEditor, { props: { modelValue: 'hi' } })
    await flushPromises()

    await wrapper.setProps({ modelValue: 'changed' })
    await flushPromises()

    expect(editor.setContent).toHaveBeenCalledWith('changed')
  })
})
