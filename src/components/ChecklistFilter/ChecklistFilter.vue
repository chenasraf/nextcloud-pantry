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

    <div class="pantry-filter__chips">
      <FilterChip
        v-if="listOptions.length > 0"
        :label="strings.lists"
        :all-label="strings.allLists"
        :icon="ListIcon"
        :model-value="selectedListIdsLocal"
        :options="listOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedListIds', $event)"
      />
      <FilterChip
        v-if="stockOptions.length > 0"
        :label="strings.stock"
        :all-label="strings.allItems"
        :icon="StockIcon"
        :model-value="selectedStockLocal"
        :options="stockOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedStock', $event)"
      />
      <FilterChip
        v-if="categoryOptions.length > 0"
        :label="strings.categories"
        :all-label="strings.allCategories"
        :icon="CategoryIcon"
        :model-value="selectedCategoryIds"
        :options="categoryOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedCategoryIds', $event)"
      />
      <FilterChip
        v-if="storeOptions.length > 0"
        :label="strings.stores"
        :all-label="strings.allStores"
        :icon="StoreIcon"
        :model-value="selectedStoreIdsLocal"
        :options="storeOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedStoreIds', $event)"
      />
      <FilterChip
        v-if="labelOptions.length > 0"
        :label="strings.labels"
        :all-label="strings.allLabels"
        :icon="LabelIcon"
        :model-value="selectedLabelIdsLocal"
        :options="labelOptions"
        :total-count="totalCount"
        @update:model-value="$emit('update:selectedLabelIds', $event)"
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
import FormatListChecksIcon from '@icons/FormatListChecks.vue'
import PackageVariantIcon from '@icons/PackageVariant.vue'
import AlertCircleOutlineIcon from '@icons/AlertCircleOutline.vue'
import CheckCircleOutlineIcon from '@icons/CheckCircleOutline.vue'
import { entityIcon, entityNoneIcon } from '@/utils/entityIcons'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import { checklistIconComponent } from '@/components/ChecklistIconPicker'
import FilterChip, { type FilterOption } from './FilterChip.vue'
import PriceFilter, { type PriceFilterValue } from './PriceFilter.vue'
import { NO_CATEGORY_ID, NO_STORE_ID, NO_LABEL_ID } from './constants'
import { STOCK_IN_STOCK, STOCK_NEEDS_RESTOCK } from './stockFilter'
import { needsRestock } from '@/utils/stock'
import { hasPrice } from '@/utils/price'
import { allListsStrings, listKindStrings, useListKind } from '@/composables/useListKind'
import type { Category, Checklist, ChecklistItem, Store, Label } from '@/api/types'

const props = defineProps<{
  query: string
  selectedCategoryIds: number[]
  selectedStoreIds?: number[]
  selectedLabelIds?: number[]
  selectedListIds?: number[]
  /** Inventories only: {@link STOCK_NEEDS_RESTOCK} and/or {@link STOCK_IN_STOCK}. */
  selectedStock?: number[]
  priceFilter?: PriceFilterValue
  items: ChecklistItem[]
  categories: Category[]
  stores?: Store[]
  labels?: Label[]
  lists?: Checklist[]
}>()

const emit = defineEmits<{
  (e: 'update:query', v: string): void
  (e: 'update:selectedCategoryIds', v: number[]): void
  (e: 'update:selectedStoreIds', v: number[]): void
  (e: 'update:selectedLabelIds', v: number[]): void
  (e: 'update:selectedListIds', v: number[]): void
  (e: 'update:selectedStock', v: number[]): void
  (e: 'update:priceFilter', v: PriceFilterValue): void
}>()

const { kind } = useListKind()

// Facet trigger-chip icons, mirroring the Flutter filter bar.
const ListIcon = FormatListChecksIcon
const CategoryIcon = entityIcon.category
const LabelIcon = entityIcon.label
const StoreIcon = entityIcon.store
const StockIcon = PackageVariantIcon

const emptyPriceFilter: PriceFilterValue = { min: null, max: null, currency: null }
const priceFilterLocal = computed(() => props.priceFilter ?? emptyPriceFilter)
const anyPriced = computed(() => props.items.some((i) => i.prices.some((p) => hasPrice(p))))

const localQuery = computed({
  get: () => props.query,
  set: (v: string) => emit('update:query', v),
})

const totalCount = computed(() => props.items.length)

// ----- Stock (inventories only) -----

const selectedStockLocal = computed(() => props.selectedStock ?? [])

const stockOptions = computed<FilterOption[]>(() => {
  if (!props.items.some((i) => i.stockStatus !== undefined)) return []
  const restock = props.items.filter(needsRestock).length
  return [
    {
      id: STOCK_NEEDS_RESTOCK,
      label: strings.needsRestock,
      count: restock,
      icon: AlertCircleOutlineIcon,
      color: 'var(--color-element-warning, var(--color-warning))',
    },
    {
      id: STOCK_IN_STOCK,
      label: strings.inStock,
      count: props.items.length - restock,
      icon: CheckCircleOutlineIcon,
      color: 'var(--color-element-success, var(--color-success))',
    },
  ]
})

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
      icon: entityNoneIcon.category,
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
      icon: entityNoneIcon.store,
    })
  }
  return options
})

// ----- Labels -----

const selectedLabelIdsLocal = computed(() => props.selectedLabelIds ?? [])

const labelOptions = computed<FilterOption[]>(() => {
  const labels = props.labels ?? []
  const counts = new Map<number, number>()
  let unlabeled = 0
  for (const item of props.items) {
    const ids = item.labelIds ?? []
    if (ids.length === 0) {
      unlabeled += 1
      continue
    }
    for (const id of ids) {
      counts.set(id, (counts.get(id) ?? 0) + 1)
    }
  }
  const options: FilterOption[] = labels
    .filter((l) => counts.has(l.id))
    .map((l) => ({
      id: l.id,
      label: l.name,
      count: counts.get(l.id)!,
      icon: labelIconComponent(l.icon),
      color: l.color,
    }))
  if (unlabeled > 0) {
    options.push({
      id: NO_LABEL_ID,
      label: strings.noLabel,
      count: unlabeled,
      icon: entityNoneIcon.label,
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
  // TRANSLATORS: Noun. Label of the inventory filter dropdown for how much of each item is on hand.
  stock: t('pantry', 'Stock'),
  // TRANSLATORS: First row of the inventory stock filter, showing every item.
  allItems: t('pantry', 'All items'),
  // TRANSLATORS: Inventory stock filter row: items that are low or out.
  needsRestock: t('pantry', 'Needs restock'),
  // TRANSLATORS: Inventory stock filter row: items that are not low or out.
  inStock: t('pantry', 'In stock'),
  // TRANSLATORS: First row of the category filter that clears it to show every category.
  allCategories: t('pantry', 'All categories'),
  noCategory: t('pantry', 'No category'),
  // The list filter names the kind of the page it filters: checklists or inventories.
  lists: listKindStrings(kind.value).title,
  allLists: allListsStrings(kind.value).title,
  // TRANSLATORS: Noun (plural). Label of the category filter dropdown.
  categories: t('pantry', 'Categories'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Label of the store filter dropdown.
  stores: t('pantry', 'Stores'),
  // TRANSLATORS: Option that clears the store filter to show items from all stores.
  allStores: t('pantry', 'All stores'),
  // TRANSLATORS: Filter option matching items with no store attached.
  noStore: t('pantry', 'No stores'),
  // TRANSLATORS: Noun (plural), tags on items. Label of the label filter dropdown.
  labels: t('pantry', 'Labels'),
  // TRANSLATORS: Option that clears the label filter to show items with any label.
  allLabels: t('pantry', 'All labels'),
  // TRANSLATORS: Filter option matching items with no label attached.
  noLabel: t('pantry', 'No label'),
}
</script>

<style scoped lang="scss">
.pantry-filter {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  &__chips {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }
}
</style>
