import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'

import { nextcloudL10nMock } from '@/test-utils'
import type { Category, Checklist } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@/components/CategoryPicker/categoryIcons', () => ({
  CATEGORY_COLORS: ['#ef4444', '#f97316', '#eab308', '#22c55e'],
  CATEGORY_ICONS: [],
  DEFAULT_CATEGORY_ICON_KEY: 'tag',
}))
vi.mock('@/utils/ncSelectPosition', () => ({ ncSelectCalculatePosition: () => undefined }))
vi.mock('@/composables/useChecklist', () => ({
  useAllKindLists: () => ({
    lists: ref([
      { id: 7, name: 'Courses', kind: 'checklist' },
      { id: 8, name: 'Frigo', kind: 'inventory' },
    ] as Checklist[]),
    load: vi.fn(),
  }),
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: defineComponent({
    props: ['name', 'open'],
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: defineComponent({
    props: ['modelValue', 'label', 'placeholder'],
    emits: ['update:modelValue'],
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: defineComponent({
    name: 'NcSelect',
    props: ['modelValue', 'options'],
    template: '<div class="nc-select" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    props: ['variant', 'disabled'],
    emits: ['click'],
    template: '<button class="nc-button" @click="$emit(\'click\')"><slot /></button>',
  }),
}))

import CategoryFormDialog from './CategoryFormDialog.vue'

function category(listId: number | null): Category {
  return {
    id: 3,
    houseId: 1,
    listId,
    name: 'Fromage',
    icon: 'tag',
    color: '#22c55e',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
  }
}

async function saveFrom(props: { category?: Category | null }) {
  const wrapper = mount(CategoryFormDialog, { props: { open: true, houseId: 1, ...props } })
  if (!props.category) await wrapper.find('input.nc-text-field').setValue('Surgelés')
  await wrapper.find('form').trigger('submit')
  return wrapper.emitted('save')?.[0]?.[0] as { name: string; listId: number | null }
}

describe('CategoryFormDialog', () => {
  it('creates a house-wide category, so checklists and inventories share it', async () => {
    const saved = await saveFrom({})

    expect(saved).toMatchObject({ name: 'Surgelés', listId: null })
  })

  it('keeps the list an edited category is narrowed to', async () => {
    const saved = await saveFrom({ category: category(7) })

    expect(saved).toMatchObject({ name: 'Fromage', listId: 7 })
  })
})
