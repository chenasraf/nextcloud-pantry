<template>
  <div class="pantry-detail">
    <PageToolbar :title="isMeta ? strings.allListsTitle : list?.name" :actions="toolbarActions">
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
    </PageToolbar>

    <div class="pantry-detail__body">
      <div v-if="selectionMode" class="pantry-detail__selection-bar">
        <NcButton
          variant="tertiary"
          :aria-label="strings.exitSelection"
          :title="strings.exitSelection"
          @click="exitSelection"
        >
          <template #icon>
            <CloseIcon :size="20" />
          </template>
        </NcButton>
        <span class="pantry-detail__selection-count">{{ selectionCountLabel }}</span>
        <NcButton variant="tertiary" :disabled="allVisibleSelected" @click="selectAllVisible">
          {{ strings.selectAll }}
        </NcButton>
        <NcButton v-if="selectedCount > 0" variant="tertiary" @click="clearSelection">
          {{ strings.clearSelection }}
        </NcButton>
        <span class="pantry-detail__selection-spacer" />
        <NcButton
          variant="tertiary"
          :disabled="!canBulkMove"
          :aria-label="strings.moveSelected"
          :title="strings.moveSelected"
          @click="openBulkMove"
        >
          <template #icon>
            <ArrowRightIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCopy"
          :aria-label="strings.copySelected"
          :title="strings.copySelected"
          @click="openBulkCopy"
        >
          <template #icon>
            <ContentCopyIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCategory"
          :aria-label="strings.assignCategory"
          :title="strings.assignCategory"
          @click="openBulkCategory"
        >
          <template #icon>
            <TagIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCategory"
          :aria-label="strings.assignStores"
          :title="strings.assignStores"
          @click="openBulkStores"
        >
          <template #icon>
            <StoreOutlineIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          v-if="!trashMode && !archiveMode"
          variant="tertiary"
          :disabled="!canBulkArchive"
          :aria-label="strings.archiveSelected"
          :title="strings.archiveSelected"
          @click="bulkArchive"
        >
          <template #icon>
            <ArchiveArrowDownOutlineIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          v-if="archiveMode"
          variant="tertiary"
          :disabled="!canBulkArchive"
          :aria-label="strings.unarchiveSelected"
          :title="strings.unarchiveSelected"
          @click="bulkUnarchive"
        >
          <template #icon>
            <ArchiveArrowUpOutlineIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkDelete"
          :aria-label="strings.deleteSelected"
          :title="strings.deleteSelected"
          @click="bulkDelete"
        >
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
        </NcButton>
      </div>

      <ChecklistAddForm
        v-if="
          !selectionMode &&
          can.canAddItems &&
          !trashMode &&
          !archiveMode &&
          (isMeta || writableHere)
        "
        ref="addForm"
        :house-id="houseIdNum"
        :adding="adding"
        :delete-on-done-default="list?.deleteOnDoneDefault ?? false"
        :require-list-selector="isMeta"
        :available-lists="isMeta ? allLists : []"
        :reuse-candidates="reuseCandidates"
        :current-list-id="isMeta ? null : listIdNum"
        @add="handleAdd"
        @update:delete-on-done-default="handleDeleteOnDoneDefaultChange"
        @reuse-existing="onReuseFromSuggestion"
      />

      <ChecklistFilter
        v-if="items.length > 0"
        v-model:query="filterQuery"
        v-model:selected-category-ids="filterCategoryIds"
        v-model:selected-store-ids="filterStoreIds"
        v-model:selected-list-ids="filterListIds"
        :items="items"
        :categories="categories.items.value"
        :stores="stores.items.value"
        :lists="isMeta ? allLists : undefined"
        class="pantry-detail__filter"
      />

      <div v-if="loading" class="pantry-detail__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="emptyStateTitle"
        :description="emptyStateBody"
      >
        <template #icon>
          <TrashCanIcon v-if="trashMode" />
          <ArchiveOutlineIcon v-else-if="archiveMode" />
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
              v-else-if="gi.type === 'header'"
              class="pantry-detail__category-header"
              :style="gi.category ? { color: gi.category.color } : undefined"
            >
              <component :is="categoryIconComponent(gi.category?.icon)" :size="18" />
              <span class="pantry-detail__category-header-name">
                {{ gi.category?.name ?? strings.noCategory }}
              </span>
            </li>
            <ChecklistItemRow
              v-else
              :item="gi.item"
              :category="categoryFor(gi.item.categoryId)"
              :stores="storesFor(gi.item.storeIds)"
              :hide-category="showCategoryHeaders"
              :list="isMeta ? listFor(gi.item.listId) : null"
              :list-writable="isMeta ? listWritable(listFor(gi.item.listId)) : writableHere"
              :house-id="houseIdNum"
              :reorder-enabled="
                reorderActive && (isMeta ? listWritable(listFor(gi.item.listId)) : writableHere)
              "
              :trash-mode="trashMode"
              :archive-mode="archiveMode"
              :tap-row-to-complete="tapRowToComplete"
              :show-added-by="showAddedBy"
              :selection-mode="selectionMode"
              :selected="selectedIds.has(gi.item.id)"
              @toggle="handleToggle"
              @toggle-select="toggleSelect"
              @view="openView"
              @edit="startEdit"
              @move="startMoveItem"
              @copy="startCopyItem"
              @remove="handleRemove"
              @restore="handleRestore"
              @archive="handleArchive"
              @unarchive="handleUnarchive"
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
                v-else-if="gi.type === 'header'"
                class="pantry-detail__category-header"
                :style="gi.category ? { color: gi.category.color } : undefined"
              >
                <component :is="categoryIconComponent(gi.category?.icon)" :size="18" />
                <span class="pantry-detail__category-header-name">
                  {{ gi.category?.name ?? strings.noCategory }}
                </span>
              </li>
              <ChecklistItemRow
                v-else
                :item="gi.item"
                :category="categoryFor(gi.item.categoryId)"
                :stores="storesFor(gi.item.storeIds)"
                :hide-category="showCategoryHeaders"
                :list="isMeta ? listFor(gi.item.listId) : null"
                :list-writable="isMeta ? listWritable(listFor(gi.item.listId)) : writableHere"
                :house-id="houseIdNum"
                :reorder-enabled="
                  reorderActive && (isMeta ? listWritable(listFor(gi.item.listId)) : writableHere)
                "
                :trash-mode="trashMode"
                :archive-mode="archiveMode"
                :tap-row-to-complete="tapRowToComplete"
                :show-added-by="showAddedBy"
                :selection-mode="selectionMode"
                :selected="selectedIds.has(gi.item.id)"
                @toggle="handleToggle"
                @toggle-select="toggleSelect"
                @view="openView"
                @edit="startEdit"
                @move="startMoveItem"
                @copy="startCopyItem"
                @remove="handleRemove"
                @restore="handleRestore"
                @archive="handleArchive"
                @unarchive="handleUnarchive"
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
      :stores="storesFor(viewing.storeIds)"
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

    <ChecklistFormDialog
      v-if="list"
      :open="editingList"
      :list="list"
      @update:open="(v) => (editingList = v)"
      @save="submitEditList"
    />

    <CategoryManagerDialog
      :open="showCategoryManager"
      :house-id="houseIdNum"
      @update:open="showCategoryManager = $event"
      @sort-changed="onCategorySortChanged"
    />

    <StoreManagerDialog
      :open="showStoreManager"
      :house-id="houseIdNum"
      @update:open="showStoreManager = $event"
    />

    <!-- Move item(s) to another list -->
    <NcDialog
      v-if="moveDialogOpen"
      :name="strings.moveToList"
      :open="moveDialogOpen"
      close-on-click-outside
      @update:open="(v) => !v && closeMoveDialog()"
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

    <!-- Copy item(s) to another list -->
    <NcDialog
      v-if="copyDialogOpen"
      :name="strings.copyToList"
      :open="copyDialogOpen"
      close-on-click-outside
      @update:open="(v) => !v && closeCopyDialog()"
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

    <!-- Assign category to selected items -->
    <NcDialog
      v-if="showBulkCategory"
      :name="strings.assignCategoryTitle"
      :open="showBulkCategory"
      close-on-click-outside
      @update:open="(v) => !v && (showBulkCategory = false)"
    >
      <div class="pantry-bulk-category">
        <CategoryPicker v-model="bulkCategoryId" :house-id="houseIdNum" />
      </div>
      <template #actions>
        <NcButton @click="showBulkCategory = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="tertiary" @click="applyBulkCategory(null)">
          {{ strings.removeCategory }}
        </NcButton>
        <NcButton
          variant="primary"
          :disabled="bulkCategoryId == null"
          @click="applyBulkCategory(bulkCategoryId)"
        >
          {{ strings.apply }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Assign stores to selected items -->
    <NcDialog
      v-if="showBulkStores"
      :name="strings.assignStoresTitle"
      :open="showBulkStores"
      close-on-click-outside
      @update:open="(v) => !v && (showBulkStores = false)"
    >
      <div class="pantry-bulk-category">
        <StoreMultiPicker v-model="bulkStoreIds" :house-id="houseIdNum" />
      </div>
      <template #actions>
        <NcButton @click="showBulkStores = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="tertiary" @click="applyBulkStores([])">
          {{ strings.removeStores }}
        </NcButton>
        <NcButton variant="primary" @click="applyBulkStores(bulkStoreIds)">
          {{ strings.apply }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Permanently delete selected items (trash view) -->
    <NcDialog
      v-if="confirmingBulkDelete"
      :name="strings.bulkDeleteTitle"
      :open="confirmingBulkDelete"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingBulkDelete = false)"
    >
      <p>{{ strings.bulkDeleteConfirm }}</p>
      <template #actions>
        <NcButton @click="confirmingBulkDelete = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitBulkDeletePermanent">
          {{ strings.deleteSelected }}
        </NcButton>
      </template>
    </NcDialog>

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

    <NcDialog
      v-if="reuseConfirm"
      :name="strings.reuseTitle"
      :open="!!reuseConfirm"
      close-on-click-outside
      @update:open="(v) => !v && resolveReuseConfirm(false)"
    >
      <p>{{ reuseConfirmPrompt }}</p>
      <template #actions>
        <NcButton @click="resolveReuseConfirm(false)">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" @click="resolveReuseConfirm(true)">
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
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import ArrowRightIcon from '@icons/ArrowRight.vue'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import DeleteIcon from '@icons/Delete.vue'
import PlusIcon from '@icons/Plus.vue'
import SortIcon from '@icons/Sort.vue'
import SelectMultipleIcon from '@icons/SelectMultiple.vue'
import CloseIcon from '@icons/Close.vue'
import TagIcon from '@icons/Tag.vue'
import StoreOutlineIcon from '@icons/StoreOutline.vue'
import TrashCanIcon from '@icons/TrashCan.vue'
import ArchiveOutlineIcon from '@icons/ArchiveOutline.vue'
import ArchiveArrowDownOutlineIcon from '@icons/ArchiveArrowDownOutline.vue'
import ArchiveArrowUpOutlineIcon from '@icons/ArchiveArrowUpOutline.vue'
import ViewListIcon from '@icons/ViewList.vue'
import PencilIcon from '@icons/Pencil.vue'
import FileExportIcon from '@icons/FileExport.vue'
import FileImportIcon from '@icons/FileImport.vue'
import PageToolbar, { type ToolbarAction } from '@/components/PageToolbar'
import { ChecklistAddForm } from '@/components/ChecklistAddForm'
import { ChecklistFilter, NO_CATEGORY_ID, NO_STORE_ID } from '@/components/ChecklistFilter'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistItemEditDialog } from '@/components/ChecklistItemEditDialog'
import { ChecklistItemViewDialog } from '@/components/ChecklistItemViewDialog'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { CategoryManagerDialog } from '@/components/CategoryManager'
import { StoreManagerDialog } from '@/components/StoreManager'
import { MarkdownExportDialog } from '@/components/MarkdownExportDialog'
import { MarkdownImportDialog } from '@/components/MarkdownImportDialog'
import CategoryPicker, { categoryIconComponent } from '@/components/CategoryPicker'
import StoreMultiPicker from '@/components/StoreMultiPicker'
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
import { useStores } from '@/composables/useStores'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { useLongPress } from '@/composables/useLongPress'
import { getList, updateList as apiUpdateList } from '@/api/lists'
import type { ItemInput } from '@/api/lists'
import type { Checklist, ChecklistItem, Category, Store } from '@/api/types'
import type { ChecklistItemSort, ReuseExistingItems } from '@/api/prefs'
import { getChecklistItemSort, setChecklistItemSort } from '@/api/prefs'
import { useTapRowToComplete } from '@/composables/useTapRowToComplete'
import { useShowAddedBy } from '@/composables/useShowAddedBy'
import { useReuseExistingItems } from '@/composables/useReuseExistingItems'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

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
  archive,
  unarchive,
  undoArchive,
  uploadImage,
  clearImage,
  moveMany,
  copyMany,
  removeMany,
  removeManyPermanent,
  archiveMany,
  unarchiveMany,
  undoArchiveMany,
  setCategoryMany,
  setStoresMany,
  undoRemoveMany,
  sortBy,
  viewMode,
  trashMode,
  archiveMode,
} = useChecklistItems(houseIdNum.value, listIdNum.value)

async function toggleTrash() {
  exitSelection()
  viewMode.value = trashMode.value ? 'active' : 'trash'
  await load()
}

async function toggleArchive() {
  exitSelection()
  viewMode.value = archiveMode.value ? 'active' : 'archive'
  await load()
}

const emptyStateTitle = computed(() => {
  if (trashMode.value) return strings.trashEmptyTitle
  if (archiveMode.value) return strings.archiveEmptyTitle
  return strings.emptyTitle
})
const emptyStateBody = computed(() => {
  if (trashMode.value) return strings.trashEmptyBody
  if (archiveMode.value) return strings.archiveEmptyBody
  return strings.emptyBody
})

const confirmingEmptyTrash = ref(false)

async function submitEmptyTrash() {
  confirmingEmptyTrash.value = false
  await emptyTrash()
}
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

const stores = useStores(houseIdNum.value)

function storesFor(ids: number[] | null | undefined): Store[] {
  if (!ids || ids.length === 0) return []
  return ids.map((id) => stores.findById(id)).filter((s): s is Store => s != null)
}

function listFor(id: number) {
  return allLists.value.find((l) => l.id === id) ?? null
}

// A list reached only through a view-only share blocks every write affordance;
// role-based access (and editor shares) keep the granular capabilities.
function listWritable(l?: Checklist | null): boolean {
  return !(l?.sharedOnly && !l?.canEdit)
}

// Writability of the list currently in focus (single-list mode).
const writableHere = computed(() => listWritable(list.value))

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

const sortMenuName = computed(() => {
  const label = allItemSortOptions.find((o) => o.value === currentSort.value)?.label ?? ''
  return t('pantry', 'Sort by: {value}', { value: label })
})

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
const { can } = useCurrentHouse()
const showAddedBy = computed(() => useShowAddedBy(houseIdNum.value).showAddedBy.value)
// Sorting by category always groups items under category headers.
const showCategoryHeaders = computed(() => currentSort.value === 'category')

onMounted(async () => {
  await loadSortPref()
  // Meta view needs the full list catalog to render per-item chips and the
  // required list picker; non-meta views read it lazily for move/copy dialogs,
  // by which time the sidebar has already populated the shared state.
  const tasks: Promise<unknown>[] = [loadList(), load(), categories.load(), stores.load()]
  if (isMeta.value) tasks.push(loadLists())
  await Promise.all(tasks)
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    exitSelection()
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
const filterStoreIds = ref<number[]>([])

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
    const wantUncategorized = catIds.includes(NO_CATEGORY_ID)
    result = result.filter((i) =>
      i.categoryId != null ? catIds.includes(i.categoryId) : wantUncategorized,
    )
  }
  const storeIds = filterStoreIds.value
  if (storeIds.length > 0) {
    const wantStoreless = storeIds.includes(NO_STORE_ID)
    result = result.filter((i) => {
      const attached = i.storeIds ?? []
      if (attached.length === 0) return wantStoreless
      return attached.some((id) => storeIds.includes(id))
    })
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

// ----- Multi-select state -----
// Declared here (ahead of the drag/drop + long-press wiring that reads them) so
// reorder can stand down while a selection is active. The rest of the
// multi-select logic lives further down.
const selectionMode = ref(false)
const selectedIds = ref<Set<number>>(new Set())
const reorderActive = computed(() => isCustomSort.value && !selectionMode.value)

// ----- Drag/drop reorder (custom sort, per partition) -----

type ListGridItem =
  | { type: 'item'; key: string; item: ChecklistItem }
  | { type: 'placeholder'; key: string }
  | { type: 'header'; key: string; category: Category | null }

type Partition = 'unchecked' | 'checked'

const draggingItemId = ref<number | null>(null)
const draggingPartition = ref<Partition | null>(null)
const dropIndex = ref<number | null>(null)
const uncheckedListRef = ref<HTMLElement | null>(null)
const checkedListRef = ref<HTMLElement | null>(null)

function partitionItems(p: Partition): ChecklistItem[] {
  return p === 'unchecked' ? uncheckedItems.value : checkedItems.value
}

function withCategoryHeaders(items: ListGridItem[]): ListGridItem[] {
  if (!showCategoryHeaders.value) return items
  const out: ListGridItem[] = []
  let prevCategoryId: number | null | undefined = undefined
  for (const gi of items) {
    if (gi.type !== 'item') {
      out.push(gi)
      continue
    }
    const catId = gi.item.categoryId ?? null
    if (prevCategoryId === undefined || prevCategoryId !== catId) {
      out.push({ type: 'header', key: `hdr-${gi.key}`, category: categoryFor(catId) })
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
    return withCategoryHeaders(
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
// The partition lists are gated behind `v-if` (loading / empty state), so the
// refs are null at mount and only appear once items render. Rebind on change
// rather than once in onMounted, or desktop drops never reach commitReorder.
watch(uncheckedListRef, (el, prev) => {
  unbindDragListeners(prev ?? null)
  bindDragListeners(el)
})
watch(checkedListRef, (el, prev) => {
  unbindDragListeners(prev ?? null)
  bindDragListeners(el)
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
  reorderActive,
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
  reorderActive,
)

// Long-press to enter multi-select on touch — only when reorder's own
// long-press isn't in play (non-custom sort) and we're not already selecting.
const longPressActive = computed(() => !isCustomSort.value && !selectionMode.value)
function onRowLongPress(id: number) {
  enterSelection(id)
}
useLongPress(uncheckedListRef, onRowLongPress, longPressActive)
useLongPress(checkedListRef, onRowLongPress, longPressActive)

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

// ----- Live reuse suggestions -----
//
// The add form surfaces existing items on the target list that fuzzily match
// what's being typed. Tapping one asks for a plain confirm (Cancel / Reuse
// existing) — no "add anyway", since the user picked a specific item — then
// reuses it (unchecking if done) and clears the input.

const addForm = ref<{ clearName: () => void } | null>(null)

// Only active items, and only when the user can check items. No pref check:
// the panel is a manual discovery affordance, distinct from on-submit dedup.
const reuseCandidates = computed<ChecklistItem[]>(() =>
  can.value.canCheckItems ? items.value.filter((i) => !i.deletedAt) : [],
)

const reuseConfirm = ref<{ item: ChecklistItem; resolve: (ok: boolean) => void } | null>(null)
const reuseConfirmPrompt = computed(() =>
  reuseConfirm.value
    ? t(
        'pantry',
        'An item named "{name}" already exists in this list. Reuse it instead of adding a new one?',
        { name: reuseConfirm.value.item.name },
      )
    : '',
)

function confirmReuseSuggestion(item: ChecklistItem): Promise<boolean> {
  return new Promise((resolve) => {
    reuseConfirm.value = { item, resolve }
  })
}

function resolveReuseConfirm(ok: boolean) {
  const req = reuseConfirm.value
  if (!req) return
  reuseConfirm.value = null
  req.resolve(ok)
}

async function onReuseFromSuggestion(item: ChecklistItem) {
  const ok = await confirmReuseSuggestion(item)
  if (!ok) return
  await reuseItem(item)
  addForm.value?.clearName()
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

const editingList = ref(false)

async function submitEditList(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  if (!list.value) return
  try {
    list.value = await apiUpdateList(houseIdNum.value, listIdNum.value, {
      name: data.name,
      description: data.description,
      icon: data.icon,
      color: data.color || null,
    })
    editingList.value = false
  } catch (e) {
    showError((e as Error).message)
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
  if (trashMode.value || archiveMode.value) {
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

async function handleArchive(itemId: number) {
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await archive(itemId)
  showUndo(
    strings.itemArchived,
    () => {
      void undoArchive(snapshot).catch(() => {
        showError(strings.unarchiveFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleUnarchive(itemId: number) {
  await unarchive(itemId)
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
const showStoreManager = ref(false)

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
// regular view, exclude the current list. For a bulk move the source varies, so
// offer every list (meta) or every list but the current one (single-list view).
const otherLists = computed(() => {
  if (moveIsBulk.value) {
    return isMeta.value ? allLists.value : allLists.value.filter((l) => l.id !== listIdNum.value)
  }
  const excludeId = isMeta.value ? (movingItem.value?.listId ?? null) : listIdNum.value
  return excludeId === null ? allLists.value : allLists.value.filter((l) => l.id !== excludeId)
})
const movingItem = ref<ChecklistItem | null>(null)
const moveIsBulk = ref(false)
const showCreateForMove = ref(false)
const moveDialogOpen = computed(() => !!movingItem.value || moveIsBulk.value)

function startMoveItem(item: ChecklistItem) {
  moveIsBulk.value = false
  movingItem.value = item
}

function openBulkMove() {
  if (!canBulkMove.value) return
  moveIsBulk.value = true
}

function closeMoveDialog() {
  movingItem.value = null
  moveIsBulk.value = false
}

async function submitMoveItem(targetListId: number) {
  if (moveIsBulk.value) {
    await submitBulkMove(targetListId)
    return
  }
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

async function submitBulkMove(targetListId: number) {
  const ids = writableSelectedIds.value
  const targetList = allLists.value.find((l) => l.id === targetListId)
  const total = selectedCount.value
  closeMoveDialog()
  if (ids.length === 0) return
  try {
    const result = await moveMany(ids, targetListId)
    showSuccess(
      n('pantry', 'Moved %n item to {list}', 'Moved %n items to {list}', result.items.length, {
        list: targetList?.name ?? '',
      }),
    )
    reportSkipped(total - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    exitSelection()
  }
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
const copyIsBulk = ref(false)
const showCreateForCopy = ref(false)
const copyDialogOpen = computed(() => !!copyingItem.value || copyIsBulk.value)
// Copy can target the current list (creates a duplicate in place) as well as
// any other list — unlike move, where the current list would be a no-op.
const copyTargetLists = computed(() => allLists.value)

function startCopyItem(item: ChecklistItem) {
  copyIsBulk.value = false
  copyingItem.value = item
}

function openBulkCopy() {
  if (!canBulkCopy.value) return
  copyIsBulk.value = true
}

function closeCopyDialog() {
  copyingItem.value = null
  copyIsBulk.value = false
}

async function submitCopyItem(targetListId: number) {
  if (copyIsBulk.value) {
    await submitBulkCopy(targetListId)
    return
  }
  if (!copyingItem.value) return
  const itemName = copyingItem.value.name
  const targetList = allLists.value.find((l) => l.id === targetListId)
  await copy(copyingItem.value.id, targetListId)
  copyingItem.value = null
  showSuccess(
    t('pantry', '{item} copied to {list}', { item: itemName, list: targetList?.name ?? '' }),
  )
}

async function submitBulkCopy(targetListId: number) {
  // Copy operates on the whole selection (read-only sources are allowed).
  const ids = selectedItems.value.map((i) => i.id)
  const targetList = allLists.value.find((l) => l.id === targetListId)
  closeCopyDialog()
  if (ids.length === 0) return
  try {
    const result = await copyMany(ids, targetListId)
    showSuccess(
      n('pantry', 'Copied %n item to {list}', 'Copied %n items to {list}', result.items.length, {
        list: targetList?.name ?? '',
      }),
    )
    reportSkipped(result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    exitSelection()
  }
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

// ----- Multi-select (group actions) -----
// (selectionMode / selectedIds / reorderActive are declared earlier.)

const selectedItems = computed(() => items.value.filter((i) => selectedIds.value.has(i.id)))
const selectedCount = computed(() => selectedItems.value.length)
const selectionCountLabel = computed(() =>
  n('pantry', '%n selected', '%n selected', selectedCount.value),
)
const allVisibleSelected = computed(
  () =>
    filteredItems.value.length > 0 && filteredItems.value.every((i) => selectedIds.value.has(i.id)),
)

// Whether the current user can write to the list an item lives on (in the meta
// view lists vary per item; in a single-list view it's the list in focus).
function itemWritable(item: ChecklistItem): boolean {
  return isMeta.value ? listWritable(listFor(item.listId)) : writableHere.value
}

// Ids eligible for write actions (move / delete / assign category).
const writableSelectedIds = computed(() =>
  selectedItems.value.filter(itemWritable).map((i) => i.id),
)

const canBulkMove = computed(() => can.value.canMoveItems && writableSelectedIds.value.length > 0)
// Copy only reads the source, so read-only items are eligible too.
const canBulkCopy = computed(() => can.value.canCopyItems && selectedCount.value > 0)
const canBulkDelete = computed(
  () => can.value.canDeleteItems && writableSelectedIds.value.length > 0,
)
const canBulkCategory = computed(
  () => can.value.canEditLists && writableSelectedIds.value.length > 0,
)
// Archive/unarchive is gated by canEditLists (an organizing action).
const canBulkArchive = computed(
  () => can.value.canEditLists && writableSelectedIds.value.length > 0,
)
// Show the toolbar entry only when there's something to select and at least one
// group action is reachable for this user.
const canSelect = computed(
  () =>
    items.value.length > 0 &&
    (can.value.canMoveItems ||
      can.value.canCopyItems ||
      can.value.canDeleteItems ||
      can.value.canEditLists),
)

function enterSelection(seedId?: number) {
  selectionMode.value = true
  if (seedId !== undefined) {
    selectedIds.value = new Set([seedId])
  }
}

function exitSelection() {
  selectionMode.value = false
  selectedIds.value = new Set()
}

function toggleSelect(id: number) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  selectedIds.value = next
}

function selectAllVisible() {
  selectedIds.value = new Set(filteredItems.value.map((i) => i.id))
}

function clearSelection() {
  selectedIds.value = new Set()
}

// Drop ids that are no longer visible/present (filter change, silent poll,
// completed bulk op) so the selection and its count stay honest.
watch([filteredItems], () => {
  if (!selectionMode.value || selectedIds.value.size === 0) return
  const visible = new Set(filteredItems.value.map((i) => i.id))
  const pruned = new Set([...selectedIds.value].filter((id) => visible.has(id)))
  if (pruned.size !== selectedIds.value.size) selectedIds.value = pruned
})

function reportSkipped(skipped: number) {
  if (skipped > 0) {
    showError(
      n(
        'pantry',
        '%n item was skipped because you cannot edit it.',
        '%n items were skipped because you cannot edit them.',
        skipped,
      ),
    )
  }
}

// ----- Bulk assign category -----

const showBulkCategory = ref(false)
const bulkCategoryId = ref<number | null>(null)

function openBulkCategory() {
  bulkCategoryId.value = null
  showBulkCategory.value = true
}

// Apply a category to the writable selection, or clear it when categoryId is null.
async function applyBulkCategory(categoryId: number | null) {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await setCategoryMany(ids, categoryId)
    showSuccess(n('pantry', 'Updated %n item', 'Updated %n items', result.items.length))
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    showBulkCategory.value = false
    exitSelection()
  }
}

// ----- Bulk stores -----

const showBulkStores = ref(false)
const bulkStoreIds = ref<number[]>([])

function openBulkStores() {
  bulkStoreIds.value = []
  showBulkStores.value = true
}

// Replace the stores on the writable selection (empty array clears them).
async function applyBulkStores(storeIds: number[]) {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await setStoresMany(ids, storeIds)
    showSuccess(n('pantry', 'Updated %n item', 'Updated %n items', result.items.length))
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    showBulkStores.value = false
    exitSelection()
  }
}

// ----- Bulk delete -----

const confirmingBulkDelete = ref(false)

async function bulkDelete() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  if (trashMode.value || archiveMode.value) {
    confirmingBulkDelete.value = true
    return
  }
  const snapshots = selectedItems.value.filter((i) => itemWritable(i)).map((i) => ({ ...i }))
  try {
    const result = await removeMany(ids)
    const deleted = ids.length - result.skipped.length
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
    showUndo(
      n('pantry', 'Deleted %n item', 'Deleted %n items', deleted),
      () => {
        void undoRemoveMany(snapshots).catch(() => showError(strings.restoreFailed))
      },
      { timeout: 6000 },
    )
  } catch (e) {
    showError((e as Error).message)
  }
}

async function submitBulkDeletePermanent() {
  const ids = writableSelectedIds.value
  confirmingBulkDelete.value = false
  if (ids.length === 0) return
  try {
    const result = await removeManyPermanent(ids)
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
  } catch (e) {
    showError((e as Error).message)
  }
}

// ----- Bulk archive / unarchive -----

async function bulkArchive() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  const snapshots = selectedItems.value.filter((i) => itemWritable(i)).map((i) => ({ ...i }))
  try {
    const result = await archiveMany(ids)
    const archived = ids.length - result.skipped.length
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
    showUndo(
      n('pantry', 'Archived %n item', 'Archived %n items', archived),
      () => {
        void undoArchiveMany(snapshots).catch(() => showError(strings.unarchiveFailed))
      },
      { timeout: 6000 },
    )
  } catch (e) {
    showError((e as Error).message)
  }
}

async function bulkUnarchive() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await unarchiveMany(ids)
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
  } catch (e) {
    showError((e as Error).message)
  }
}

const strings = {
  back: t('pantry', 'Back to lists'),
  allListsTitle: t('pantry', 'All lists'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
  trashEmptyTitle: t('pantry', 'Trash is empty'),
  trashEmptyBody: t('pantry', 'Deleted items will appear here.'),
  archiveEmptyTitle: t('pantry', 'Archive is empty'),
  archiveEmptyBody: t('pantry', 'Archived items will appear here.'),
  sortLabel: t('pantry', 'Sort order'),
  trashLabel: t('pantry', 'Trash'),
  // TRANSLATORS: Noun. Toolbar toggle that opens the view of archived items (not the "archive" action).
  archiveLabel: t('pantry', 'Archive'),
  // TRANSLATORS: Section heading above the completed (checked-off) items.
  doneTitle: t('pantry', 'Done'),
  noCategory: t('pantry', 'No category'),
  manageCategories: t('pantry', 'Manage categories'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Toolbar action opening the store manager.
  manageStores: t('pantry', 'Manage stores'),
  editList: t('pantry', 'Edit list'),
  // TRANSLATORS: Verb. Toolbar button that exports the list as Markdown.
  exportMarkdown: t('pantry', 'Export'),
  // TRANSLATORS: Verb. Toolbar button that imports items from Markdown.
  importMarkdown: t('pantry', 'Import'),
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
  itemArchived: t('pantry', 'Item archived'),
  restoreFailed: t('pantry', 'Failed to restore item.'),
  unarchiveFailed: t('pantry', 'Failed to unarchive item.'),
  reuseTitle: t('pantry', 'Item already exists'),
  reuseAction: t('pantry', 'Reuse existing'),
  reuseAddAnyway: t('pantry', 'Add anyway'),
  // TRANSLATORS: Verb. Toolbar toggle that enters multi-select mode to act on several items at once.
  select: t('pantry', 'Select'),
  selectAll: t('pantry', 'Select all'),
  clearSelection: t('pantry', 'Clear selection'),
  exitSelection: t('pantry', 'Exit selection'),
  // TRANSLATORS: Verb. Button that moves the selected items to another list.
  moveSelected: t('pantry', 'Move'),
  // TRANSLATORS: Verb. Button that copies the selected items to another list.
  copySelected: t('pantry', 'Copy'),
  assignCategory: t('pantry', 'Assign category'),
  // TRANSLATORS: Verb. Button that sets which stores the selected items can be bought at.
  assignStores: t('pantry', 'Assign stores'),
  archiveSelected: t('pantry', 'Archive items'),
  unarchiveSelected: t('pantry', 'Unarchive items'),
  // TRANSLATORS: Verb. Button that deletes the selected items.
  deleteSelected: t('pantry', 'Delete items'),
  assignCategoryTitle: t('pantry', 'Assign category'),
  // TRANSLATORS: Verb. Dialog button that applies the chosen category to the selected items.
  apply: t('pantry', 'Apply'),
  removeCategory: t('pantry', 'Remove category'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Dialog title for bulk assignment.
  assignStoresTitle: t('pantry', 'Assign stores'),
  // TRANSLATORS: Verb. Dialog button that detaches all stores from the selected items.
  removeStores: t('pantry', 'Remove stores'),
  bulkDeleteTitle: t('pantry', 'Delete items?'),
  bulkDeleteConfirm: t(
    'pantry',
    'The selected items will be permanently removed. This cannot be undone.',
  ),
}

const toolbarActions = computed<ToolbarAction[]>(() => {
  const actions: ToolbarAction[] = [
    {
      key: 'sort',
      type: 'menu',
      label: sortMenuName.value,
      caption: strings.sortLabel,
      icon: SortIcon,
      priority: 5,
      options: itemSortOptions.value.map((opt) => ({
        key: opt.value,
        label: opt.label,
        active: currentSort.value === opt.value,
        onClick: () => changeSort(opt.value),
      })),
    },
  ]

  if (!isMeta.value) {
    if (list.value && (list.value.canEdit ?? can.value.canEditLists)) {
      actions.push({
        key: 'edit',
        label: strings.editList,
        icon: PencilIcon,
        priority: 4,
        onClick: () => (editingList.value = true),
      })
    }
    actions.push(
      {
        key: 'archive',
        label: strings.archiveLabel,
        icon: ArchiveOutlineIcon,
        variant: archiveMode.value ? 'primary' : 'tertiary',
        pressed: archiveMode.value,
        priority: 2,
        onClick: toggleArchive,
      },
      {
        key: 'trash',
        label: strings.trashLabel,
        icon: TrashCanIcon,
        variant: trashMode.value ? 'primary' : 'tertiary',
        pressed: trashMode.value,
        priority: 2,
        onClick: toggleTrash,
      },
      {
        key: 'export',
        label: strings.exportMarkdown,
        icon: FileExportIcon,
        priority: 1,
        onClick: () => (showExport.value = true),
      },
      {
        key: 'import',
        label: strings.importMarkdown,
        icon: FileImportIcon,
        priority: 1,
        onClick: () => (showImport.value = true),
      },
    )
  }

  actions.push({
    key: 'categories',
    label: strings.manageCategories,
    icon: TagIcon,
    priority: 6,
    onClick: () => (showCategoryManager.value = true),
  })

  actions.push({
    key: 'stores',
    label: strings.manageStores,
    icon: StoreOutlineIcon,
    priority: 6,
    onClick: () => (showStoreManager.value = true),
  })

  if (canSelect.value) {
    actions.push({
      key: 'select',
      label: strings.select,
      icon: SelectMultipleIcon,
      variant: selectionMode.value ? 'primary' : 'tertiary',
      pressed: selectionMode.value,
      priority: 3,
      onClick: () => (selectionMode.value ? exitSelection() : enterSelection()),
    })
  }

  return actions
})
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

  &__selection-bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.5rem;
    margin-bottom: 0.75rem;
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  }

  &__selection-count {
    font-weight: 600;
    white-space: nowrap;
  }

  &__selection-spacer {
    flex: 1;
  }

  &__placeholder {
    min-height: 48px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
  }

  &__category-header {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    // Stick each category header to the top of the scroll area so the group a
    // row belongs to stays visible while scrolling through it. An opaque
    // background and a z-index keep item rows from showing through as they
    // scroll underneath. Top margin is avoided on purpose — a sticky element's
    // transparent margin would leave a see-through strip above the stuck
    // header; the internal top padding provides the separation instead.
    position: sticky;
    top: 0;
    z-index: 2;
    margin: 0;
    padding: 0.6rem 0.25rem 0.4rem;
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
    font-weight: 600;
    // Falls back to the default text color for the "No category" header (no
    // inline color set); real categories set their own color inline.
    color: var(--color-text-maxcontrast);
  }

  &__category-header-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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

.pantry-bulk-category {
  padding: 0.5rem 0 1rem;
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
