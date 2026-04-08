<template>
  <div class="pantry-detail">
    <PageToolbar :title="list?.name">
      <template #before-title>
        <NcButton
          variant="tertiary"
          :aria-label="strings.back"
          @click="$router.push({ name: 'lists', params: { houseId } })"
        >
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
        </NcButton>
      </template>
      <template #actions>
        <NcActions :aria-label="strings.sortLabel" type="tertiary">
          <template #icon>
            <SortIcon :size="20" />
          </template>
          <NcActionButton
            v-for="opt in itemSortOptions"
            :key="opt.value"
            :class="{ 'pantry-sort-active': currentSort === opt.value }"
            @click="changeSort(opt.value)"
          >
            <template #icon>
              <RadioboxMarkedIcon v-if="currentSort === opt.value" :size="20" />
              <RadioboxBlankIcon v-else :size="20" />
            </template>
            {{ opt.label }}
          </NcActionButton>
        </NcActions>
      </template>
    </PageToolbar>

    <div class="pantry-detail__body">
      <ChecklistAddForm :house-id="houseIdNum" :adding="adding" @add="handleAdd" />

      <div v-if="loading" class="pantry-detail__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <component :is="checklistIconComponent(list?.icon)" />
        </template>
      </NcEmptyContent>

      <template v-else>
        <ul v-if="uncheckedItems.length > 0" ref="uncheckedListRef" class="pantry-detail__items">
          <template v-for="gi in uncheckedGridItems" :key="gi.key">
            <li
              v-if="gi.type === 'placeholder'"
              class="pantry-detail__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <ChecklistItemRow
              v-else
              :item="gi.item"
              :category="categoryFor(gi.item.categoryId)"
              :house-id="houseIdNum"
              :reorder-enabled="isCustomSort"
              @toggle="handleToggle"
              @view="openView"
              @edit="startEdit"
              @remove="handleRemove"
              @preview="openPreview"
              @drag-start="onItemDragStart"
              @reorder-over="onReorderOver"
            />
          </template>
        </ul>
        <template v-if="checkedItems.length > 0">
          <h3 class="pantry-detail__section-title">{{ strings.doneTitle }}</h3>
          <ul class="pantry-detail__items pantry-detail__items--done">
            <ChecklistItemRow
              v-for="item in checkedItems"
              :key="item.id"
              :item="item"
              :category="categoryFor(item.categoryId)"
              :house-id="houseIdNum"
              @toggle="handleToggle"
              @view="openView"
              @edit="startEdit"
              @remove="handleRemove"
              @preview="openPreview"
            />
          </ul>
        </template>
      </template>
    </div>

    <ChecklistItemEditDialog
      v-if="editing"
      :open="!!editing"
      :item="editing"
      :house-id="houseIdNum"
      :saving="savingEdit"
      @update:open="(v) => !v && (editing = null)"
      @save="handleSaveEdit"
    />

    <ChecklistItemViewDialog
      v-if="viewing"
      :open="!!viewing"
      :item="viewing"
      :category="categoryFor(viewing.categoryId)"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (viewing = null)"
      @edit="viewToEdit"
      @preview="openPreview"
    />

    <ChecklistImagePreview
      v-if="previewing"
      :open="!!previewing"
      :item="previewing"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (previewing = null)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import SortIcon from '@icons/Sort.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import PageToolbar from '@/components/PageToolbar'
import { ChecklistAddForm } from '@/components/ChecklistAddForm'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistItemEditDialog } from '@/components/ChecklistItemEditDialog'
import { ChecklistItemViewDialog } from '@/components/ChecklistItemViewDialog'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { checklistIconComponent } from '@/components/ChecklistIconPicker'
import { useChecklistItems } from '@/composables/useChecklist'
import { useCategories } from '@/composables/useCategories'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { getList } from '@/api/lists'
import type { ItemInput } from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'
import type { ChecklistItemSort } from '@/api/prefs'
import { getChecklistItemSort, setChecklistItemSort } from '@/api/prefs'

const props = defineProps<{ houseId: string; listId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const listIdNum = computed(() => Number(props.listId))

const list = ref<Checklist | null>(null)
const {
  items,
  loading,
  load,
  add,
  update,
  toggle,
  reorderItems,
  remove,
  uploadImage,
  clearImage,
  sortBy,
} = useChecklistItems(houseIdNum.value, listIdNum.value)
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

// ----- Sort -----

const currentSort = ref<ChecklistItemSort>('custom')

const itemSortOptions: { value: ChecklistItemSort; label: string }[] = [
  { value: 'newest', label: t('pantry', 'Newest first') },
  { value: 'oldest', label: t('pantry', 'Oldest first') },
  { value: 'name_asc', label: t('pantry', 'Name A\u2013Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z\u2013A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getChecklistItemSort(houseIdNum.value)
  currentSort.value = prefs.sort
  sortBy.value = prefs.sort
}

async function changeSort(value: ChecklistItemSort) {
  currentSort.value = value
  sortBy.value = value
  await setChecklistItemSort(houseIdNum.value, value)
  await load(value)
}

// ----- Loading -----

async function loadList() {
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

onMounted(async () => {
  await loadSortPref()
  await Promise.all([loadList(), load(), categories.load()])
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    await loadSortPref()
    await Promise.all([loadList(), load()])
  },
)

// ----- Partitioned items -----

function sortWithinPartition(arr: ChecklistItem[]): ChecklistItem[] {
  if (currentSort.value === 'custom') {
    return [...arr].sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
  }
  return arr
}

const isCustomSort = computed(() => currentSort.value === 'custom')
const uncheckedItems = computed(() => sortWithinPartition(items.value.filter((i) => !i.done)))
const checkedItems = computed(() => sortWithinPartition(items.value.filter((i) => i.done)))

// ----- Drag/drop reorder (unchecked partition only, custom sort) -----

type ListGridItem =
  | { type: 'item'; key: string; item: ChecklistItem }
  | { type: 'placeholder'; key: string }

const draggingItemId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const uncheckedListRef = ref<HTMLElement | null>(null)

const uncheckedGridItems = computed<ListGridItem[]>(() => {
  const source = uncheckedItems.value
  const dragId = draggingItemId.value
  if (!isCustomSort.value || dragId === null || dropIndex.value === null) {
    return source.map((i) => ({ type: 'item' as const, key: 'i-' + i.id, item: i }))
  }
  const without = source.filter((i) => i.id !== dragId)
  const items: ListGridItem[] = without.map((i) => ({
    type: 'item' as const,
    key: 'i-' + i.id,
    item: i,
  }))
  const clamped = Math.min(dropIndex.value, items.length)
  items.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return items
})

function onItemDragStart(itemId: number) {
  draggingItemId.value = itemId
  dropIndex.value = null
}

function computeItemDropIndex(hoveredItemId: number, clientY: number, target: HTMLElement | null) {
  const dragId = draggingItemId.value
  if (!dragId || dragId === hoveredItemId) return

  const without = uncheckedItems.value.filter((i) => i.id !== dragId)
  const idx = without.findIndex((i) => i.id === hoveredItemId)
  if (idx === -1) return

  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientY > rect.top + rect.height / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onReorderOver(hoveredItemId: number, e: MouseEvent) {
  computeItemDropIndex(hoveredItemId, e.clientY, e.currentTarget as HTMLElement | null)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingItemId.value
  const idx = dropIndex.value
  draggingItemId.value = null
  dropIndex.value = null

  if (dragId === null || idx === null) return

  const source = uncheckedItems.value
  const dragged = source.find((i) => i.id === dragId)
  if (!dragged) return

  const without = source.filter((i) => i.id !== dragId)
  const clamped = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragged)

  const entries = reordered.map((i, idx) => ({ id: i.id, sortOrder: idx }))
  await reorderItems(entries)
}

// Capture-phase listeners
function onDropCapture() {
  commitReorder()
}
function onDragEndCapture() {
  draggingItemId.value = null
  dropIndex.value = null
}
onMounted(() => {
  uncheckedListRef.value?.addEventListener('drop', onDropCapture, true)
  uncheckedListRef.value?.addEventListener('dragend', onDragEndCapture, true)
})
onBeforeUnmount(() => {
  uncheckedListRef.value?.removeEventListener('drop', onDropCapture, true)
  uncheckedListRef.value?.removeEventListener('dragend', onDragEndCapture, true)
})

// Touch reorder
useTouchReorder(
  uncheckedListRef,
  {
    onDragStart: onItemDragStart,
    onReorderOver(hoveredId, _clientX, clientY) {
      const el = uncheckedListRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`)
      computeItemDropIndex(hoveredId, clientY, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingItemId.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

// ----- Add -----

const adding = ref(false)

async function handleAdd(input: ItemInput) {
  adding.value = true
  try {
    await add(input)
  } finally {
    adding.value = false
  }
}

// ----- Toggle / Remove -----

async function handleToggle(itemId: number) {
  await toggle(itemId)
}

async function handleRemove(itemId: number) {
  await remove(itemId)
}

// ----- Edit -----

const editing = ref<ChecklistItem | null>(null)
const savingEdit = ref(false)

function startEdit(item: ChecklistItem) {
  editing.value = item
}

async function handleSaveEdit(
  itemId: number,
  patch: Partial<ItemInput>,
  pendingImage: File | null,
  shouldClearImage: boolean,
) {
  savingEdit.value = true
  try {
    await update(itemId, patch)
    if (pendingImage) {
      await uploadImage(itemId, pendingImage)
    } else if (shouldClearImage) {
      await clearImage(itemId)
    }
    editing.value = null
  } finally {
    savingEdit.value = false
  }
}

// ----- View / Preview -----

const viewing = ref<ChecklistItem | null>(null)
const previewing = ref<ChecklistItem | null>(null)

function openView(item: ChecklistItem) {
  viewing.value = item
}

function viewToEdit(item: ChecklistItem) {
  viewing.value = null
  startEdit(item)
}

function openPreview(item: ChecklistItem) {
  previewing.value = item
}

const strings = {
  back: t('pantry', 'Back to lists'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
  sortLabel: t('pantry', 'Sort order'),
  doneTitle: t('pantry', 'Done'),
}
</script>

<style scoped lang="scss">
.pantry-detail {
  &__body {
    max-width: 900px;
    margin: 0 auto;
  }

  &__center {
    display: flex;
    justify-content: center;
    padding: 2rem;
  }

  &__items {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  &__placeholder {
    min-height: 48px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
  }

  &__section-title {
    margin: 1.5rem 0 0.5rem;
    padding: 0 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
