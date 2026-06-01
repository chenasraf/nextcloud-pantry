<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="!catLoading && catItems.length > 0" class="pantry-cat-toolbar">
      <NcActions :aria-label="strings.sortLabel" :title="strings.sortLabel" type="tertiary">
        <template #icon>
          <SortIcon :size="20" />
        </template>
        <NcActionButton
          v-for="opt in sortOptions"
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
    </div>

    <div v-if="catLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p v-if="catItems.length === 0" class="pantry-cat-hint">
        {{ strings.noCategoriesHint }}
      </p>
      <ul v-else ref="listRef" class="pantry-cat-list">
        <template v-for="gi in gridItems" :key="gi.key">
          <li
            v-if="gi.type === 'placeholder'"
            class="pantry-cat-list__placeholder"
            @dragover.prevent
            @drop.prevent.stop="onPlaceholderDrop"
          />
          <li
            v-else
            :class="[
              'pantry-cat-list__item',
              { 'pantry-cat-list__item--dragging': draggingId === gi.cat.id },
            ]"
            :data-drag-id="gi.cat.id"
            :draggable="isCustomSort ? 'true' : 'false'"
            @dragstart="onDragStart($event, gi.cat.id)"
            @dragend="onDragEnd"
            @dragover.prevent="onDragOver($event, gi.cat.id)"
            @drop.prevent.stop="commitReorder"
          >
            <span
              v-if="isCustomSort"
              class="pantry-cat-list__handle"
              :aria-label="strings.dragHandle"
              :title="strings.dragHandle"
            >
              <DragVerticalIcon :size="20" />
            </span>
            <span class="pantry-cat-list__icon" :style="{ color: gi.cat.color }">
              <component :is="categoryIconComponent(gi.cat.icon)" :size="20" />
            </span>
            <span class="pantry-cat-list__name">{{ gi.cat.name }}</span>
            <div class="pantry-cat-list__actions">
              <NcButton
                variant="tertiary"
                :aria-label="strings.editCategory"
                @click="startEditCat(gi.cat)"
              >
                <template #icon><PencilIcon :size="18" /></template>
              </NcButton>
              <NcButton
                variant="tertiary"
                :aria-label="strings.deleteCategory"
                @click="confirmDeleteCat(gi.cat)"
              >
                <template #icon><DeleteIcon :size="18" /></template>
              </NcButton>
            </div>
          </li>
        </template>
      </ul>
    </template>
    <template #actions>
      <NcButton variant="primary" @click="openCreateCat">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.newCategory }}
      </NcButton>
    </template>
  </NcDialog>

  <!-- Create/edit form -->
  <CategoryFormDialog
    :open="showForm"
    :category="editingCat"
    :saving="catSaving"
    :error="catError"
    @update:open="closeForm"
    @save="submitForm"
  />

  <!-- Delete category confirm -->
  <NcDialog
    v-if="deletingCat"
    :name="strings.deleteCategoryTitle"
    :open="!!deletingCat"
    close-on-click-outside
    @update:open="(v) => !v && (deletingCat = null)"
  >
    <p>{{ deleteCatConfirmBody }}</p>
    <template #actions>
      <NcButton @click="deletingCat = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="submitDeleteCat">{{ strings.delete }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import PencilIcon from '@icons/Pencil.vue'
import SortIcon from '@icons/Sort.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import DragVerticalIcon from '@icons/DragVertical.vue'
import type { Category } from '@/api/types'
import type { CategorySort } from '@/api/prefs'
import { getCategorySort, setCategorySort } from '@/api/prefs'
import { useCategories } from '@/composables/useCategories'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import CategoryFormDialog from './CategoryFormDialog.vue'

const props = defineProps<{ open: boolean; houseId: number }>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  'sort-changed': []
}>()

const categories = useCategories(props.houseId)
const catItems = computed(() => categories.items.value)
const catLoading = computed(() => categories.loading.value)

const currentSort = ref<CategorySort>('name_asc')
const isCustomSort = computed(() => currentSort.value === 'custom')

const sortOptions: { value: CategorySort; label: string }[] = [
  { value: 'name_asc', label: t('pantry', 'Name A–Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z–A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getCategorySort(props.houseId)
  currentSort.value = prefs.sort
  categories.setSortBy(prefs.sort)
}

const sortDirty = ref(false)

async function changeSort(value: CategorySort) {
  if (value === currentSort.value) return
  currentSort.value = value
  categories.setSortBy(value)
  sortDirty.value = true
  await setCategorySort(props.houseId, value)
}

watch(
  () => props.open,
  async (isOpen, wasOpen) => {
    if (isOpen) {
      sortDirty.value = false
      await loadSortPref()
      await categories.load()
    } else if (wasOpen && sortDirty.value) {
      sortDirty.value = false
      emit('sort-changed')
    }
  },
  { immediate: true },
)

// -------- Drag & drop reorder --------

type ListGridItem =
  | { type: 'cat'; key: string; cat: Category }
  | { type: 'placeholder'; key: string }

const draggingId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const listRef = ref<HTMLElement | null>(null)

const gridItems = computed<ListGridItem[]>(() => {
  const source = catItems.value
  if (!isCustomSort.value || draggingId.value === null || dropIndex.value === null) {
    return source.map((c) => ({ type: 'cat' as const, key: 'c-' + c.id, cat: c }))
  }
  const dragId = draggingId.value
  const without = source.filter((c) => c.id !== dragId)
  const out: ListGridItem[] = without.map((c) => ({
    type: 'cat' as const,
    key: 'c-' + c.id,
    cat: c,
  }))
  const clamped = Math.min(dropIndex.value, out.length)
  out.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return out
})

function onDragStart(e: DragEvent, id: number) {
  if (!isCustomSort.value || !e.dataTransfer) return
  draggingId.value = id
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  // Some browsers refuse to start a drag without data — set anything.
  e.dataTransfer.setData('text/plain', String(id))
}

function onDragEnd() {
  draggingId.value = null
  dropIndex.value = null
}

function computeDropIndex(hoveredId: number, clientY: number, target: HTMLElement | null) {
  const dragId = draggingId.value
  if (!dragId || dragId === hoveredId) return
  const without = catItems.value.filter((c) => c.id !== dragId)
  const idx = without.findIndex((c) => c.id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientY > rect.top + rect.height / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onDragOver(e: DragEvent, hoveredId: number) {
  computeDropIndex(hoveredId, e.clientY, e.currentTarget as HTMLElement | null)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingId.value
  const idx = dropIndex.value
  draggingId.value = null
  dropIndex.value = null
  if (dragId === null || idx === null) return

  const source = catItems.value
  const dragged = source.find((c) => c.id === dragId)
  if (!dragged) return

  const without = source.filter((c) => c.id !== dragId)
  const clamped = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragged)
  const entries = reordered.map((c, n) => ({ id: c.id, sortOrder: n }))
  sortDirty.value = true
  await categories.reorder(entries)
}

function bindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.addEventListener('dragend', onDragEnd, true)
}
function unbindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.removeEventListener('dragend', onDragEnd, true)
}

watch(listRef, (newEl, oldEl) => {
  unbindDragListeners(oldEl ?? null)
  bindDragListeners(newEl ?? null)
})
onBeforeUnmount(() => {
  unbindDragListeners(listRef.value)
})

useTouchReorder(
  listRef,
  {
    onDragStart: (id) => {
      draggingId.value = id
      dropIndex.value = null
    },
    onReorderOver(hoveredId, _clientX, clientY) {
      const el = listRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeDropIndex(hoveredId, clientY, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingId.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

// -------- Form state --------
const showForm = ref(false)
const editingCat = ref<Category | null>(null)
const deletingCat = ref<Category | null>(null)
const catSaving = ref(false)
const catError = ref<string | null>(null)

function openCreateCat() {
  editingCat.value = null
  catError.value = null
  showForm.value = true
}

function startEditCat(cat: Category) {
  editingCat.value = cat
  catError.value = null
  showForm.value = true
}

function closeForm(v: boolean) {
  if (!v) {
    showForm.value = false
    editingCat.value = null
  }
}

function confirmDeleteCat(cat: Category) {
  deletingCat.value = cat
}

const deleteCatConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the category "{name}"?', {
    name: deletingCat.value?.name ?? '',
  }),
)

async function submitForm(data: { name: string; icon: string; color: string }) {
  catSaving.value = true
  catError.value = null
  try {
    if (editingCat.value) {
      await categories.update(editingCat.value.id, data)
    } else {
      await categories.create(data)
    }
    showForm.value = false
    editingCat.value = null
  } catch (e) {
    catError.value =
      (e as Error).message ||
      (editingCat.value
        ? t('pantry', 'Could not update category.')
        : t('pantry', 'Could not create category.'))
  } finally {
    catSaving.value = false
  }
}

async function submitDeleteCat() {
  const target = deletingCat.value
  if (!target) return
  await categories.remove(target.id)
  deletingCat.value = null
}

const strings = {
  title: t('pantry', 'Manage categories'),
  noCategoriesHint: t('pantry', 'No categories yet. Categories help organize checklist items.'),
  newCategory: t('pantry', 'New category'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  editCategory: t('pantry', 'Edit'),
  deleteCategory: t('pantry', 'Delete'),
  deleteCategoryTitle: t('pantry', 'Delete category'),
  sortLabel: t('pantry', 'Sort order'),
  dragHandle: t('pantry', 'Drag to reorder'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-cat-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.25rem;
}

.pantry-cat-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-cat-list {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem 0;

  &__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border);

    &--dragging {
      opacity: 0.4;
    }
  }

  &__handle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    cursor: grab;
    color: var(--color-text-maxcontrast);
    touch-action: none;
  }

  &__icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__actions {
    display: flex;
    gap: 0;
    flex-shrink: 0;
  }

  &__placeholder {
    min-height: 40px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
    margin: 4px 0;
  }
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
