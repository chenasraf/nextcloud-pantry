import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size'],
  },
}))
vi.mock('@/api/images', () => ({
  itemImagePreviewUrl: (houseId: number, fileId: number, owner: string, size: number) =>
    `/preview/${houseId}/${fileId}/${owner}/${size}`,
}))

import ChecklistImagePreview from './ChecklistImagePreview.vue'

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 42,
    listId: 1,
    name: 'Milk',
    description: null,
    categoryId: null,
    quantity: null,
    done: false,
    doneAt: null,
    doneBy: null,
    rrule: null,
    repeatFromCompletion: false,
    nextDueAt: null,
    imageFileId: 77,
    imageUploadedBy: 'admin',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const defaultProps = {
  open: true,
  item: makeItem(),
  houseId: 10,
}

describe('ChecklistImagePreview', () => {
  it('renders image when item has imageFileId', () => {
    const wrapper = mount(ChecklistImagePreview, { props: defaultProps })
    const img = wrapper.find('.image-preview img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/preview/10/77/admin/1600')
    expect(img.attributes('alt')).toBe('Milk')
  })

  it('passes item name as dialog name', () => {
    const wrapper = mount(ChecklistImagePreview, {
      props: { ...defaultProps, item: makeItem({ name: 'Eggs' }) },
    })
    const dialog = wrapper.findComponent({ name: 'NcDialog' })
    expect(dialog.props('name')).toBe('Eggs')
  })

  it("emits 'update:open' false on dialog close", async () => {
    const wrapper = mount(ChecklistImagePreview, { props: defaultProps })
    wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:open')).toBeTruthy()
    expect(wrapper.emitted('update:open')![0]).toEqual([false])
  })

  it('uses large size image URL', () => {
    const wrapper = mount(ChecklistImagePreview, { props: defaultProps })
    const img = wrapper.find('.image-preview img')
    // The URL should contain size 1600 (large)
    expect(img.attributes('src')).toContain('/1600')
  })
})
