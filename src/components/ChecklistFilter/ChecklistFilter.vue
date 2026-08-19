<template>
  <div class="pantry-filter">
    <NcTextField
      v-model="localQuery"
      :placeholder="strings.placeholder"
      :show-trailing-button="localQuery.length > 0"
      trailing-button-icon="close"
      class="pantry-filter__search"
      @trailing-button-click="localQuery = ''"
    >
      <template #icon>
        <MagnifyIcon :size="18" />
      </template>
    </NcTextField>

    <div class="pantry-filter__dropdowns">
      <FilterDropdown
        v-if="listOptions.length > 0"
        :label="strings.lists"
        :all-label="strings.allLists"
        :model-value="selectedListIdsLocal"
        :options="listOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedListIds', $event)"
      />
      <FilterDropdown
        v-if="categoryOptions.length > 0"
        :label="strings.categories"
        :all-label="strings.all"
        :model-value="selectedCategoryIds"
        :options="categoryOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedCategoryIds', $event)"
      />
      <FilterDropdown
        v-if="storeOptions.length > 0"
        :label="strings.stores"
        :all-label="strings.allStores"
        :model-value="selectedStoreIdsLocal"
        :options="storeOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedStoreIds', $event)"
      />
      <PriceFilter
        v-if="anyPriced"
        :model-value="priceFilterLocal"
        @update:model-value="$emit('update:priceFilter', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import MagnifyIcon from '@icons/Magnify.vue'
import TagOffOutlineIcon from '@icons/TagOffOutline.vue'
import StoreOffOutlineIcon from '@icons/StoreOffOutline.vue'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { checklistIconComponent } from '@/components/ChecklistIconPicker'
import FilterDropdown, { type FilterOption } from './FilterDropdown.vue'
import PriceFilter, { type PriceFilterValue } from './PriceFilter.vue'
import { NO_CATEGORY_ID, NO_STORE_ID } from './constants'
import { hasPrice } from '@/utils/price'
import type { Category, Checklist, ChecklistItem, Store } from '@/api/types'

const props = defineProps<{
  query: string
  selectedCategoryIds: number[]
  selectedStoreIds?: number[]
  selectedListIds?: number[]
  priceFilter?: PriceFilterValue
  items: ChecklistItem[]
  categories: Category[]
  stores?: Store[]
  lists?: Checklist[]
}>()

const emit = defineEmits<{
  (e: 'update:query', v: string): void
  (e: 'update:selectedCategoryIds', v: number[]): void
  (e: 'update:selectedStoreIds', v: number[]): void
  (e: 'update:selectedListIds', v: number[]): void
  (e: 'update:priceFilter', v: PriceFilterValue): void
}>()

const emptyPriceFilter: PriceFilterValue = { min: null, max: null, currency: null }
const priceFilterLocal = computed(() => props.priceFilter ?? emptyPriceFilter)
const anyPriced = computed(() => props.items.some((i) => i.prices.some((p) => hasPrice(p))))

const localQuery = computed({
  get: () => props.query,
  set: (v: string) => emit('update:query', v),
})

const totalCount = computed(() => props.items.length)

// ----- Categories -----

const categoryOptions = computed<FilterOption[]>(() => {
  const counts = new Map<number, number>()
  for (const item of props.items) {
    if (item.categoryId != null) {
      counts.set(item.categoryId, (counts.get(item.categoryId) ?? 0) + 1)
    }
  }
  const options: FilterOption[] = props.categories
    .filter((c) => counts.has(c.id))
    .map((c) => ({
      id: c.id,
      label: c.name,
      count: counts.get(c.id)!,
      icon: categoryIconComponent(c.icon),
      color: c.color,
    }))
  const uncategorized = props.items.filter((i) => i.categoryId == null).length
  if (uncategorized > 0) {
    options.push({
      id: NO_CATEGORY_ID,
      label: strings.noCategory,
      count: uncategorized,
      icon: TagOffOutlineIcon,
    })
  }
  return options
})

// ----- Stores -----

const selectedStoreIdsLocal = computed(() => props.selectedStoreIds ?? [])

const storeOptions = computed<FilterOption[]>(() => {
  const stores = props.stores ?? []
  const counts = new Map<number, number>()
  let storeless = 0
  for (const item of props.items) {
    const ids = item.storeIds ?? []
    if (ids.length === 0) {
      storeless += 1
      continue
    }
    for (const id of ids) {
      counts.set(id, (counts.get(id) ?? 0) + 1)
    }
  }
  const options: FilterOption[] = stores
    .filter((s) => counts.has(s.id))
    .map((s) => ({
      id: s.id,
      label: s.name,
      count: counts.get(s.id)!,
      icon: storeIconComponent(s.icon),
      color: s.color,
    }))
  if (storeless > 0) {
    options.push({
      id: NO_STORE_ID,
      label: strings.noStore,
      count: storeless,
      icon: StoreOffOutlineIcon,
    })
  }
  return options
})

// ----- Lists (meta "All lists" view only) -----

const selectedListIdsLocal = computed(() => props.selectedListIds ?? [])

const listOptions = computed<FilterOption[]>(() => {
  if (!props.lists || props.lists.length === 0) return []
  const counts = new Map<number, number>()
  for (const item of props.items) {
    counts.set(item.listId, (counts.get(item.listId) ?? 0) + 1)
  }
  return props.lists.map((l) => ({
    id: l.id,
    label: l.name,
    count: counts.get(l.id) ?? 0,
    icon: checklistIconComponent(l.icon),
    color: l.color ?? undefined,
  }))
})

const strings = {
  placeholder: t('pantry', 'Type to filter …'),
  // TRANSLATORS: Adjective/label meaning "no category filter — show all". Option that clears the category filter.
  all: t('pantry', 'All'),
  noCategory: t('pantry', 'No category'),
  // TRANSLATORS: Noun (plural). Label of the list filter dropdown.
  lists: t('pantry', 'Lists'),
  allLists: t('pantry', 'All lists'),
  // TRANSLATORS: Noun (plural). Label of the category filter dropdown.
  categories: t('pantry', 'Categories'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Label of the store filter dropdown.
  stores: t('pantry', 'Stores'),
  // TRANSLATORS: Option that clears the store filter to show items from all stores.
  allStores: t('pantry', 'All stores'),
  // TRANSLATORS: Filter option matching items with no store attached.
  noStore: t('pantry', 'No store'),
}
</script>

<style scoped lang="scss">
.pantry-filter {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  &__dropdowns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    align-items: start;

    // Tablet: drop to two columns, then a single full-width column on mobile.
    @media (max-width: 900px) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @media (max-width: 600px) {
      grid-template-columns: 1fr;
    }
  }
}
</style>
