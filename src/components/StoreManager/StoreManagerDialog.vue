<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="!storeLoading && storeItems.length > 0" class="pantry-store-toolbar">
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

    <div v-if="storeLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p v-if="storeItems.length === 0" class="pantry-store-hint">
        {{ strings.noStoresHint }}
      </p>
      <ul v-else ref="listRef" class="pantry-store-list">
        <template v-for="gi in gridItems" :key="gi.key">
          <li
            v-if="gi.type === 'placeholder'"
            class="pantry-store-list__placeholder"
            @dragover.prevent
            @drop.prevent.stop="onPlaceholderDrop"
          />
          <li
            v-else
            :class="[
              'pantry-store-list__item',
              { 'pantry-store-list__item--dragging': draggingId === gi.store.id },
            ]"
            :data-drag-id="gi.store.id"
            :draggable="isCustomSort ? 'true' : 'false'"
            @dragstart="onDragStart($event, gi.store.id)"
            @dragend="onDragEnd"
            @dragover.prevent="onDragOver($event, gi.store.id)"
            @drop.prevent.stop="commitReorder"
          >
            <span
              v-if="isCustomSort"
              class="pantry-store-list__handle"
              :aria-label="strings.dragHandle"
              :title="strings.dragHandle"
            >
              <DragVerticalIcon :size="20" />
            </span>
            <button type="button" class="pantry-store-list__main" @click="viewStore(gi.store)">
              <span class="pantry-store-list__icon" :style="{ color: gi.store.color }">
                <component :is="storeIconComponent(gi.store.icon)" :size="20" />
              </span>
              <span class="pantry-store-list__name">{{ gi.store.name }}</span>
            </button>
            <div class="pantry-store-list__actions">
              <NcButton
                variant="tertiary"
                :aria-label="strings.editStore"
                @click="startEdit(gi.store)"
              >
                <template #icon><PencilIcon :size="18" /></template>
              </NcButton>
              <NcButton
                variant="tertiary"
                :aria-label="strings.deleteStore"
                @click="confirmDelete(gi.store)"
              >
                <template #icon><DeleteIcon :size="18" /></template>
              </NcButton>
            </div>
          </li>
        </template>
      </ul>
    </template>
    <template #actions>
      <NcButton variant="primary" @click="openCreate">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.newStore }}
      </NcButton>
    </template>
  </NcDialog>

  <!-- Store details -->
  <StoreViewDialog
    v-if="viewingStore"
    :open="!!viewingStore"
    :store="viewingStore"
    @update:open="(v) => !v && (viewingStore = null)"
    @edit="editFromView"
  />

  <!-- Create/edit form -->
  <StoreFormDialog
    :open="showForm"
    :store="editingStore"
    :saving="storeSaving"
    :error="storeError"
    @update:open="closeForm"
    @save="submitForm"
  />

  <!-- Delete store confirm -->
  <NcDialog
    v-if="deletingStore"
    :name="strings.deleteStoreTitle"
    :open="!!deletingStore"
    close-on-click-outside
    @update:open="(v) => !v && (deletingStore = null)"
  >
    <p>{{ deleteConfirmBody }}</p>
    <template #actions>
      <NcButton @click="deletingStore = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="submitDelete">{{ strings.delete }}</NcButton>
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
import type { Store } from '@/api/types'
import type { StoreInput } from '@/api/stores'
import type { StoreSort } from '@/api/prefs'
import { getStoreSort, setStoreSort } from '@/api/prefs'
import { useStores } from '@/composables/useStores'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import StoreFormDialog from './StoreFormDialog.vue'
import StoreViewDialog from './StoreViewDialog.vue'

const props = defineProps<{
  open: boolean
  houseId: number
  /** When set while the dialog opens, jump straight into editing this store. */
  editStore?: Store | null
}>()
defineEmits<{
  'update:open': [value: boolean]
}>()

const stores = useStores(props.houseId)
const storeItems = computed(() => stores.items.value)
const storeLoading = computed(() => stores.loading.value)

const currentSort = ref<StoreSort>('name_asc')
const isCustomSort = computed(() => currentSort.value === 'custom')

const sortOptions: { value: StoreSort; label: string }[] = [
  { value: 'name_asc', label: t('pantry', 'Name A–Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z–A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getStoreSort(props.houseId)
  currentSort.value = prefs.sort
  stores.setSortBy(prefs.sort)
}

async function changeSort(value: StoreSort) {
  if (value === currentSort.value) return
  currentSort.value = value
  stores.setSortBy(value)
  await setStoreSort(props.houseId, value)
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      await loadSortPref()
      await stores.load()
    }
  },
  { immediate: true },
)

// Jump straight into the edit form when the parent opens us with a target store.
watch(
  [() => props.open, () => props.editStore],
  ([isOpen, store]) => {
    if (isOpen && store) {
      startEdit(store)
    }
  },
  { immediate: true },
)

// -------- Drag & drop reorder --------

type ListGridItem =
  { type: 'store'; key: string; store: Store } | { type: 'placeholder'; key: string }

const draggingId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const listRef = ref<HTMLElement | null>(null)

const gridItems = computed<ListGridItem[]>(() => {
  const source = storeItems.value
  if (!isCustomSort.value || draggingId.value === null || dropIndex.value === null) {
    return source.map((s) => ({ type: 'store' as const, key: 's-' + s.id, store: s }))
  }
  const dragId = draggingId.value
  const without = source.filter((s) => s.id !== dragId)
  const out: ListGridItem[] = without.map((s) => ({
    type: 'store' as const,
    key: 's-' + s.id,
    store: s,
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
  const without = storeItems.value.filter((s) => s.id !== dragId)
  const idx = without.findIndex((s) => s.id === hoveredId)
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

  const source = storeItems.value
  const dragged = source.find((s) => s.id === dragId)
  if (!dragged) return

  const without = source.filter((s) => s.id !== dragId)
  const clamped = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragged)
  const entries = reordered.map((s, n) => ({ id: s.id, sortOrder: n }))
  await stores.reorder(entries)
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
const editingStore = ref<Store | null>(null)
const viewingStore = ref<Store | null>(null)
const deletingStore = ref<Store | null>(null)
const storeSaving = ref(false)
const storeError = ref<string | null>(null)

function openCreate() {
  editingStore.value = null
  storeError.value = null
  showForm.value = true
}

function viewStore(store: Store) {
  viewingStore.value = store
}

function startEdit(store: Store) {
  editingStore.value = store
  storeError.value = null
  showForm.value = true
}

function editFromView(store: Store) {
  viewingStore.value = null
  startEdit(store)
}

function closeForm(v: boolean) {
  if (!v) {
    showForm.value = false
    editingStore.value = null
  }
}

function confirmDelete(store: Store) {
  deletingStore.value = store
}

const deleteConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the store "{name}"?', {
    name: deletingStore.value?.name ?? '',
  }),
)

async function submitForm(data: StoreInput) {
  storeSaving.value = true
  storeError.value = null
  try {
    if (editingStore.value) {
      await stores.update(editingStore.value.id, data)
    } else {
      await stores.create(data)
    }
    showForm.value = false
    editingStore.value = null
  } catch (e) {
    storeError.value =
      (e as Error).message ||
      (editingStore.value
        ? t('pantry', 'Could not update store.')
        : t('pantry', 'Could not create store.'))
  } finally {
    storeSaving.value = false
  }
}

async function submitDelete() {
  const target = deletingStore.value
  if (!target) return
  await stores.remove(target.id)
  deletingStore.value = null
}

const strings = {
  // TRANSLATORS: Noun (plural), shops where items are bought. Dialog title.
  title: t('pantry', 'Manage stores'),
  noStoresHint: t('pantry', 'No stores yet. Stores let you mark where each item can be bought.'),
  // TRANSLATORS: Noun, a shop where items are bought. Button label.
  newStore: t('pantry', 'New store'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  editStore: t('pantry', 'Edit'),
  deleteStore: t('pantry', 'Delete'),
  // TRANSLATORS: Noun, a shop where items are bought. Dialog title.
  deleteStoreTitle: t('pantry', 'Delete store'),
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

.pantry-store-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.25rem;
}

.pantry-store-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-store-list {
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

  &__main {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 6px 8px;
    margin: -6px 0;
    border: none;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: start;
    cursor: pointer;
    border-radius: var(--border-radius, 8px);

    &:hover {
      background: var(--color-background-hover);
    }
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
