import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Category } from '@/api/types'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// Mock icon imports used by categoryIcons.ts
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Tag.vue', () => createIconMock('TagIcon'))
vi.mock('@icons/Food.vue', () => createIconMock('FoodIcon'))
vi.mock('@icons/FoodApple.vue', () => createIconMock('FruitIcon'))
vi.mock('@icons/Carrot.vue', () => createIconMock('VegetableIcon'))
vi.mock('@icons/BreadSlice.vue', () => createIconMock('BakeryIcon'))
vi.mock('@icons/Cheese.vue', () => createIconMock('DairyIcon'))
vi.mock('@icons/FoodDrumstick.vue', () => createIconMock('MeatIcon'))
vi.mock('@icons/Fish.vue', () => createIconMock('FishIcon'))
vi.mock('@icons/FoodCroissant.vue', () => createIconMock('SnacksIcon'))
vi.mock('@icons/Cookie.vue', () => createIconMock('CookieIcon'))
vi.mock('@icons/BottleWine.vue', () => createIconMock('DrinksIcon'))
vi.mock('@icons/Coffee.vue', () => createIconMock('CoffeeIcon'))
vi.mock('@icons/Snowflake.vue', () => createIconMock('FrozenIcon'))
vi.mock('@icons/Broom.vue', () => createIconMock('HouseholdIcon'))
vi.mock('@icons/Dog.vue', () => createIconMock('PetsIcon'))
vi.mock('@icons/Baby.vue', () => createIconMock('BabyIcon'))
vi.mock('@icons/Home.vue', () => createIconMock('HomeIcon'))
vi.mock('@icons/Leaf.vue', () => createIconMock('LeafIcon'))
vi.mock('@icons/Pizza.vue', () => createIconMock('PizzaIcon'))

// Mock Nextcloud Vue components that pull in CSS
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<div class="nc-select" />',
    props: ['modelValue', 'options', 'clearable', 'placeholder', 'inputLabel', 'label'],
    emits: ['update:modelValue', 'option:selected'],
  },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div><slot /><slot name="actions" /></div>',
    props: ['name', 'open'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template: '<input />',
    props: ['modelValue', 'label', 'placeholder'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button><slot /><slot name="icon" /></button>',
    props: ['variant', 'disabled', 'type'],
  },
}))
// Mock the shared CategoryFormDialog with a minimal stub — tests verify
// interaction via its props/events, not its internal markup.
vi.mock('@/components/CategoryManager/CategoryFormDialog.vue', () => ({
  default: {
    name: 'CategoryFormDialog',
    template:
      '<div v-if="open" class="mock-cat-form-dialog"><slot /><span class="error" v-if="error">{{ error }}</span></div>',
    props: ['open', 'category', 'saving', 'error'],
    emits: ['update:open', 'save'],
  },
}))

// Mock useCategories composable
const mockItems = ref<Category[]>([])
const mockLoad = vi.fn().mockResolvedValue(undefined)
const mockCreate = vi.fn()

vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    items: mockItems,
    loading: ref(false),
    error: ref(null),
    loaded: ref(true),
    load: mockLoad,
    create: mockCreate,
    update: vi.fn(),
    remove: vi.fn(),
    findById: vi.fn(),
  }),
}))

import CategoryPicker from './CategoryPicker.vue'

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    houseId: 10,
    name: 'Dairy',
    icon: 'dairy',
    color: '#22c55e',
    sortOrder: 0,
    createdAt: 1000,
    updatedAt: 1000,
    ...overrides,
  }
}

describe('CategoryPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockItems.value = []
  })

  describe('rendering', () => {
    it('renders with required props', () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('.pantry-category-picker').exists()).toBe(true)
    })

    it('calls load on mount', () => {
      mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      expect(mockLoad).toHaveBeenCalled()
    })

    it('shows the label when provided', () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null, label: 'Category' },
        global: {},
      })

      const label = wrapper.find('.pantry-category-picker__label')
      expect(label.exists()).toBe(true)
      expect(label.text()).toBe('Category')
    })

    it('does not show label when not provided', () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      expect(wrapper.find('.pantry-category-picker__label').exists()).toBe(false)
    })

    it('passes placeholder text to NcSelect', () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null, placeholder: 'Choose one' },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      expect(select.props('placeholder')).toBe('Choose one')
    })

    it('uses default placeholder when none provided', () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      expect(select.props('placeholder')).toBe('Pick a category')
    })
  })

  describe('options', () => {
    it('renders category options from the composable', () => {
      const dairy = makeCategory({ id: 1, name: 'Dairy' })
      const produce = makeCategory({ id: 2, name: 'Produce', icon: 'fruit', color: '#ef4444' })
      mockItems.value = [dairy, produce]

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      const options = select.props('options') as Array<{
        label: string
        id?: number
        create?: boolean
      }>
      // Should have 2 category options + 1 create option
      expect(options).toHaveLength(3)
      expect(options[0]).toMatchObject({ label: 'Dairy', id: 1 })
      expect(options[1]).toMatchObject({ label: 'Produce', id: 2 })
    })

    it('includes a "Create new category" option at the end', () => {
      mockItems.value = [makeCategory()]

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      const options = select.props('options') as Array<{ label: string; create?: boolean }>
      const lastOption = options[options.length - 1]
      expect(lastOption.create).toBe(true)
      expect(lastOption.label).toContain('Create new category')
    })

    it('shows create option even when no categories exist', () => {
      mockItems.value = []

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      const options = select.props('options') as Array<{ label: string; create?: boolean }>
      expect(options).toHaveLength(1)
      expect(options[0].create).toBe(true)
    })
  })

  describe('create dialog', () => {
    it('opens create dialog when the create option is selected', async () => {
      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      // Dialog not visible initially
      expect(wrapper.findComponent({ name: 'CategoryFormDialog' }).props('open')).toBe(false)

      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('option:selected', { label: 'Create new category …', create: true })
      await wrapper.vm.$nextTick()

      expect(wrapper.findComponent({ name: 'CategoryFormDialog' }).props('open')).toBe(true)
    })

    it('emits update:modelValue with created category id after save event', async () => {
      const createdCategory = makeCategory({ id: 42, name: 'New Cat' })
      mockCreate.mockResolvedValueOnce(createdCategory)

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      // Open dialog
      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('option:selected', { label: 'Create new category …', create: true })
      await wrapper.vm.$nextTick()

      // Trigger save from the CategoryFormDialog
      const formDialog = wrapper.findComponent({ name: 'CategoryFormDialog' })
      formDialog.vm.$emit('save', { name: 'New Cat', icon: 'tag', color: '#ef4444' })

      await vi.waitFor(() => {
        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      })

      const emitted = wrapper.emitted('update:modelValue')!
      expect(emitted[emitted.length - 1]).toEqual([42])
    })

    it('calls create with name, icon, and color from the save event', async () => {
      const createdCategory = makeCategory({ id: 50, name: 'Snacks' })
      mockCreate.mockResolvedValueOnce(createdCategory)

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('option:selected', { label: 'Create new category …', create: true })
      await wrapper.vm.$nextTick()

      const formDialog = wrapper.findComponent({ name: 'CategoryFormDialog' })
      formDialog.vm.$emit('save', { name: 'Snacks', icon: 'snacks', color: '#ef4444' })

      await vi.waitFor(() => {
        expect(mockCreate).toHaveBeenCalled()
      })

      expect(mockCreate).toHaveBeenCalledWith({
        name: 'Snacks',
        icon: 'snacks',
        color: '#ef4444',
      })
    })

    it('passes error prop to CategoryFormDialog when create fails', async () => {
      mockCreate.mockRejectedValueOnce(new Error('Server error'))

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('option:selected', { label: 'Create new category …', create: true })
      await wrapper.vm.$nextTick()

      const formDialog = wrapper.findComponent({ name: 'CategoryFormDialog' })
      formDialog.vm.$emit('save', { name: 'Test', icon: 'tag', color: '#ef4444' })

      await vi.waitFor(() => {
        expect(wrapper.findComponent({ name: 'CategoryFormDialog' }).props('error')).toBe(
          'Server error',
        )
      })
    })
  })

  describe('selection', () => {
    it('emits update:modelValue when a category is selected', async () => {
      const dairy = makeCategory({ id: 5, name: 'Dairy' })
      mockItems.value = [dairy]

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: null },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('update:modelValue', { label: 'Dairy', id: 5, category: dairy })
      await wrapper.vm.$nextTick()

      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toBeTruthy()
      expect(emitted![0]).toEqual([5])
    })

    it('emits update:modelValue with null when cleared', async () => {
      const dairy = makeCategory({ id: 5, name: 'Dairy' })
      mockItems.value = [dairy]

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: 5 },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      select.vm.$emit('update:modelValue', null)
      await wrapper.vm.$nextTick()

      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toBeTruthy()
      expect(emitted![0]).toEqual([null])
    })

    it('resolves selected option from modelValue and items', () => {
      const dairy = makeCategory({ id: 5, name: 'Dairy' })
      mockItems.value = [dairy]

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: 5 },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      const modelValue = select.props('modelValue') as { label: string; id: number } | null
      expect(modelValue).toMatchObject({ label: 'Dairy', id: 5 })
    })

    it('returns null selected when modelValue does not match any item', () => {
      mockItems.value = []

      const wrapper = mount(CategoryPicker, {
        props: { houseId: 10, modelValue: 999 },
        global: {},
      })

      const select = wrapper.findComponent({ name: 'NcSelect' })
      expect(select.props('modelValue')).toBeNull()
    })
  })
})
