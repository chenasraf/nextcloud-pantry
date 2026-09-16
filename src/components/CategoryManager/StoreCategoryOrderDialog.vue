<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <p class="pantry-store-order__hint">{{ strings.hint }}</p>

    <div v-if="storesLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <p v-else-if="storeItems.length === 0" class="pantry-store-order__hint">
      {{ strings.noStores }}
    </p>
    <template v-else>
      <NcSelect
        :model-value="selectedOption"
        :options="storeOptions"
        :clearable="false"
        :placeholder="strings.pickStore"
        :input-label="strings.storeLabel"
        label="label"
        :calculate-position="ncSelectCalculatePosition"
        @update:model-value="onPickStore"
      >
        <template #option="option">
          <div class="pantry-store-order__option">
            <span class="pantry-store-order__option-icon" :style="{ color: option.store?.color }">
              <component :is="storeIconComponent(option.store?.icon)" :size="18" />
            </span>
            <span>{{ option.label }}</span>
          </div>
        </template>
        <template #selected-option="option">
          <div class="pantry-store-order__option">
            <span class="pantry-store-order__option-icon" :style="{ color: option.store?.color }">
              <component :is="storeIconComponent(option.store?.icon)" :size="16" />
            </span>
            <span>{{ option.label }}</span>
          </div>
        </template>
      </NcSelect>

      <div v-if="loadingOrder" class="pantry-center">
        <NcLoadingIcon :size="28" />
      </div>
      <template v-else-if="selectedStoreId !== null">
        <p v-if="ordered.length === 0" class="pantry-store-order__hint">
          {{ strings.noCategories }}
        </p>
        <ul v-else ref="listRef" class="pantry-store-order__list">
          <template v-for="gi in gridItems" :key="gi.key">
            <li
              v-if="gi.type === 'placeholder'"
              class="pantry-store-order__placeholder"
              @dragover.prevent
              @drop.prevent.stop="commitReorder"
            />
            <li
              v-else
              :class="[
                'pantry-store-order__item',
                { 'pantry-store-order__item--dragging': draggingId === gi.cat.id },
              ]"
              :data-drag-id="gi.cat.id"
              draggable="true"
              @dragstart="onDragStart($event, gi.cat.id)"
              @dragend="onDragEnd"
              @dragover.prevent="onDragOver($event, gi.cat.id)"
              @drop.prevent.stop="commitReorder"
            >
              <span
                class="pantry-store-order__handle"
                :aria-label="strings.dragHandle"
                :title="strings.dragHandle"
              >
                <DragVerticalIcon :size="20" />
              </span>
              <span class="pantry-store-order__position">{{ gi.position }}</span>
              <span class="pantry-store-order__icon" :style="{ color: gi.cat.color }">
                <component :is="categoryIconComponent(gi.cat.icon)" :size="20" />
              </span>
              <span class="pantry-store-order__name">{{ gi.cat.name }}</span>
            </li>
          </template>
        </ul>
        <p v-if="error" class="pantry-store-order__error">{{ error }}</p>
      </template>
    </template>

    <template #actions>
      <NcButton
        v-if="selectedStoreId !== null && isArranged"
        variant="tertiary"
        :disabled="saving"
        @click="resetOrder"
      >
        <template #icon><RestoreIcon :size="20" /></template>
        {{ strings.reset }}
      </NcButton>
      <NcButton variant="primary" @click="$emit('update:open', false)">
        {{ strings.done }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import DragVerticalIcon from '@icons/DragVertical.vue'
import RestoreIcon from '@icons/Restore.vue'
import type { Category } from '@/api/types'
import {
  clearStoreCategoryOrder,
  getStoreCategoryOrder,
  setStoreCategoryOrder,
} from '@/api/categories'
import { useCategories } from '@/composables/useCategories'
import { useStores } from '@/composables/useStores'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'
import { orderCategoriesForStore } from '@/utils/storeCategoryOrder'

const props = defineProps<{ open: boolean; houseId: number }>()
defineEmits<{ 'update:open': [value: boolean] }>()

const categories = useCategories(props.houseId)
const stores = useStores(props.houseId)
const storeItems = computed(() => stores.items.value)
const storesLoading = computed(() => stores.loading.value)

const selectedStoreId = ref<number | null>(null)
const loadingOrder = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
// The store's own arrangement, as stored. Empty means the store follows the
// house-wide order; `ordered` still renders a full list, so this is what tells
// the two apart for the reset action.
const arrangedIds = ref<number[]>([])
const ordered = ref<Category[]>([])

const isArranged = computed(() => arrangedIds.value.length > 0)

interface StoreOption {
  id: number
  label: string
  store: { icon: string; color: string }
}
const storeOptions = computed<StoreOption[]>(() =>
  storeItems.value.map((s) => ({
    id: s.id,
    label: s.name,
    store: { icon: s.icon, color: s.color },
  })),
)
const selectedOption = computed(
  () => storeOptions.value.find((o) => o.id === selectedStoreId.value) ?? null,
)

async function onPickStore(option: StoreOption | null) {
  if (!option || option.id === selectedStoreId.value) return
  selectedStoreId.value = option.id
  await loadOrder()
}

async function loadOrder() {
  const storeId = selectedStoreId.value
  if (storeId === null) return
  loadingOrder.value = true
  error.value = null
  try {
    const { categoryIds } = await getStoreCategoryOrder(props.houseId, storeId)
    arrangedIds.value = categoryIds
    ordered.value = orderCategoriesForStore(categories.items.value, categoryIds)
  } catch (e) {
    error.value = (e as Error).message || strings.loadFailed
  } finally {
    loadingOrder.value = false
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    error.value = null
    await Promise.all([categories.load(), stores.load()])
    // Reopening keeps the store that was last arranged, so a round of
    // tweaks does not start from the picker every time.
    if (
      selectedStoreId.value !== null &&
      !storeItems.value.some((s) => s.id === selectedStoreId.value)
    ) {
      selectedStoreId.value = null
    }
    if (selectedStoreId.value === null) {
      selectedStoreId.value = storeItems.value[0]?.id ?? null
    }
    await loadOrder()
  },
  { immediate: true },
)

async function persist() {
  const storeId = selectedStoreId.value
  if (storeId === null) return
  saving.value = true
  error.value = null
  const ids = ordered.value.map((c) => c.id)
  try {
    const saved = await setStoreCategoryOrder(props.houseId, storeId, ids)
    arrangedIds.value = saved.categoryIds
  } catch (e) {
    error.value = (e as Error).message || strings.saveFailed
    await loadOrder()
  } finally {
    saving.value = false
  }
}

async function resetOrder() {
  const storeId = selectedStoreId.value
  if (storeId === null) return
  saving.value = true
  error.value = null
  try {
    await clearStoreCategoryOrder(props.houseId, storeId)
    arrangedIds.value = []
    ordered.value = orderCategoriesForStore(categories.items.value, [])
  } catch (e) {
    error.value = (e as Error).message || strings.saveFailed
  } finally {
    saving.value = false
  }
}

// -------- Drag & drop reorder --------

type ListGridItem =
  | { type: 'cat'; key: string; cat: Category; position: number }
  | { type: 'placeholder'; key: string }

const draggingId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const listRef = ref<HTMLElement | null>(null)

const gridItems = computed<ListGridItem[]>(() => {
  const dragId = draggingId.value
  const dropAt = dropIndex.value
  const rows: Category[] =
    dragId !== null && dropAt !== null
      ? ordered.value.filter((c) => c.id !== dragId)
      : ordered.value
  const out: ListGridItem[] = rows.map((c, i) => ({
    type: 'cat' as const,
    key: 'c-' + c.id,
    cat: c,
    position: i + 1,
  }))
  if (dragId !== null && dropAt !== null) {
    out.splice(Math.min(dropAt, out.length), 0, {
      type: 'placeholder',
      key: 'drop-placeholder',
    })
  }
  return out
})

function onDragStart(e: DragEvent, id: number) {
  if (!e.dataTransfer) return
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
  if (dragId === null || dragId === hoveredId) return
  const without = ordered.value.filter((c) => c.id !== dragId)
  const idx = without.findIndex((c) => c.id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    dropIndex.value = clientY > rect.top + rect.height / 2 ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onDragOver(e: DragEvent, hoveredId: number) {
  computeDropIndex(hoveredId, e.clientY, e.currentTarget as HTMLElement | null)
}

async function commitReorder() {
  const dragId = draggingId.value
  const idx = dropIndex.value
  draggingId.value = null
  dropIndex.value = null
  if (dragId === null || idx === null) return

  const dragged = ordered.value.find((c) => c.id === dragId)
  if (!dragged) return
  const without = ordered.value.filter((c) => c.id !== dragId)
  without.splice(Math.min(idx, without.length), 0, dragged)
  ordered.value = without
  await persist()
}

function bindDragListeners(el: HTMLElement | null) {
  el?.addEventListener('dragend', onDragEnd, true)
}
function unbindDragListeners(el: HTMLElement | null) {
  el?.removeEventListener('dragend', onDragEnd, true)
}

watch(listRef, (newEl, oldEl) => {
  unbindDragListeners(oldEl ?? null)
  bindDragListeners(newEl ?? null)
})
onBeforeUnmount(() => {
  unbindDragListeners(listRef.value)
})

useTouchReorder(listRef, {
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
})

const strings = {
  // TRANSLATORS: Dialog title, arranging categories in the order a shop is walked
  title: t('pantry', 'Per-store order'),
  hint: t(
    'pantry',
    'Arrange the categories in the order you walk this store. Shopping mode follows this order while you are there.',
  ),
  storeLabel: t('pantry', 'Store:'),
  pickStore: t('pantry', 'Pick a store'),
  noStores: t('pantry', 'No stores yet. Add a store to give it an order of its own.'),
  noCategories: t('pantry', 'No categories yet. Categories help organize checklist items.'),
  dragHandle: t('pantry', 'Drag to reorder'),
  // TRANSLATORS: Button that drops this store's own order and returns it to the shared one
  reset: t('pantry', 'Use the shared order'),
  done: t('pantry', 'Done'),
  loadFailed: t('pantry', 'Could not load the order for this store.'),
  saveFailed: t('pantry', 'Could not save the order for this store.'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-store-order {
  &__hint {
    color: var(--color-text-maxcontrast);
    margin: 0 0 0.75rem 0;
  }

  &__error {
    color: var(--color-error-text, var(--color-error));
    margin: 0.5rem 0 0 0;
  }

  &__option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  &__option-icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__list {
    list-style: none;
    padding: 0;
    margin: 0.75rem 0 1rem 0;
  }

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

  &__position {
    flex-shrink: 0;
    min-width: 1.5rem;
    text-align: end;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
    font-variant-numeric: tabular-nums;
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

  &__placeholder {
    min-height: 40px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
    margin: 4px 0;
  }
}
</style>
