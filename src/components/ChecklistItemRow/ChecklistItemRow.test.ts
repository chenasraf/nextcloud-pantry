import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { ChecklistItem, Category, Store } from '@/api/types'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// The row reads its capabilities from useCurrentHouse; grant everything in unit tests.
const { ALL_CAPS } = vi.hoisted(() => ({
  ALL_CAPS: {
    canViewLists: true,
    canCreateLists: true,
    canEditLists: true,
    canDeleteLists: true,
    canAddItems: true,
    canDeleteItems: true,
    canCopyItems: true,
    canMoveItems: true,
    canCheckItems: true,
    canViewPhotos: true,
    canUploadPhotos: true,
    canUpdatePhotos: true,
    canDeletePhotos: true,
    canMovePhotos: true,
    canViewNotes: true,
    canCreateNotes: true,
    canUpdateNotes: true,
    canDeleteNotes: true,
  },
}))
vi.mock('@/composables/useCurrentHouse', async () => {
  const { ref } = await import('vue')
  return { useCurrentHouse: () => ({ can: ref(ALL_CAPS) }) }
})
vi.mock('@icons/Repeat.vue', () => createIconMock('RepeatIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/ArrowRight.vue', () => createIconMock('ArrowRightIcon'))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button class="nc-button" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
    props: ['variant', 'ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
    props: ['modelValue', 'disabled'],
  },
}))
vi.mock('@nextcloud/vue/components/NcPopover', () => ({
  default: {
    name: 'NcPopover',
    template: '<div class="nc-popover"><slot name="trigger" /></div>',
    props: ['shown', 'popupRole'],
  },
}))
vi.mock('@/api/lists', () => ({ listLinkTargets: vi.fn().mockResolvedValue([]) }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: { name: 'NcTextField', template: '<input class="nc-text-field" />' },
}))
vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions"><slot /></div>',
    props: ['ariaLabel'],
  },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template:
      '<button class="nc-action-button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
  },
}))
vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: {
    name: 'NcAvatar',
    template: '<span class="nc-avatar" :data-user="user" />',
    props: ['user', 'size', 'showUserStatus', 'tooltipMessage'],
  },
}))

vi.mock('@/components/CategoryPicker', () => ({
  categoryIconComponent: () => ({
    name: 'MockCategoryIcon',
    template: '<span class="mock-category-icon" />',
    props: ['size'],
  }),
}))

vi.mock('@/composables/useHouseMembers', () => ({
  useHouseMembers: () => ({
    members: { value: [] },
    displayNameByUid: { value: {} },
  }),
}))

vi.mock('@/api/images', () => ({
  itemImagePreviewUrl: (houseId: number, fileId: number, uploadedBy: string, size: number) =>
    `/mock/preview/${houseId}/${fileId}/${uploadedBy}/${size}`,
}))

vi.mock('@/utils/rrule', () => ({
  formatRrule: (rrule: string) => rrule,
  formatNextRecurrence: () => null,
}))

const { narrowRef } = vi.hoisted(() => ({ narrowRef: { value: false } }))
vi.mock('@/composables/useNarrow', async () => {
  const { ref } = await import('vue')
  const narrow = ref(false)
  Object.defineProperty(narrowRef, 'value', {
    get: () => narrow.value,
    set: (v: boolean) => (narrow.value = v),
  })
  return { useNarrow: () => narrow }
})

import { defineComponent, h, ref as vueRef } from 'vue'
import ChecklistItemRow from './ChecklistItemRow.vue'
import { provideListKind } from '@/composables/useListKind'
import { addDays, todayEpoch } from '@/utils/stock'

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
  return {
    id: 1,
    listId: 10,
    name: 'Milk',
    description: null,
    categoryId: null,
    storeIds: [],
    labelIds: [],
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
    barcode: null,
    prices: [],
    customFields: [],
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    archivedAt: null,
    ...overrides,
  }
}

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    houseId: 1,
    listId: null,
    name: 'Dairy',
    icon: 'cow',
    color: '#3366ff',
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

function makeStore(overrides: Partial<Store> = {}): Store {
  return {
    id: 1,
    houseId: 1,
    name: 'Supermarket',
    icon: 'store',
    color: '#22c55e',
    brand: null,
    location: null,
    openingHours: null,
    contact: null,
    responsible: null,
    notes: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

const defaultProps = {
  item: makeItem(),
  category: null as Category | null,
  houseId: 1,
}

describe('ChecklistItemRow', () => {
  describe('rendering', () => {
    it('renders item name', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ name: 'Eggs' }) },
      })
      expect(wrapper.find('.checklist-row__name').text()).toBe('Eggs')
    })

    it('shows done styling when item.done is true', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ done: true, doneAt: 1000, doneBy: 'admin' }) },
      })
      expect(wrapper.find('.checklist-row').classes()).toContain('checklist-row--done')
    })

    it('shows quantity badge when present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ quantity: '3' }) },
      })
      const qty = wrapper.find('.checklist-row__quantity')
      expect(qty.exists()).toBe(true)
      expect(qty.text()).toContain('3')
    })

    it('shows the store-less price chip when no store context is given', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({
            prices: [
              {
                storeId: null,
                priceType: 'set',
                priceMin: 5,
                priceMax: null,
                priceCurrency: 'USD',
              },
              { storeId: 7, priceType: 'set', priceMin: 9, priceMax: null, priceCurrency: 'USD' },
            ],
          }),
        },
      })
      const chip = wrapper.find('.checklist-row__price')
      expect(chip.exists()).toBe(true)
      expect(chip.text()).toBe('$5')
    })

    it('resolves the store price when a price-store-id is given', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          priceStoreId: 7,
          item: makeItem({
            prices: [
              {
                storeId: null,
                priceType: 'set',
                priceMin: 5,
                priceMax: null,
                priceCurrency: 'USD',
              },
              { storeId: 7, priceType: 'set', priceMin: 9, priceMax: null, priceCurrency: 'USD' },
            ],
          }),
        },
      })
      expect(wrapper.find('.checklist-row__price').text()).toBe('$9')
    })

    it('falls back to the store-less price for a store with no price', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          priceStoreId: 8,
          item: makeItem({
            prices: [
              {
                storeId: null,
                priceType: 'set',
                priceMin: 5,
                priceMax: null,
                priceCurrency: 'USD',
              },
            ],
          }),
        },
      })
      expect(wrapper.find('.checklist-row__price').text()).toBe('$5')
    })

    it('shows category badge with color when category is provided', () => {
      const category = makeCategory({ name: 'Dairy', color: '#ff0000' })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ categoryId: category.id }), category },
      })
      const cat = wrapper.find('.checklist-row__category')
      expect(cat.exists()).toBe(true)
      expect(cat.text()).toContain('Dairy')
      expect(cat.attributes('style')).toContain('color: #ff0000')
    })

    it('renders a store chip for each store and emits view-store on click', async () => {
      const store = makeStore({ id: 7, name: 'Pharmacy', color: '#ff0000' })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ storeIds: [7] }), stores: [store] },
      })
      const chip = wrapper.find('.checklist-row__store')
      expect(chip.exists()).toBe(true)
      expect(chip.text()).toContain('Pharmacy')
      expect(chip.attributes('style')).toContain('color: #ff0000')

      await chip.trigger('click')
      expect(wrapper.emitted('view-store')).toBeTruthy()
      expect(wrapper.emitted('view-store')![0]).toEqual([store])
    })

    it('does not emit view when a store chip is clicked', async () => {
      const store = makeStore({ id: 7 })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ storeIds: [7] }), stores: [store] },
      })
      await wrapper.find('.checklist-row__store').trigger('click')
      expect(wrapper.emitted('view')).toBeFalsy()
    })

    it('shows recurrence badge when rrule is present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ rrule: 'FREQ=WEEKLY' }) },
      })
      const rec = wrapper.find('.checklist-row__recurrence')
      expect(rec.exists()).toBe(true)
      expect(rec.text()).toContain('FREQ=WEEKLY')
    })

    it('shows description badge with the description as its tooltip', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ description: 'Organic, from the farm' }) },
      })
      const desc = wrapper.find('.checklist-row__description')
      expect(desc.exists()).toBe(true)
      expect(desc.attributes('title')).toBe('Organic, from the farm')
    })

    it('hides description badge when there is no description', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ description: null }) },
      })
      expect(wrapper.find('.checklist-row__description').exists()).toBe(false)
    })

    it('shows image thumbnail when imageFileId is present', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({ imageFileId: 42, imageUploadedBy: 'admin' }),
        },
      })
      const thumb = wrapper.find('.checklist-row__thumb')
      expect(thumb.exists()).toBe(true)
      expect(thumb.find('img').attributes('src')).toBe('/mock/preview/1/42/admin/64')
    })

    it('does not show thumbnail when no imageFileId', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row__thumb').exists()).toBe(false)
    })

    it('marks an archived reuse suggestion with an archived chip', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, suggestion: true, item: makeItem({ archivedAt: 1000 }) },
      })
      const chip = wrapper.find('.checklist-row__archived')
      expect(chip.exists()).toBe(true)
      expect(chip.text()).toContain('Archived')
    })

    it('does not mark a non-archived suggestion', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, suggestion: true, item: makeItem({ archivedAt: null }) },
      })
      expect(wrapper.find('.checklist-row__archived').exists()).toBe(false)
    })

    it('does not show the archived chip outside suggestion mode', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ archivedAt: 1000 }) },
      })
      expect(wrapper.find('.checklist-row__archived').exists()).toBe(false)
    })
  })

  describe('view-only shared list (listWritable=false)', () => {
    it('disables the checkbox and hides every write action', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, listWritable: false },
      })
      expect(wrapper.find('input[type="checkbox"]').attributes('disabled')).toBeDefined()
      const actionTexts = wrapper.findAll('.nc-action-button').map((b) => b.text())
      expect(actionTexts).not.toContain('Edit item')
      expect(actionTexts).not.toContain('Move to list')
      expect(actionTexts).not.toContain('Copy to list')
      // The read-only "View item" button remains.
      expect(
        wrapper.findAll('.nc-button').some((b) => b.attributes('aria-label') === 'View item'),
      ).toBe(true)
    })

    it('keeps write affordances when listWritable is true (default)', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('input[type="checkbox"]').attributes('disabled')).toBeUndefined()
      expect(
        wrapper.findAll('.nc-button').some((b) => b.attributes('aria-label') === 'Edit item'),
      ).toBe(true)
    })
  })

  describe('events', () => {
    it('emits toggle with item id on checkbox change', async () => {
      const item = makeItem({ id: 5 })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      await wrapper.find('input[type="checkbox"]').trigger('change')
      expect(wrapper.emitted('toggle')).toBeTruthy()
      expect(wrapper.emitted('toggle')![0]).toEqual([5])
    })

    it('emits view with item on view button click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const viewBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.attributes('aria-label') === 'View item')!
      await viewBtn.trigger('click')
      expect(wrapper.emitted('view')).toBeTruthy()
      expect(wrapper.emitted('view')![0]).toEqual([item])
    })

    it('emits edit with item on edit button click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const editBtn = wrapper
        .findAll('.nc-button')
        .find((b) => b.attributes('aria-label') === 'Edit item')!
      await editBtn.trigger('click')
      expect(wrapper.emitted('edit')).toBeTruthy()
      expect(wrapper.emitted('edit')![0]).toEqual([item])
    })

    it('emits move with item on move action click', async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const moveBtn = wrapper.findAll('.nc-action-button').find((b) => b.text() === 'Move to list')!
      await moveBtn.trigger('click')
      expect(wrapper.emitted('move')).toBeTruthy()
      expect(wrapper.emitted('move')![0]).toEqual([item])
    })

    it('emits remove with item id on remove action click', async () => {
      const item = makeItem({ id: 9 })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      const removeBtn = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text() === 'Remove item')!
      await removeBtn.trigger('click')
      expect(wrapper.emitted('remove')).toBeTruthy()
      expect(wrapper.emitted('remove')![0]).toEqual([9])
    })

    it('emits preview with item on thumbnail click', async () => {
      const item = makeItem({ imageFileId: 42, imageUploadedBy: 'admin' })
      const wrapper = mount(ChecklistItemRow, { props: { ...defaultProps, item } })
      await wrapper.find('.checklist-row__thumb').trigger('click')
      expect(wrapper.emitted('preview')).toBeTruthy()
      expect(wrapper.emitted('preview')![0]).toEqual([item])
    })
  })

  describe('row click action', () => {
    const editLabel = (b: { attributes: (n: string) => string | undefined }) =>
      b.attributes('aria-label') === 'Edit item'
    const viewLabel = (b: { attributes: (n: string) => string | undefined }) =>
      b.attributes('aria-label') === 'View item'

    it("shows both view and edit icon buttons when action is 'none' (default)", () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      const buttons = wrapper.findAll('.nc-button')
      expect(buttons.some(viewLabel)).toBe(true)
      expect(buttons.some(editLabel)).toBe(true)
    })

    it("does nothing on row click when action is 'none'", async () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('view')).toBeFalsy()
      expect(wrapper.emitted('edit')).toBeFalsy()
    })

    it("toggles done on row click and keeps both icon buttons when action is 'done'", async () => {
      const item = makeItem({ id: 5 })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item, rowClickAction: 'done' },
      })
      const buttons = wrapper.findAll('.nc-button')
      // View and edit stay available (neither is the click action).
      expect(buttons.some(viewLabel)).toBe(true)
      expect(buttons.some(editLabel)).toBe(true)
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('toggle')![0]).toEqual([5])
    })

    it("emits view on row click and hides the view icon when action is 'view'", async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item, rowClickAction: 'view' },
      })
      expect(wrapper.findAll('.nc-button').some(viewLabel)).toBe(false)
      // Edit is not the click action, so its icon button stays.
      expect(wrapper.findAll('.nc-button').some(editLabel)).toBe(true)
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('view')![0]).toEqual([item])
    })

    it("emits edit on row click and hides the edit icon when action is 'edit'", async () => {
      const item = makeItem()
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item, rowClickAction: 'edit' },
      })
      expect(wrapper.findAll('.nc-button').some(editLabel)).toBe(false)
      expect(wrapper.findAll('.nc-button').some(viewLabel)).toBe(true)
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('edit')![0]).toEqual([item])
    })

    it('does not trigger the row action when the checkbox is clicked', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, rowClickAction: 'view' },
      })
      await wrapper.find('.nc-checkbox').trigger('click')
      expect(wrapper.emitted('view')).toBeFalsy()
    })
  })

  describe('session removal', () => {
    it('hides the remove-from-trip button by default', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row__session-remove').exists()).toBe(false)
    })

    it('shows the remove-from-trip button when sessionRemovable', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, sessionRemovable: true, compact: true },
      })
      expect(wrapper.find('.checklist-row__session-remove').exists()).toBe(true)
    })

    it('emits session-remove with item id when clicked', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({ id: 7 }),
          sessionRemovable: true,
          compact: true,
        },
      })
      await wrapper.find('.checklist-row__session-remove .nc-button').trigger('click')
      expect(wrapper.emitted('session-remove')).toBeTruthy()
      expect(wrapper.emitted('session-remove')![0]).toEqual([7])
    })
  })

  describe('selection mode', () => {
    it('shows a selection checkbox and hides the actions', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, selectionMode: true },
      })
      expect(wrapper.find('.checklist-row__select').exists()).toBe(true)
      expect(wrapper.find('.checklist-row').classes()).toContain('checklist-row--selecting')
      // The eye/kebab actions cluster is gone in selection mode.
      expect(wrapper.find('.checklist-row__actions').exists()).toBe(false)
      expect(wrapper.find('.nc-actions').exists()).toBe(false)
    })

    it('reflects the selected prop and marks the row', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, selectionMode: true, selected: true },
      })
      expect(wrapper.find('.checklist-row').classes()).toContain('checklist-row--selected')
      expect(
        wrapper.find('.checklist-row__select input[type="checkbox"]').attributes('checked'),
      ).toBeDefined()
    })

    it('emits toggle-select when the selection checkbox changes', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 8 }), selectionMode: true },
      })
      await wrapper.find('.checklist-row__select input[type="checkbox"]').trigger('change')
      expect(wrapper.emitted('toggle-select')).toBeTruthy()
      expect(wrapper.emitted('toggle-select')![0]).toEqual([8])
    })

    it('emits toggle-select when the row body is tapped', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 8 }), selectionMode: true },
      })
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('toggle-select')).toBeTruthy()
      expect(wrapper.emitted('toggle-select')![0]).toEqual([8])
    })

    it('has no selection checkbox when not in selection mode', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row__select').exists()).toBe(false)
    })
  })

  describe('suggestion mode', () => {
    it('renders the name and meta chips but no checkbox or actions', () => {
      const category = makeCategory({ name: 'Dairy' })
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({ name: 'Milk', categoryId: category.id, quantity: '2' }),
          category,
          suggestion: true,
        },
      })
      expect(wrapper.find('.checklist-row').classes()).toContain('checklist-row--suggestion')
      expect(wrapper.find('.checklist-row__name').text()).toBe('Milk')
      // Meta chips still render.
      expect(wrapper.find('.checklist-row__category').text()).toContain('Dairy')
      expect(wrapper.find('.checklist-row__quantity').text()).toContain('2')
      // No checkbox, no selection control, no actions cluster.
      expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
      expect(wrapper.find('.checklist-row__select').exists()).toBe(false)
      expect(wrapper.find('.checklist-row__actions').exists()).toBe(false)
    })

    it('shows no drag handle even when reorderEnabled is set', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, suggestion: true, reorderEnabled: true },
      })
      expect(wrapper.find('.checklist-row__handle').exists()).toBe(false)
    })

    it('emits select with the item on row click (not toggle)', async () => {
      const item = makeItem({ id: 12 })
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item, suggestion: true },
      })
      await wrapper.find('.checklist-row').trigger('click')
      expect(wrapper.emitted('select')).toBeTruthy()
      expect(wrapper.emitted('select')![0]).toEqual([item])
      expect(wrapper.emitted('toggle')).toBeFalsy()
      expect(wrapper.emitted('toggle-select')).toBeFalsy()
    })
  })

  describe('reorderEnabled', () => {
    it('has no drag handle by default', () => {
      const wrapper = mount(ChecklistItemRow, { props: defaultProps })
      expect(wrapper.find('.checklist-row__handle').exists()).toBe(false)
    })

    it('exposes a draggable handle when reorderEnabled is true', () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: true },
      })
      const handle = wrapper.find('.checklist-row__handle')
      expect(handle.exists()).toBe(true)
      expect(handle.attributes('draggable')).toBe('true')
      // The row itself is not a drag source — only the handle is.
      expect(wrapper.find('.checklist-row').attributes('draggable')).toBeUndefined()
    })

    it('emits drag-start on handle dragstart when reorderEnabled', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 7 }), reorderEnabled: true },
      })
      await wrapper.find('.checklist-row__handle').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')).toBeTruthy()
      // Second arg is the reorder group key — undefined outside store sort.
      expect(wrapper.emitted('drag-start')![0]).toEqual([7, undefined])
    })

    it('emits drag-start with the reorder group key when set (store sort)', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: {
          ...defaultProps,
          item: makeItem({ id: 7 }),
          reorderEnabled: true,
          reorderGroupKey: 's-3',
        },
      })
      await wrapper.find('.checklist-row__handle').trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(wrapper.emitted('drag-start')![0]).toEqual([7, 's-3'])
      expect(wrapper.find('.checklist-row').attributes('data-drag-group')).toBe('s-3')
    })

    it('emits reorder-over on dragover when reorderEnabled', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, item: makeItem({ id: 2 }), reorderEnabled: true },
      })
      await wrapper.find('.checklist-row').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-checklist-item'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeTruthy()
    })

    it('does not emit reorder-over when reorderEnabled is false', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: false },
      })
      await wrapper.find('.checklist-row').trigger('dragover', {
        dataTransfer: { types: ['application/x-pantry-checklist-item'] },
      })
      expect(wrapper.emitted('reorder-over')).toBeFalsy()
    })

    it('applies dragging class on handle dragstart and removes on dragend', async () => {
      const wrapper = mount(ChecklistItemRow, {
        props: { ...defaultProps, reorderEnabled: true },
      })
      const row = wrapper.find('.checklist-row')
      const handle = wrapper.find('.checklist-row__handle')

      await handle.trigger('dragstart', {
        dataTransfer: { effectAllowed: '', setData: vi.fn() },
      })
      expect(row.classes()).toContain('checklist-row--dragging')

      await handle.trigger('dragend')
      expect(row.classes()).not.toContain('checklist-row--dragging')
    })
  })
})

describe('ChecklistItemRow on an inventory', () => {
  function mountInventoryRow(item: ChecklistItem) {
    const Host = defineComponent({
      setup() {
        provideListKind(vueRef('inventory'))
        return () =>
          h(ChecklistItemRow, {
            item,
            category: null,
            houseId: 1,
            onStock: (...args: unknown[]) => stockEvents.push(args),
          })
      },
    })
    const stockEvents: unknown[][] = []
    return { wrapper: mount(Host), stockEvents }
  }

  const countItem = (quantity: string | null, extra: Partial<ChecklistItem> = {}) =>
    makeItem({
      name: 'Eggs',
      quantity,
      stockMode: 'count',
      stockLevel: 'full',
      target: '12 eggs',
      reorderPoint: null,
      ...extra,
    })

  it('shows the status icon, the stock chip and on hand in the target wording', () => {
    narrowRef.value = false
    const { wrapper } = mountInventoryRow(
      countItem('4', { stockStatus: 'low', shortfall: '8 eggs' }),
    )

    expect(wrapper.find('.nc-checkbox').exists()).toBe(false)
    expect(wrapper.find('.stock-status-icon--warning').attributes('aria-label')).toBe(
      'Low · buy 8 eggs',
    )
    expect(wrapper.find('.checklist-row__stock-chip').text()).toBe('Low · buy 8 eggs')
    expect(wrapper.find('.stock-control__amount').text()).toBe('4 eggs')
    expect(wrapper.find('.checklist-row__quantity').exists()).toBe(false)
    expect(wrapper.find('.stock-gauge').exists()).toBe(true)
  })

  it('steps on hand, keeping its own text', async () => {
    narrowRef.value = false
    const { wrapper, stockEvents } = mountInventoryRow(countItem('4 eggs'))

    const [minus, plus] = wrapper.findAll('.stock-control__step')
    await plus.trigger('click')
    await minus.trigger('click')

    expect(stockEvents.map((e) => e[1])).toEqual([{ quantity: '5 eggs' }, { quantity: '3 eggs' }])
  })

  it('disables − at 0, and both buttons when on hand has no number', () => {
    narrowRef.value = false
    const empty = mountInventoryRow(countItem('0')).wrapper.findAll('.stock-control__step')
    expect(empty[0].attributes('disabled')).toBeDefined()
    expect(empty[1].attributes('disabled')).toBeUndefined()

    const some = mountInventoryRow(countItem('some', { stockStatus: 'uncounted' })).wrapper
    expect(
      some.findAll('.stock-control__step').every((b) => b.attributes('disabled') !== undefined),
    ).toBe(true)
    expect(some.find('.checklist-row__stock-chip').text()).toBe('Not counted')
    expect(some.find('.stock-gauge').exists()).toBe(false)
  })

  it('moves the amount into a chip on a narrow row', () => {
    narrowRef.value = true
    const { wrapper } = mountInventoryRow(countItem('4'))

    expect(wrapper.find('.checklist-row__stock-amount').text()).toBe('4 / 12 eggs')
    expect(wrapper.find('.stock-control__amount').exists()).toBe(false)
    expect(wrapper.findAll('.stock-control__step')).toHaveLength(2)
    narrowRef.value = false
  })

  it('sets a level item from the segmented control', async () => {
    narrowRef.value = false
    const { wrapper, stockEvents } = mountInventoryRow(
      makeItem({
        name: 'Flour',
        quantity: '1 kg bag',
        stockMode: 'level',
        stockLevel: 'full',
        target: null,
        reorderPoint: null,
        stockStatus: 'ok',
        shortfall: null,
      }),
    )

    const buttons = wrapper.findAll('.stock-level-control__button')
    expect(buttons.map((b) => b.text())).toEqual(['Full', 'Low', 'Out'])
    await buttons[2].trigger('click')

    expect(stockEvents.map((e) => e[1])).toEqual([{ stockLevel: 'out' }])
  })

  describe('expiry', () => {
    const today = todayEpoch()

    it('says when an expiring item goes off and asks for the full replacement', () => {
      narrowRef.value = false
      const { wrapper } = mountInventoryRow(
        countItem('4', {
          stockStatus: 'low',
          shortfall: '8 eggs',
          expiresAt: addDays(today, 1),
          expiryStatus: 'expiring',
        }),
      )

      const chips = wrapper.findAll('.checklist-row__stock-chip').map((c) => c.text())
      expect(chips).toEqual(['Low', 'Expires tomorrow · buy 12 eggs'])
      expect(wrapper.find('.stock-status-icon--warning').attributes('aria-label')).toBe(
        'Low, Expires tomorrow · buy 12 eggs',
      )
    })

    it('counts the days left and says today on the date', () => {
      const inThree = mountInventoryRow(
        countItem('12', { expiresAt: addDays(today, 3), expiryStatus: 'expiring' }),
      ).wrapper
      expect(inThree.find('.checklist-row__expiry-chip').text()).toBe(
        'Expires in 3 days · buy 12 eggs',
      )
      const onTheDay = mountInventoryRow(
        countItem('12', { expiresAt: today, expiryStatus: 'expiring' }),
      ).wrapper
      expect(onTheDay.find('.checklist-row__expiry-chip').text()).toBe(
        'Expires today · buy 12 eggs',
      )
    })

    it('turns the row red once expired, over a low stock', () => {
      const { wrapper } = mountInventoryRow(
        countItem('4', {
          stockStatus: 'low',
          shortfall: '8 eggs',
          expiresAt: addDays(today, -2),
          expiryStatus: 'expired',
        }),
      )

      expect(wrapper.find('.stock-status-icon--error').exists()).toBe(true)
      expect(wrapper.find('.checklist-row__expiry-chip').text()).toMatch(
        /^Expired .+ · buy 12 eggs$/,
      )
    })

    it('shows a neutral good-until chip when not expiring yet', () => {
      const { wrapper } = mountInventoryRow(
        countItem('12', { stockStatus: 'ok', expiresAt: addDays(today, 20), expiryStatus: 'ok' }),
      )

      expect(wrapper.find('.checklist-row__expiry-chip').text()).toMatch(/^Good until /)
      expect(wrapper.find('.stock-status-icon--success').exists()).toBe(true)
    })

    it('shows no expiry chip without a date', () => {
      const { wrapper } = mountInventoryRow(countItem('12', { expiryStatus: 'none' }))

      expect(wrapper.find('.checklist-row__expiry-chip').exists()).toBe(false)
    })
  })

  it('shows no stock on a checklist row', () => {
    const wrapper = mount(ChecklistItemRow, {
      props: { ...defaultProps, item: countItem('4') },
    })
    expect(wrapper.find('.stock-status-icon').exists()).toBe(false)
    expect(wrapper.find('.stock-control').exists()).toBe(false)
  })
})

describe('ChecklistItemRow stock links', () => {
  const row = {
    itemId: 50,
    name: 'Eggs',
    quantity: '8',
    listId: 3,
    listName: 'Groceries',
    listColor: '#ff9800',
    state: 'open' as const,
  }

  function linkedItem(extra: Partial<ChecklistItem> = {}) {
    return makeItem({
      name: 'Eggs',
      quantity: '4',
      stockMode: 'count',
      stockLevel: 'full',
      target: '12 eggs',
      reorderPoint: null,
      stockStatus: 'low',
      shortfall: '8 eggs',
      autoLink: true,
      stockLink: null,
      linkCandidates: [],
      ...extra,
    })
  }

  function mountRow(item: ChecklistItem, kind: 'inventory' | 'checklist' = 'inventory') {
    const events: Record<string, unknown[][]> = { link: [], unlink: [], autoLink: [] }
    const Host = defineComponent({
      setup() {
        provideListKind(vueRef(kind))
        return () =>
          h(ChecklistItemRow, {
            item,
            category: null,
            houseId: 1,
            onLink: (...args: unknown[]) => events.link.push(args),
            onUnlink: (...args: unknown[]) => events.unlink.push(args),
            onAutoLink: (...args: unknown[]) => events.autoLink.push(args),
          })
      },
    })
    return { wrapper: mount(Host), events }
  }

  it.each([
    [{ stockLink: row }, 'stock-link-button__icon--linked', 'Linked to Eggs on Groceries'],
    [
      { linkCandidates: [row, { ...row, itemId: 51 }] },
      'stock-link-button__icon--pick',
      'Not linked: 2 matches, pick one',
    ],
    [{}, 'stock-link-button__icon--auto', 'Not linked yet: added to the shopping list when low'],
    [
      { autoLink: false },
      'stock-link-button__icon--off',
      'Not linked: never added to a shopping list',
    ],
  ])('shows the link state on a wide row', (extra, cls, tooltip) => {
    narrowRef.value = false
    const { wrapper } = mountRow(linkedItem(extra as Partial<ChecklistItem>))

    const button = wrapper.find('[data-testid="stock-link-button"]')
    expect(button.find('.stock-link-button__icon').classes()).toContain(cls)
    expect(button.attributes('aria-label')).toBe(tooltip)
    expect(wrapper.find('[data-testid="stock-link-chip"]').exists()).toBe(false)
  })

  it('passes on what the popover changes', async () => {
    narrowRef.value = false
    const item = linkedItem({ stockLink: row })
    const { wrapper, events } = mountRow(item)
    const button = wrapper.findComponent({ name: 'StockLinkButton' })

    button.vm.$emit('link', row)
    button.vm.$emit('unlink')
    button.vm.$emit('auto-link', false)

    expect(events.link).toEqual([[item, row]])
    expect(events.unlink).toEqual([[item]])
    expect(events.autoLink).toEqual([[item, false]])
  })

  it('shows a linked chip in the checklist colour on a narrow row', () => {
    narrowRef.value = true
    const { wrapper } = mountRow(linkedItem({ stockLink: row }))

    const chip = wrapper.find('[data-testid="stock-link-chip"]')
    expect(chip.text()).toBe('Groceries')
    expect(chip.attributes('title')).toBe('Linked to Eggs on Groceries')
    expect(wrapper.find('[data-testid="stock-link-button"]').exists()).toBe(false)
    narrowRef.value = false
  })

  it('shows a warning chip on a narrow row that needs a pick', () => {
    narrowRef.value = true
    const { wrapper } = mountRow(linkedItem({ linkCandidates: [row, { ...row, itemId: 51 }] }))

    expect(wrapper.find('[data-testid="stock-link-chip"]').text()).toBe(
      'Not linked · 2 matches, pick one',
    )
    narrowRef.value = false
  })

  it('shows a link-off chip on a narrow row only while it needs restock', () => {
    narrowRef.value = true
    const low = mountRow(linkedItem({ autoLink: false })).wrapper
    expect(low.find('[data-testid="stock-link-chip"]').text()).toBe('Not added to a shopping list')

    const stocked = mountRow(
      linkedItem({ autoLink: false, quantity: '12', stockStatus: 'ok', shortfall: null }),
    ).wrapper
    expect(stocked.find('[data-testid="stock-link-chip"]').exists()).toBe(false)

    const auto = mountRow(linkedItem()).wrapper
    expect(auto.find('[data-testid="stock-link-chip"]').exists()).toBe(false)
    narrowRef.value = false
  })

  it('marks a checklist row with what it restocks', () => {
    const { wrapper } = mountRow(
      makeItem({
        name: 'Soda',
        restocks: [
          { itemId: 3, name: 'Soda', listId: 20, listName: 'Kitchen' },
          { itemId: 4, name: 'Cola', listId: 21, listName: 'Garage' },
        ],
      }),
      'checklist',
    )

    expect(wrapper.find('[data-testid="restocks-marker"]').attributes('title')).toBe(
      'Restocks Soda (Kitchen), Cola (Garage)',
    )
  })

  it('leaves an unlinked checklist row unmarked', () => {
    const { wrapper } = mountRow(makeItem({ restocks: [] }), 'checklist')

    expect(wrapper.find('[data-testid="restocks-marker"]').exists()).toBe(false)
  })
})

describe('ChecklistItemRow restock markers', () => {
  const restocks = [{ itemId: 3, name: 'Eggs', listId: 20, listName: 'Kitchen' }]

  function mountChecklistRow(item: ChecklistItem) {
    const unpinned: unknown[][] = []
    const Host = defineComponent({
      setup() {
        provideListKind(vueRef('checklist'))
        return () =>
          h(ChecklistItemRow, {
            item,
            category: null,
            houseId: 1,
            onUnpin: (...args: unknown[]) => unpinned.push(args),
          })
      },
    })
    return { wrapper: mount(Host), unpinned }
  }

  it('shows a quantity set by hand, and lets stock set it again', async () => {
    const item = makeItem({ name: 'Eggs', quantity: '2 dozen', quantityPinned: true, restocks })
    const { wrapper, unpinned } = mountChecklistRow(item)

    const chip = wrapper.find('[data-testid="pinned-quantity"]')
    expect(chip.text()).toBe('× 2 dozen · set by hand')
    expect(chip.attributes('title')).toBe('Set by hand. Tap to let stock set it.')
    expect(wrapper.find('.checklist-row__quantity').exists()).toBe(false)
    await chip.trigger('click')

    expect(unpinned).toEqual([[item]])
  })

  it('shows a plain quantity once nothing pins it', () => {
    const { wrapper } = mountChecklistRow(
      makeItem({ quantity: '2 dozen', quantityPinned: false, restocks }),
    )

    expect(wrapper.find('[data-testid="pinned-quantity"]').exists()).toBe(false)
    expect(wrapper.find('.checklist-row__quantity').text()).toContain('2 dozen')
  })

  it('says a row ticked off by stock is no longer needed', () => {
    const { wrapper } = mountChecklistRow(
      makeItem({ done: true, doneAt: 5, doneBy: null, restocks }),
    )

    expect(wrapper.find('[data-testid="no-longer-needed"]').text()).toBe('No longer needed')
  })

  it('says nothing more on a row a member ticked off', () => {
    const { wrapper } = mountChecklistRow(
      makeItem({ done: true, doneAt: 5, doneBy: 'alice', restocks }),
    )

    expect(wrapper.find('[data-testid="no-longer-needed"]').exists()).toBe(false)
  })

  const restock = [
    {
      itemId: 3,
      listId: 20,
      itemName: 'Eggs',
      listName: 'Kitchen',
      added: '8',
      previousLevel: null,
      replaced: false,
      previousOnHand: null,
      previousExpiresAt: null,
      expiresAt: null,
    },
  ]

  it('says what a ticked row restocked until it is unchecked', () => {
    const { wrapper } = mountChecklistRow(
      makeItem({ done: true, doneAt: 5, doneBy: 'alice', restocks, restock }),
    )

    expect(wrapper.find('[data-testid="restock-line"]').text()).toBe(
      'Restocked Eggs (Kitchen) +8 · uncheck to undo',
    )
  })

  it('has no restock line once unchecked', () => {
    const { wrapper } = mountChecklistRow(makeItem({ done: false, restocks, restock }))

    expect(wrapper.find('[data-testid="restock-line"]').exists()).toBe(false)
  })
})

describe('ChecklistItemRow renew offer', () => {
  function mountWithOffer(renewOffer: number | null) {
    const renewed: unknown[][] = []
    const item = makeItem({
      name: 'Eggs',
      quantity: '5',
      stockMode: 'count',
      target: '12',
      shelfLifeDays: 7,
    })
    const Host = defineComponent({
      setup() {
        provideListKind(vueRef('inventory'))
        return () =>
          h(ChecklistItemRow, {
            item,
            category: null,
            houseId: 1,
            renewOffer,
            onRenew: (...args: unknown[]) => renewed.push(args),
          })
      },
    })
    return { wrapper: mount(Host), renewed, item }
  }

  it('offers to renew the expiry date, and renews on tap', async () => {
    const date = Math.floor(new Date(2026, 9, 30).getTime() / 1000)
    const { wrapper, renewed, item } = mountWithOffer(date)

    const chip = wrapper.find('[data-testid="renew-expiry"]')
    expect(chip.text()).toContain('Renew expiry →')
    await chip.trigger('click')

    expect(renewed).toEqual([[item]])
  })

  it('shows no offer without one', () => {
    const { wrapper } = mountWithOffer(null)

    expect(wrapper.find('[data-testid="renew-expiry"]').exists()).toBe(false)
  })
})
