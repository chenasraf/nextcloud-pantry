import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Category, ChecklistItem } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({
  showError: vi.fn(),
  showSuccess: vi.fn(),
}))
vi.mock('@icons/ContentCopy.vue', () => createIconMock('ContentCopyIcon'))
vi.mock('@icons/Download.vue', () => createIconMock('DownloadIcon'))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size', 'closeOnClickOutside'],
    emits: ['update:open'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant'],
    emits: ['click'],
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

import MarkdownExportDialog from './MarkdownExportDialog.vue'

function item(partial: Partial<ChecklistItem>): ChecklistItem {
  return {
    id: 1,
    listId: 1,
    name: 'Item',
    description: null,
    categoryId: null,
    quantity: null,
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
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    ...partial,
  }
}

const categoryFor = (_id: number | null): Category | null => null

function mountDialog(items: ChecklistItem[]) {
  return mount(MarkdownExportDialog, {
    props: { open: true, listName: 'Groceries', items, categoryFor },
  })
}

function textarea(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.find('.md-export').element as HTMLTextAreaElement
}

describe('MarkdownExportDialog', () => {
  const items = [
    item({ id: 1, name: 'Milk', done: false }),
    item({ id: 2, name: 'Bread', done: true }),
  ]

  it('excludes completed items by default', () => {
    const wrapper = mountDialog(items)
    const value = textarea(wrapper).value
    expect(value).toContain('Milk')
    expect(value).not.toContain('Bread')
  })

  it('includes completed items when the checkbox is ticked', async () => {
    const wrapper = mountDialog(items)
    await wrapper.find('.nc-checkbox input').trigger('change')
    const value = textarea(wrapper).value
    expect(value).toContain('Milk')
    expect(value).toContain('Bread')
  })

  it('the checkbox defaults to unchecked', () => {
    const wrapper = mountDialog(items)
    expect((wrapper.find('.nc-checkbox input').element as HTMLInputElement).checked).toBe(false)
  })

  it('regenerates content when toggled back off', async () => {
    const wrapper = mountDialog(items)
    const box = wrapper.find('.nc-checkbox input')
    await box.trigger('change') // on
    expect(textarea(wrapper).value).toContain('Bread')
    await box.trigger('change') // off
    expect(textarea(wrapper).value).not.toContain('Bread')
  })

  it('copy uses the edited textarea content', async () => {
    const { showSuccess } = await import('@nextcloud/dialogs')
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText },
      configurable: true,
    })

    const wrapper = mountDialog(items)
    await wrapper.find('.md-export').setValue('edited content')
    const copyBtn = wrapper.findAll('.nc-button').find((b) => b.text().includes('Copy'))!
    await copyBtn.trigger('click')

    expect(writeText).toHaveBeenCalledWith('edited content')
    expect(showSuccess).toHaveBeenCalled()
  })

  it('re-seeds content from the list when reopened, discarding edits', async () => {
    const wrapper = mountDialog(items)
    await wrapper.find('.md-export').setValue('edited content')

    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })

    expect(textarea(wrapper).value).toContain('Milk')
    expect(textarea(wrapper).value).not.toBe('edited content')
  })
})
