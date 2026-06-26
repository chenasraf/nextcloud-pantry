<template>
  <div class="pantry-detail">
    <PageToolbar :title="isMeta ? strings.allListsTitle : list?.name">
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
        <span v-if="isMeta" class="pantry-detail__title-icon pantry-detail__title-icon--meta">
          <ViewListIcon :size="20" />
        </span>
        <span v-else-if="list" class="pantry-detail__title-icon" :style="iconWrapStyle(list.color)">
          <component :is="checklistIconComponent(list.icon)" :size="20" />
        </span>
      </template>
      <template #actions>
        <NcActions :aria-label="strings.sortLabel" :title="strings.sortLabel" type="tertiary">
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
        <NcButton
          v-if="!isMeta"
          :variant="trashMode ? 'primary' : 'tertiary'"
          :aria-label="strings.trashLabel"
          :title="strings.trashLabel"
          :aria-pressed="trashMode"
          @click="toggleTrash"
        >
          <template #icon>
            <TrashCanIcon :size="20" />
          </template>
          {{ strings.trashLabel }}
        </NcButton>
        <NcButton variant="primary" @click="showCategoryManager = true">
          <template #icon>
            <TagIcon :size="20" />
          </template>
          {{ strings.manageCategories }}
        </NcButton>
        <NcActions v-if="!isMeta" :aria-label="strings.moreActions" :title="strings.moreActions">
          <template #icon>
            <DotsHorizontalIcon :size="20" />
          </template>
          <NcActionButton @click="showExport = true">
            <template #icon>
              <FileExportIcon :size="20" />
            </template>
            {{ strings.exportMarkdown }}
          </NcActionButton>
          <NcActionButton @click="showImport = true">
            <template #icon>
              <FileImportIcon :size="20" />
            </template>
            {{ strings.importMarkdown }}
          </NcActionButton>
        </NcActions>
      </template>
    </PageToolbar>

    <div class="pantry-detail__body">
      <ChecklistAddForm
        :house-id="houseIdNum"
        :adding="adding"
        :delete-on-done-default="list?.deleteOnDoneDefault ?? false"
        :require-list-selector="isMeta"
        :available-lists="isMeta ? allLists : []"
        @add="handleAdd"
        @update:delete-on-done-default="handleDeleteOnDoneDefaultChange"
      />

      <ChecklistFilter
        v-if="items.length > 0"
        v-model:query="filterQuery"
        v-model:selected-category-ids="filterCategoryIds"
        v-model:selected-list-ids="filterListIds"
        :items="items"
        :categories="categories.items.value"
        :lists="isMeta ? allLists : undefined"
        class="pantry-detail__filter"
      />

      <div v-if="loading" class="pantry-detail__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="trashMode ? strings.trashEmptyTitle : strings.emptyTitle"
        :description="trashMode ? strings.trashEmptyBody : strings.emptyBody"
      >
        <template #icon>
          <TrashCanIcon v-if="trashMode" />
          <span
            v-else
            class="pantry-detail__empty-icon"
            :style="iconWrapStyle(list?.color ?? null)"
          >
            <component :is="checklistIconComponent(list?.icon)" :size="32" />
          </span>
        </template>
      </NcEmptyContent>

      <template v-else>
        <div v-if="trashMode" class="pantry-detail__trash-bar">
          <NcButton variant="error" @click="confirmingEmptyTrash = true">
            <template #icon>
              <TrashCanIcon :size="20" />
            </template>
            {{ strings.emptyTrashAction }}
          </NcButton>
        </div>
        <ul v-if="uncheckedItems.length > 0" ref="uncheckedListRef" class="pantry-detail__items">
          <template v-for="gi in uncheckedGridItems" :key="gi.key">
            <li
              v-if="gi.type === 'placeholder'"
              class="pantry-detail__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <li
              v-else-if="gi.type === 'separator'"
              :class="[
                'pantry-detail__category-separator',
                `pantry-detail__category-separator--${categorySpacing}`,
              ]"
              aria-hidden="true"
            />
            <ChecklistItemRow
              v-else
              :item="gi.item"
              :category="categoryFor(gi.item.categoryId)"
              :list="isMeta ? listFor(gi.item.listId) : null"
              :house-id="houseIdNum"
              :reorder-enabled="isCustomSort"
              :trash-mode="trashMode"
              :tap-row-to-complete="tapRowToComplete"
              :show-added-by="showAddedBy"
              @toggle="handleToggle"
              @view="openView"
              @edit="startEdit"
              @move="startMoveItem"
              @copy="startCopyItem"
              @remove="handleRemove"
              @restore="handleRestore"
              @preview="openPreview"
              @drag-start="onItemDragStart"
              @reorder-over="onReorderOver"
            />
          </template>
        </ul>
        <template v-if="checkedItems.length > 0">
          <h3 class="pantry-detail__section-title">{{ strings.doneTitle }}</h3>
          <ul ref="checkedListRef" class="pantry-detail__items pantry-detail__items--done">
            <template v-for="gi in checkedGridItems" :key="gi.key">
              <li
                v-if="gi.type === 'placeholder'"
                class="pantry-detail__placeholder"
                @dragover.prevent
                @drop.prevent.stop="onPlaceholderDrop"
              />
              <li
                v-else-if="gi.type === 'separator'"
                :class="[
                  'pantry-detail__category-separator',
                  `pantry-detail__category-separator--${categorySpacing}`,
                ]"
                aria-hidden="true"
              />
              <ChecklistItemRow
                v-else
                :item="gi.item"
                :category="categoryFor(gi.item.categoryId)"
                :list="isMeta ? listFor(gi.item.listId) : null"
                :house-id="houseIdNum"
                :reorder-enabled="isCustomSort"
                :tap-row-to-complete="tapRowToComplete"
                :show-added-by="showAddedBy"
                @toggle="handleToggle"
                @view="openView"
                @edit="startEdit"
                @move="startMoveItem"
                @copy="startCopyItem"
                @remove="handleRemove"
                @preview="openPreview"
                @drag-start="onItemDragStart"
                @reorder-over="onReorderOver"
              />
            </template>
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

    <CategoryManagerDialog
      :open="showCategoryManager"
      :house-id="houseIdNum"
      @update:open="showCategoryManager = $event"
      @sort-changed="onCategorySortChanged"
    />

    <!-- Move item to another list -->
    <NcDialog
      v-if="movingItem"
      :name="strings.moveToList"
      :open="!!movingItem"
      close-on-click-outside
      @update:open="(v) => !v && (movingItem = null)"
    >
      <div class="pantry-move-list">
        <NcButton v-for="cl in otherLists" :key="cl.id" wide @click="submitMoveItem(cl.id)">
          <template #icon>
            <component :is="checklistIconComponent(cl.icon)" :size="20" />
          </template>
          {{ cl.name }}
        </NcButton>
        <NcButton wide @click="createListForMove">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </div>
    </NcDialog>

    <ChecklistFormDialog
      :open="showCreateForMove"
      @update:open="showCreateForMove = $event"
      @save="submitCreateListAndMove"
    />

    <!-- Copy item to another list -->
    <NcDialog
      v-if="copyingItem"
      :name="strings.copyToList"
      :open="!!copyingItem"
      close-on-click-outside
      @update:open="(v) => !v && (copyingItem = null)"
    >
      <div class="pantry-move-list">
        <NcButton v-for="cl in copyTargetLists" :key="cl.id" wide @click="submitCopyItem(cl.id)">
          <template #icon>
            <component :is="checklistIconComponent(cl.icon)" :size="20" />
          </template>
          {{ cl.name }}
        </NcButton>
        <NcButton wide @click="createListForCopy">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </div>
    </NcDialog>

    <ChecklistFormDialog
      :open="showCreateForCopy"
      @update:open="showCreateForCopy = $event"
      @save="submitCreateListAndCopy"
    />

    <NcDialog
      v-if="confirmingEmptyTrash"
      :name="strings.emptyTrashTitle"
      :open="confirmingEmptyTrash"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingEmptyTrash = false)"
    >
      <p>{{ strings.emptyTrashConfirm }}</p>
      <template #actions>
        <NcButton @click="confirmingEmptyTrash = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitEmptyTrash">
          {{ strings.emptyTrashAction }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="currentReuse"
      :name="strings.reuseTitle"
      :open="!!currentReuse"
      close-on-click-outside
      @update:open="(v) => !v && resolveCurrentReuse('cancel')"
    >
      <p>{{ reusePrompt }}</p>
      <template #actions>
        <NcButton @click="resolveCurrentReuse('cancel')">{{ strings.cancel }}</NcButton>
        <NcButton @click="resolveCurrentReuse('add')">{{ strings.reuseAddAnyway }}</NcButton>
        <NcButton variant="primary" @click="resolveCurrentReuse('reuse')">
          {{ strings.reuseAction }}
        </NcButton>
      </template>
    </NcDialog>

    <MarkdownExportDialog
      v-model:open="showExport"
      :list-name="list?.name ?? ''"
      :items="items"
      :category-for="categoryFor"
    />

    <MarkdownImportDialog
      v-model:open="showImport"
      :house-id="houseIdNum"
      :importing="importing"
      :reuse-pref="reuseExistingItems"
      @import="handleImportItems"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { showUndo, showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PlusIcon from '@icons/Plus.vue'
import SortIcon from '@icons/Sort.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import TagIcon from '@icons/Tag.vue'
import TrashCanIcon from '@icons/TrashCan.vue'
import ViewListIcon from '@icons/ViewList.vue'
import DotsHorizontalIcon from '@icons/DotsHorizontal.vue'
import FileExportIcon from '@icons/FileExport.vue'
import FileImportIcon from '@icons/FileImport.vue'
import PageToolbar from '@/components/PageToolbar'
import { ChecklistAddForm } from '@/components/ChecklistAddForm'
import { ChecklistFilter } from '@/components/ChecklistFilter'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistItemEditDialog } from '@/components/ChecklistItemEditDialog'
import { ChecklistItemViewDialog } from '@/components/ChecklistItemViewDialog'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { CategoryManagerDialog } from '@/components/CategoryManager'
import { MarkdownExportDialog } from '@/components/MarkdownExportDialog'
import { MarkdownImportDialog } from '@/components/MarkdownImportDialog'
import {
  checklistIconComponent,
  ChecklistFormDialog,
  contrastColor,
} from '@/components/ChecklistIconPicker'

function iconWrapStyle(color: string | null) {
  if (!color) return undefined
  return { background: color, color: contrastColor(color) }
}
import { useChecklists, useChecklistItems, ALL_LISTS_ID } from '@/composables/useChecklist'
import { useCategories } from '@/composables/useCategories'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { getList, updateList as apiUpdateList } from '@/api/lists'
import type { ItemInput } from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'
import type { ChecklistItemSort, ReuseExistingItems } from '@/api/prefs'
import { getChecklistItemSort, setChecklistItemSort } from '@/api/prefs'
import { useTapRowToComplete } from '@/composables/useTapRowToComplete'
import { useCategorySpacing } from '@/composables/useCategorySpacing'
import { useShowAddedBy } from '@/composables/useShowAddedBy'
import { useReuseExistingItems } from '@/composables/useReuseExistingItems'

const props = defineProps<{ houseId: string; listId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const isMeta = computed(() => props.listId === 'all')
const listIdNum = computed(() => (isMeta.value ? ALL_LISTS_ID : Number(props.listId)))

const list = ref<Checklist | null>(null)
const {
  items,
  loading,
  load,
  add,
  update,
  copy,
  toggle,
  undoToggle,
  reorderItems,
  remove,
  undoRemove,
  removePermanently,
  restore,
  emptyTrash,
  uploadImage,
  clearImage,
  sortBy,
  trashMode,
} = useChecklistItems(houseIdNum.value, listIdNum.value)

async function toggleTrash() {
  trashMode.value = !trashMode.value
  await load()
}

const confirmingEmptyTrash = ref(false)

async function submitEmptyTrash() {
  confirmingEmptyTrash.value = false
  await emptyTrash()
}
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

function listFor(id: number) {
  return allLists.value.find((l) => l.id === id) ?? null
}

// ----- Markdown import / export -----

const showExport = ref(false)
const showImport = ref(false)
const importing = ref(false)

async function handleImportItems(inputs: ItemInput[], forceReuse: boolean) {
  // Close the import dialog first so the reuse-existing-items prompts (when the
  // pref is set to "ask") render cleanly over the list instead of stacking on
  // top of this dialog.
  showImport.value = false
  importing.value = true
  // When the user ticked "Reuse existing items" in the dialog, force reuse for
  // this import regardless of the global pref ("ask"/"never").
  const modeOverride = forceReuse ? 'reuse' : undefined
  try {
    // Route each item through the normal add path so imports honor the
    // reuse-existing-items pref: reuse silently, prompt per duplicate, or add.
    // Sequential awaiting also resolves any "ask" prompts one at a time and
    // dedupes names that repeat within the imported batch itself.
    for (const input of inputs) {
      await handleAdd(input, null, null, modeOverride)
    }
    showSuccess(n('pantry', 'Imported %n item', 'Imported %n items', inputs.length))
  } catch (e) {
    showError((e as Error).message)
  } finally {
    importing.value = false
  }
}

// ----- Sort -----

const currentSort = ref<ChecklistItemSort>('custom')

const allItemSortOptions: { value: ChecklistItemSort; label: string }[] = [
  { value: 'newest', label: t('pantry', 'Newest first') },
  { value: 'oldest', label: t('pantry', 'Oldest first') },
  { value: 'name_asc', label: t('pantry', 'Name A\u2013Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z\u2013A') },
  { value: 'category', label: t('pantry', 'Category') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

// Custom sort is per-list, so it's hidden in the meta "All lists" view.
const itemSortOptions = computed(() =>
  isMeta.value ? allItemSortOptions.filter((o) => o.value !== 'custom') : allItemSortOptions,
)

async function loadSortPref() {
  const prefs = await getChecklistItemSort(houseIdNum.value)
  // Custom sort is per-list — meta view falls back to "newest" instead.
  const sort: ChecklistItemSort = isMeta.value && prefs.sort === 'custom' ? 'newest' : prefs.sort
  currentSort.value = sort
  sortBy.value = sort
}

async function changeSort(value: ChecklistItemSort) {
  currentSort.value = value
  sortBy.value = value
  await setChecklistItemSort(houseIdNum.value, value)
  await load(value)
}

// ----- Loading -----

async function loadList() {
  if (isMeta.value) {
    list.value = null
    return
  }
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

const { tapRowToComplete } = useTapRowToComplete()
const { categorySpacing } = useCategorySpacing()
const showAddedBy = computed(() => useShowAddedBy(houseIdNum.value).showAddedBy.value)
const showCategorySeparators = computed(
  () => currentSort.value === 'category' && categorySpacing.value !== 'disabled',
)

onMounted(async () => {
  await loadSortPref()
  // Meta view needs the full list catalog to render per-item chips and the
  // required list picker; non-meta views read it lazily for move/copy dialogs,
  // by which time the sidebar has already populated the shared state.
  const tasks: Promise<unknown>[] = [loadList(), load(), categories.load()]
  if (isMeta.value) tasks.push(loadLists())
  await Promise.all(tasks)
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    filterListIds.value = loadListFilter()
    await loadSortPref()
    const tasks: Promise<unknown>[] = [loadList(), load()]
    if (isMeta.value) tasks.push(loadLists())
    await Promise.all(tasks)
  },
)

// ----- Silent polling while window is focused -----
//
// Refresh items every 30 s so multi-device edits show up without the user
// reloading. Pause when the window loses focus; on refocus, fire an immediate
// refresh and resume the interval.

const POLL_INTERVAL_MS = 30_000
let pollTimer: number | null = null

function silentRefresh() {
  void load(undefined, { silent: true })
}

function startPolling() {
  stopPolling()
  pollTimer = window.setInterval(silentRefresh, POLL_INTERVAL_MS)
}

function stopPolling() {
  if (pollTimer !== null) {
    window.clearInterval(pollTimer)
    pollTimer = null
  }
}

function onWindowFocus() {
  silentRefresh()
  startPolling()
}

function onWindowBlur() {
  stopPolling()
}

onMounted(() => {
  window.addEventListener('focus', onWindowFocus)
  window.addEventListener('blur', onWindowBlur)
  if (document.hasFocus()) startPolling()
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', onWindowFocus)
  window.removeEventListener('blur', onWindowBlur)
  stopPolling()
})

// ----- Filter -----

const filterQuery = ref('')
const filterCategoryIds = ref<number[]>([])

// List filter is only meaningful in the meta "All lists" view. Its selection is
// persisted per house in localStorage so it survives navigation and reloads.
const listFilterStorageKey = computed(() => `pantry:list-filter:${props.houseId}`)
const filterListIds = ref<number[]>(loadListFilter())

function loadListFilter(): number[] {
  try {
    const raw = window.localStorage.getItem(`pantry:list-filter:${props.houseId}`)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.filter((v): v is number => typeof v === 'number') : []
  } catch {
    return []
  }
}

watch(
  filterListIds,
  (ids) => {
    try {
      window.localStorage.setItem(listFilterStorageKey.value, JSON.stringify(ids))
    } catch {
      // Ignore storage failures (e.g. private mode quota).
    }
  },
  { deep: true },
)

const filteredItems = computed(() => {
  let result = items.value
  if (isMeta.value && filterListIds.value.length > 0) {
    const listIds = filterListIds.value
    result = result.filter((i) => listIds.includes(i.listId))
  }
  const catIds = filterCategoryIds.value
  if (catIds.length > 0) {
    result = result.filter((i) => i.categoryId != null && catIds.includes(i.categoryId))
  }
  const q = filterQuery.value.trim().toLowerCase()
  if (q) {
    result = result.filter(
      (i) =>
        i.name.toLowerCase().includes(q) ||
        (i.description && i.description.toLowerCase().includes(q)),
    )
  }
  return result
})

// ----- Partitioned items -----

function sortWithinPartition(arr: ChecklistItem[]): ChecklistItem[] {
  if (currentSort.value === 'custom') {
    return [...arr].sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
  }
  return arr
}

const isCustomSort = computed(() => currentSort.value === 'custom')
const uncheckedItems = computed(() =>
  sortWithinPartition(filteredItems.value.filter((i) => !i.done)),
)
const checkedItems = computed(() => sortWithinPartition(filteredItems.value.filter((i) => i.done)))

// ----- Drag/drop reorder (custom sort, per partition) -----

type ListGridItem =
  | { type: 'item'; key: string; item: ChecklistItem }
  | { type: 'placeholder'; key: string }
  | { type: 'separator'; key: string }

type Partition = 'unchecked' | 'checked'

const draggingItemId = ref<number | null>(null)
const draggingPartition = ref<Partition | null>(null)
const dropIndex = ref<number | null>(null)
const uncheckedListRef = ref<HTMLElement | null>(null)
const checkedListRef = ref<HTMLElement | null>(null)

function partitionItems(p: Partition): ChecklistItem[] {
  return p === 'unchecked' ? uncheckedItems.value : checkedItems.value
}

function withCategorySeparators(items: ListGridItem[]): ListGridItem[] {
  if (!showCategorySeparators.value) return items
  const out: ListGridItem[] = []
  let prevCategoryId: number | null | undefined = undefined
  for (const gi of items) {
    if (gi.type !== 'item') {
      out.push(gi)
      continue
    }
    const catId = gi.item.categoryId ?? null
    if (prevCategoryId !== undefined && prevCategoryId !== catId) {
      out.push({ type: 'separator', key: `sep-${gi.key}` })
    }
    prevCategoryId = catId
    out.push(gi)
  }
  return out
}

function buildGridItems(p: Partition): ListGridItem[] {
  const source = partitionItems(p)
  const dragId = draggingItemId.value
  if (
    !isCustomSort.value ||
    dragId === null ||
    dropIndex.value === null ||
    draggingPartition.value !== p
  ) {
    return withCategorySeparators(
      source.map((i) => ({ type: 'item' as const, key: 'i-' + i.id, item: i })),
    )
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
}

const uncheckedGridItems = computed<ListGridItem[]>(() => buildGridItems('unchecked'))
const checkedGridItems = computed<ListGridItem[]>(() => buildGridItems('checked'))

function findPartitionOf(itemId: number): Partition | null {
  if (uncheckedItems.value.some((i) => i.id === itemId)) return 'unchecked'
  if (checkedItems.value.some((i) => i.id === itemId)) return 'checked'
  return null
}

function onItemDragStart(itemId: number) {
  draggingItemId.value = itemId
  draggingPartition.value = findPartitionOf(itemId)
  dropIndex.value = null
}

function computeItemDropIndex(hoveredItemId: number, clientY: number, target: HTMLElement | null) {
  const dragId = draggingItemId.value
  if (!dragId || dragId === hoveredItemId) return

  const partition = draggingPartition.value
  if (!partition) return

  // Only allow reordering within the same partition.
  const source = partitionItems(partition)
  const without = source.filter((i) => i.id !== dragId)
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
  const partition = draggingPartition.value
  draggingItemId.value = null
  draggingPartition.value = null
  dropIndex.value = null

  if (dragId === null || idx === null || !partition) return

  // Reorder within the dragged partition, then merge with the other partition
  // (preserving its relative order) so the API receives a complete sort order
  // for all items in the list.
  const source = partitionItems(partition)
  const dragged = source.find((i) => i.id === dragId)
  if (!dragged) return

  const without = source.filter((i) => i.id !== dragId)
  const clamped = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragged)

  const otherPartition: Partition = partition === 'unchecked' ? 'checked' : 'unchecked'
  const other = partitionItems(otherPartition)

  // Unchecked items always come first in the sortOrder sequence.
  const finalOrder = partition === 'unchecked' ? [...reordered, ...other] : [...other, ...reordered]

  const entries = finalOrder.map((i, n) => ({ id: i.id, sortOrder: n }))
  await reorderItems(entries)
}

// Capture-phase listeners — attached to both partition lists.
function onDropCapture() {
  commitReorder()
}
function onDragEndCapture() {
  draggingItemId.value = null
  draggingPartition.value = null
  dropIndex.value = null
}
function bindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.addEventListener('drop', onDropCapture, true)
  el.addEventListener('dragend', onDragEndCapture, true)
}
function unbindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.removeEventListener('drop', onDropCapture, true)
  el.removeEventListener('dragend', onDragEndCapture, true)
}
onMounted(() => {
  bindDragListeners(uncheckedListRef.value)
  bindDragListeners(checkedListRef.value)
})
onBeforeUnmount(() => {
  unbindDragListeners(uncheckedListRef.value)
  unbindDragListeners(checkedListRef.value)
})

// Touch reorder — one composable instance per partition list.
useTouchReorder(
  uncheckedListRef,
  {
    onDragStart: onItemDragStart,
    onReorderOver(hoveredId, _clientX, clientY) {
      const el =
        uncheckedListRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeItemDropIndex(hoveredId, clientY, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingItemId.value = null
      draggingPartition.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

useTouchReorder(
  checkedListRef,
  {
    onDragStart: onItemDragStart,
    onReorderOver(hoveredId, _clientX, clientY) {
      const el =
        checkedListRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeItemDropIndex(hoveredId, clientY, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingItemId.value = null
      draggingPartition.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

// ----- Add -----

const adding = ref(false)

const { reuseExistingItems } = useReuseExistingItems()

// ----- Reuse existing items -----
//
// When adding an item whose normalized name already matches one in the same
// list, the user's "reuse existing items" pref decides what happens: reuse
// (uncheck the existing item, add nothing), ask (prompt per duplicate), or
// never (fall through to a normal add). Bulk add fires handleAdd concurrently,
// so "ask" prompts are queued and resolved one at a time.

function normalizeName(name: string): string {
  return name.trim().toLowerCase()
}

function findExistingItem(
  name: string,
  useListId: number | null | undefined,
): ChecklistItem | null {
  if (useListId == null || useListId <= 0) return null
  const norm = normalizeName(name)
  if (!norm) return null
  return items.value.find((i) => i.listId === useListId && normalizeName(i.name) === norm) ?? null
}

async function reuseItem(existing: ChecklistItem) {
  // "Reusing" means surfacing the existing item as active again — only the done
  // ones need an uncheck; an already-active match is left untouched.
  if (existing.done) {
    await toggle(existing.id)
  }
  showSuccess(t('pantry', 'Reused existing item "{name}"', { name: existing.name }))
}

type ReuseDecision = 'reuse' | 'add' | 'cancel'

interface ReuseRequest {
  existing: ChecklistItem
  resolve: (decision: ReuseDecision) => void
}

const reuseQueue = ref<ReuseRequest[]>([])
const currentReuse = computed(() => reuseQueue.value[0] ?? null)
const reusePrompt = computed(() =>
  currentReuse.value
    ? t(
        'pantry',
        'An item named "{name}" already exists in this list. Reuse it instead of adding a new one?',
        { name: currentReuse.value.existing.name },
      )
    : '',
)

function askReuse(existing: ChecklistItem): Promise<ReuseDecision> {
  return new Promise((resolve) => {
    reuseQueue.value = [...reuseQueue.value, { existing, resolve }]
  })
}

function resolveCurrentReuse(decision: ReuseDecision) {
  const req = reuseQueue.value[0]
  if (!req) return
  reuseQueue.value = reuseQueue.value.slice(1)
  req.resolve(decision)
}

async function handleAdd(
  input: ItemInput,
  pendingImage: File | null,
  targetListId: number | null,
  modeOverride?: ReuseExistingItems,
) {
  const mode = modeOverride ?? reuseExistingItems.value
  if (mode !== 'never' && !trashMode.value) {
    const useListId = isMeta.value ? targetListId : listIdNum.value
    const existing = findExistingItem(input.name, useListId)
    if (existing) {
      if (mode === 'reuse') {
        await reuseItem(existing)
        return
      }
      const decision = await askReuse(existing)
      if (decision === 'cancel') return
      if (decision === 'reuse') {
        await reuseItem(existing)
        return
      }
      // 'add' falls through to a normal add below.
    }
  }
  adding.value = true
  try {
    const created = await add(input, targetListId ?? undefined)
    if (pendingImage) {
      try {
        await uploadImage(created.id, pendingImage)
      } catch (e) {
        showError((e as Error).message)
      }
    }
  } finally {
    adding.value = false
  }
}

async function handleDeleteOnDoneDefaultChange(value: boolean) {
  if (!list.value || list.value.deleteOnDoneDefault === value) return
  const prev = list.value
  list.value = { ...prev, deleteOnDoneDefault: value }
  try {
    const updated = await apiUpdateList(houseIdNum.value, listIdNum.value, {
      deleteOnDoneDefault: value,
    })
    list.value = updated
  } catch (e) {
    list.value = prev
    showError((e as Error).message)
  }
}

// ----- Toggle / Remove -----

async function handleToggle(itemId: number) {
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await toggle(itemId)
  // Only offer undo when an item is marked done (undone → done).
  if (snapshot.done) return
  showUndo(
    strings.itemMarkedDone,
    () => {
      void undoToggle(snapshot).catch(() => {
        showError(strings.restoreFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleRemove(itemId: number) {
  if (trashMode.value) {
    await removePermanently(itemId)
    return
  }
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await remove(itemId)
  showUndo(
    strings.itemRemoved,
    () => {
      void undoRemove(snapshot).catch(() => {
        showError(strings.restoreFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleRestore(itemId: number) {
  await restore(itemId)
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

// ----- Category manager -----

const showCategoryManager = ref(false)

async function onCategorySortChanged() {
  // The list endpoint groups items by category, so changing category order
  // requires a re-fetch to reflect the new grouping.
  if (currentSort.value === 'category') {
    await load()
  }
}

// ----- Move item to another list -----

const { lists: allLists, create: createList, load: loadLists } = useChecklists(houseIdNum.value)

// Drop any persisted list-filter ids that no longer correspond to an existing
// list (e.g. a list was deleted) once the catalog has loaded.
watch(allLists, (lists) => {
  if (!isMeta.value || lists.length === 0 || filterListIds.value.length === 0) return
  const valid = new Set(lists.map((l) => l.id))
  const pruned = filterListIds.value.filter((id) => valid.has(id))
  if (pruned.length !== filterListIds.value.length) filterListIds.value = pruned
})
// In meta view, exclude the item's own current list (per movingItem); in a
// regular view, exclude the current list.
const otherLists = computed(() => {
  const excludeId = isMeta.value ? (movingItem.value?.listId ?? null) : listIdNum.value
  return excludeId === null ? allLists.value : allLists.value.filter((l) => l.id !== excludeId)
})
const movingItem = ref<ChecklistItem | null>(null)
const showCreateForMove = ref(false)

function startMoveItem(item: ChecklistItem) {
  movingItem.value = item
}

async function submitMoveItem(targetListId: number) {
  if (!movingItem.value) return
  const itemName = movingItem.value.name
  const targetList = allLists.value.find((l) => l.id === targetListId)
  await update(movingItem.value.id, { targetListId })
  // In a normal list view the item leaves the view after move. In the meta
  // "All lists" view it stays — just under a different list.
  if (!isMeta.value) {
    items.value = items.value.filter((i) => i.id !== movingItem.value!.id)
  }
  movingItem.value = null
  showSuccess(
    t('pantry', '{item} moved to {list}', { item: itemName, list: targetList?.name ?? '' }),
  )
}

function createListForMove() {
  showCreateForMove.value = true
}

async function submitCreateListAndMove(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const newList = await createList(
    data.name,
    data.description || null,
    data.icon || null,
    data.color || null,
  )
  showCreateForMove.value = false
  await submitMoveItem(newList.id)
}

// ----- Copy item to another list -----

const copyingItem = ref<ChecklistItem | null>(null)
const showCreateForCopy = ref(false)
// Copy can target the current list (creates a duplicate in place) as well as
// any other list — unlike move, where the current list would be a no-op.
const copyTargetLists = computed(() => allLists.value)

function startCopyItem(item: ChecklistItem) {
  copyingItem.value = item
}

async function submitCopyItem(targetListId: number) {
  if (!copyingItem.value) return
  const itemName = copyingItem.value.name
  const targetList = allLists.value.find((l) => l.id === targetListId)
  await copy(copyingItem.value.id, targetListId)
  copyingItem.value = null
  showSuccess(
    t('pantry', '{item} copied to {list}', { item: itemName, list: targetList?.name ?? '' }),
  )
}

function createListForCopy() {
  showCreateForCopy.value = true
}

async function submitCreateListAndCopy(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const newList = await createList(
    data.name,
    data.description || null,
    data.icon || null,
    data.color || null,
  )
  showCreateForCopy.value = false
  await submitCopyItem(newList.id)
}

const strings = {
  back: t('pantry', 'Back to lists'),
  allListsTitle: t('pantry', 'All lists'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
  trashEmptyTitle: t('pantry', 'Trash is empty'),
  trashEmptyBody: t('pantry', 'Deleted items will appear here.'),
  sortLabel: t('pantry', 'Sort order'),
  trashLabel: t('pantry', 'Trash'),
  doneTitle: t('pantry', 'Done'),
  manageCategories: t('pantry', 'Manage categories'),
  moreActions: t('pantry', 'More actions'),
  exportMarkdown: t('pantry', 'Export to Markdown'),
  importMarkdown: t('pantry', 'Import from Markdown'),
  moveToList: t('pantry', 'Move to list'),
  copyToList: t('pantry', 'Copy to list'),
  newList: t('pantry', 'New list'),
  emptyTrashAction: t('pantry', 'Empty trash'),
  emptyTrashTitle: t('pantry', 'Empty trash?'),
  emptyTrashConfirm: t(
    'pantry',
    'All deleted items in this list will be permanently removed. This cannot be undone.',
  ),
  cancel: t('pantry', 'Cancel'),
  itemMarkedDone: t('pantry', 'Item marked as done'),
  itemRemoved: t('pantry', 'Item moved to trash'),
  restoreFailed: t('pantry', 'Failed to restore item.'),
  reuseTitle: t('pantry', 'Item already exists'),
  reuseAction: t('pantry', 'Reuse existing'),
  reuseAddAnyway: t('pantry', 'Add anyway'),
}
</script>

<style scoped lang="scss">
.pantry-detail {
  &__title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);

    &--meta {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }
  }

  &__empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);
  }

  &__body {
    max-width: 900px;
    margin: 0 auto;
  }

  &__filter {
    margin-top: 1rem;
    margin-bottom: 1.5rem;
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

  &__trash-bar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.75rem;
  }

  &__placeholder {
    min-height: 48px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
  }

  &__category-separator {
    list-style: none;
    padding: 0;
    margin: 0;

    &--divider {
      border-top: 1px solid var(--color-border);
      margin-top: 1rem;
      padding-top: 1rem;
    }

    &--spacing {
      height: 0.75rem;
    }
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

.pantry-move-list {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.5rem 0;
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
