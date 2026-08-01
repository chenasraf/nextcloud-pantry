<template>
  <div class="shop-start">
    <div class="shop-start__inner">
      <header class="shop-start__head">
        <CartIcon :size="28" />
        <h2>{{ strings.title }}</h2>
      </header>

      <div v-if="loading" class="shop-start__center">
        <NcLoadingIcon :size="36" />
      </div>

      <template v-else>
        <!-- One-live-session guard: resume or end the existing trip. -->
        <div v-if="existing" class="shop-start__guard">
          <p class="shop-start__guard-text">
            {{ existingSameHouse ? strings.guardHere : guardOtherText }}
          </p>
          <div class="shop-start__guard-actions">
            <NcButton variant="primary" @click="resumeExisting">{{ strings.resume }}</NcButton>
            <NcButton variant="secondary" :disabled="ending" @click="endExisting">
              {{ strings.endPrevious }}
            </NcButton>
          </div>
        </div>

        <template v-else>
          <section class="shop-start__section">
            <div class="shop-start__section-head">
              <h3>{{ strings.listsTitle }}</h3>
              <NcButton variant="tertiary" @click="toggleSelectAll">
                {{ allSelected ? strings.selectNone : strings.selectAll }}
              </NcButton>
            </div>
            <NcEmptyContent v-if="lists.length === 0" :name="strings.noLists">
              <template #icon><ClipboardListIcon /></template>
            </NcEmptyContent>
            <ul v-else class="shop-start__lists">
              <li v-for="list in lists" :key="list.id">
                <NcCheckboxRadioSwitch
                  :model-value="selectedListIds.includes(list.id)"
                  @update:model-value="toggleList(list.id, $event)"
                >
                  {{ list.name }}
                </NcCheckboxRadioSwitch>
              </li>
            </ul>
          </section>

          <section class="shop-start__section">
            <h3>{{ strings.storesTitle }}</h3>
            <p class="shop-start__hint">{{ strings.storesHint }}</p>
            <p v-if="availableStoreIds.length === 0" class="shop-start__hint">
              {{ strings.noStores }}
            </p>
            <ul v-else ref="storeListRef" class="shop-start__stores">
              <template v-for="gi in storeGridItems" :key="gi.key">
                <li
                  v-if="gi.type === 'placeholder'"
                  class="shop-start__store-placeholder"
                  @dragover.prevent
                  @drop.prevent.stop="onStoreDrop"
                />
                <li
                  v-else
                  class="shop-start__store"
                  :class="{
                    'shop-start__store--dragging': draggingStoreId === gi.id,
                    'shop-start__store--off': !enabledStores.has(gi.id),
                  }"
                  :data-drag-id="gi.id"
                  draggable="true"
                  @dragstart="onStoreDragStart($event, gi.id)"
                  @dragend="onStoreDragEnd"
                  @dragover.prevent="onStoreDragOver($event, gi.id)"
                  @drop.prevent.stop="commitStoreReorder"
                >
                  <span
                    class="shop-start__store-handle"
                    :aria-label="strings.dragHandle"
                    :title="strings.dragHandle"
                  >
                    <DragVerticalIcon :size="20" />
                  </span>
                  <span class="shop-start__store-icon" :style="{ color: storeColor(gi.id) }">
                    <component :is="storeIcon(gi.id)" :size="18" />
                  </span>
                  <span class="shop-start__store-name">{{ storeName(gi.id) }}</span>
                  <NcCheckboxRadioSwitch
                    type="switch"
                    :model-value="enabledStores.has(gi.id)"
                    :aria-label="strings.toggleStore"
                    @update:model-value="toggleStore(gi.id, $event)"
                  />
                </li>
              </template>
            </ul>
          </section>

          <section class="shop-start__section">
            <NcCheckboxRadioSwitch v-model="includeUnassigned">
              {{ strings.includeUnassigned }}
            </NcCheckboxRadioSwitch>
          </section>

          <div class="shop-start__actions">
            <NcButton
              variant="primary"
              :disabled="starting || selectedListIds.length === 0"
              @click="start"
            >
              <template #icon><CartIcon :size="20" /></template>
              {{ strings.start }}
            </NcButton>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import CartIcon from '@icons/Cart.vue'
import ClipboardListIcon from '@icons/ClipboardList.vue'
import DragVerticalIcon from '@icons/DragVertical.vue'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { useChecklists } from '@/composables/useChecklist'
import { useStores } from '@/composables/useStores'
import { useHouses } from '@/composables/useHouses'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { listAllItems } from '@/api/lists'
import { createSession, getCurrentSession, closeSession as apiCloseSession } from '@/api/shopping'
import type { ChecklistItem, ShoppingSession } from '@/api/types'

const route = useRoute()
const router = useRouter()

// The route-derived, already-numeric house id (route params are strings, so
// comparing them against the server's numeric ids needs this coercion).
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)

const { lists, load: loadLists } = useChecklists(houseIdNum.value)
const { load: loadStores, findById: findStore } = useStores(houseIdNum.value)
const { load: loadHouses, findById: findHouse } = useHouses()

const loading = ref(true)
const starting = ref(false)
const ending = ref(false)

const selectedListIds = ref<number[]>([])
const houseItems = ref<ChecklistItem[]>([])
const includeUnassigned = ref(true)

const existing = ref<ShoppingSession | null>(null)
const existingSameHouse = computed(() => existing.value?.houseId === houseIdNum.value)
const guardOtherText = computed(() => {
  const name = existing.value ? (findHouse(existing.value.houseId)?.name ?? '') : ''
  return name
    ? t('pantry', 'You have a shopping trip in progress in {house}.', { house: name })
    : strings.guardOther
})

const allSelected = computed(
  () => lists.value.length > 0 && selectedListIds.value.length === lists.value.length,
)

// Stores worth planning a route to: those with at least one un-done item on a
// selected list. Sorted by name for a stable default order.
const availableStoreIds = computed<number[]>(() => {
  const selected = new Set(selectedListIds.value)
  const ids = new Set<number>()
  for (const item of houseItems.value) {
    if (item.done || !selected.has(item.listId)) continue
    for (const storeId of item.storeIds) ids.add(storeId)
  }
  return [...ids].sort((a, b) => (storeName(a) || '').localeCompare(storeName(b) || ''))
})

// The user's route: the available stores in a chosen order, each on/off.
const storeOrder = ref<number[]>([])
const enabledStores = ref<Set<number>>(new Set())

// Keep the route in sync as the available set changes: preserve order and
// on/off state for stores still available, add newcomers (default on) at the
// end, drop stores that no longer have items.
watch(
  availableStoreIds,
  (next) => {
    const nextSet = new Set(next)
    const kept = storeOrder.value.filter((id) => nextSet.has(id))
    const added = next.filter((id) => !storeOrder.value.includes(id))
    storeOrder.value = [...kept, ...added]
    const nextEnabled = new Set<number>()
    for (const id of storeOrder.value) {
      if (added.includes(id) || enabledStores.value.has(id)) nextEnabled.add(id)
    }
    enabledStores.value = nextEnabled
  },
  { immediate: true },
)

function initialSelection(): number[] {
  const raw = route.query.lists
  const value = Array.isArray(raw) ? raw[0] : raw
  // Default (and explicit "all"): every list. A csv of ids preselects those.
  if (!value || value === 'all') {
    return lists.value.map((l) => l.id)
  }
  const ids = String(value)
    .split(',')
    .map((s) => Number(s))
    .filter((n) => Number.isFinite(n))
  const known = new Set(lists.value.map((l) => l.id))
  const picked = ids.filter((id) => known.has(id))
  return picked.length ? picked : lists.value.map((l) => l.id)
}

onMounted(async () => {
  loading.value = true
  try {
    const [, , items] = await Promise.all([
      loadLists(true),
      loadStores(),
      listAllItems(houseIdNum.value),
    ])
    houseItems.value = items
    existing.value = await getCurrentSession()
    if (existing.value && existing.value.houseId !== houseIdNum.value) {
      await loadHouses()
    }
    selectedListIds.value = initialSelection()
  } catch (e) {
    showError((e as Error).message || strings.loadFailed)
  } finally {
    loading.value = false
  }
})

function toggleList(id: number, checked: boolean) {
  if (checked) {
    if (!selectedListIds.value.includes(id)) selectedListIds.value = [...selectedListIds.value, id]
  } else {
    selectedListIds.value = selectedListIds.value.filter((x) => x !== id)
  }
}

function toggleSelectAll() {
  selectedListIds.value = allSelected.value ? [] : lists.value.map((l) => l.id)
}

function toggleStore(id: number, on: boolean) {
  if (on) enabledStores.value.add(id)
  else enabledStores.value.delete(id)
}

function storeName(id: number): string {
  return findStore(id)?.name ?? ''
}
function storeColor(id: number): string {
  return findStore(id)?.color ?? 'var(--color-primary-element)'
}
function storeIcon(id: number) {
  return storeIconComponent(findStore(id)?.icon)
}

// -------- Store reorder (drag & drop) --------

type StoreGridItem =
  { type: 'store'; key: string; id: number } | { type: 'placeholder'; key: string }

const draggingStoreId = ref<number | null>(null)
const storeDropIndex = ref<number | null>(null)
const storeListRef = ref<HTMLElement | null>(null)

const storeGridItems = computed<StoreGridItem[]>(() => {
  const source = storeOrder.value
  if (draggingStoreId.value === null || storeDropIndex.value === null) {
    return source.map((id) => ({ type: 'store' as const, key: 's-' + id, id }))
  }
  const dragId = draggingStoreId.value
  const without = source.filter((id) => id !== dragId)
  const out: StoreGridItem[] = without.map((id) => ({ type: 'store' as const, key: 's-' + id, id }))
  const clamped = Math.min(storeDropIndex.value, out.length)
  out.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return out
})

function onStoreDragStart(e: DragEvent, id: number) {
  if (!e.dataTransfer) return
  draggingStoreId.value = id
  storeDropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(id))
}

function onStoreDragEnd() {
  draggingStoreId.value = null
  storeDropIndex.value = null
}

function computeStoreDropIndex(hoveredId: number, clientY: number, target: HTMLElement | null) {
  const dragId = draggingStoreId.value
  if (dragId === null || dragId === hoveredId) return
  const without = storeOrder.value.filter((id) => id !== dragId)
  const idx = without.findIndex((id) => id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientY > rect.top + rect.height / 2
    storeDropIndex.value = past ? idx + 1 : idx
  } else {
    storeDropIndex.value = idx
  }
}

function onStoreDragOver(e: DragEvent, hoveredId: number) {
  computeStoreDropIndex(hoveredId, e.clientY, e.currentTarget as HTMLElement | null)
}

function onStoreDrop() {
  commitStoreReorder()
}

function commitStoreReorder() {
  const dragId = draggingStoreId.value
  const idx = storeDropIndex.value
  draggingStoreId.value = null
  storeDropIndex.value = null
  if (dragId === null || idx === null) return
  const without = storeOrder.value.filter((id) => id !== dragId)
  const clamped = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragId)
  storeOrder.value = reordered
}

function bindStoreDragEnd(el: HTMLElement | null) {
  el?.addEventListener('dragend', onStoreDragEnd, true)
}
function unbindStoreDragEnd(el: HTMLElement | null) {
  el?.removeEventListener('dragend', onStoreDragEnd, true)
}
watch(storeListRef, (newEl, oldEl) => {
  unbindStoreDragEnd(oldEl ?? null)
  bindStoreDragEnd(newEl ?? null)
})
onBeforeUnmount(() => unbindStoreDragEnd(storeListRef.value))

useTouchReorder(storeListRef, {
  onDragStart: (id) => {
    draggingStoreId.value = id
    storeDropIndex.value = null
  },
  onReorderOver(hoveredId, _clientX, clientY) {
    const el =
      storeListRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
    computeStoreDropIndex(hoveredId, clientY, el)
  },
  onDrop: commitStoreReorder,
  onCancel() {
    draggingStoreId.value = null
    storeDropIndex.value = null
  },
})

// -------- Lifecycle --------

async function start() {
  if (selectedListIds.value.length === 0) return
  starting.value = true
  try {
    const storeIds = storeOrder.value.filter((id) => enabledStores.value.has(id))
    const result = await createSession(houseIdNum.value, {
      listIds: selectedListIds.value,
      storeIds,
      includeUnassigned: includeUnassigned.value,
    })
    if (result.status === 'conflict') {
      // Someone/somewhere already has a live trip — surface the guard instead.
      existing.value = result.session
      if (result.session.houseId !== houseIdNum.value) await loadHouses()
      return
    }
    await goToSession(result.session)
  } catch (e) {
    showError((e as Error).message || strings.startFailed)
  } finally {
    starting.value = false
  }
}

function resumeExisting() {
  if (existing.value) void goToSession(existing.value)
}

async function endExisting() {
  if (!existing.value) return
  ending.value = true
  try {
    await apiCloseSession(existing.value.houseId, existing.value.id)
    existing.value = null
    selectedListIds.value = initialSelection()
  } catch (e) {
    showError((e as Error).message || strings.endFailed)
  } finally {
    ending.value = false
  }
}

function goToSession(session: ShoppingSession) {
  return router.push({
    name: 'shopping-session',
    params: { houseId: String(session.houseId), sessionId: String(session.id) },
  })
}

const strings = {
  title: t('pantry', 'Start shopping'),
  listsTitle: t('pantry', 'Lists to shop'),
  // TRANSLATORS: Button that selects every checklist in the list-picker.
  selectAll: t('pantry', 'Select all'),
  // TRANSLATORS: Button that clears the checklist selection.
  selectNone: t('pantry', 'Select none'),
  noLists: t('pantry', 'No lists to shop'),
  storesTitle: t('pantry', 'Stores'),
  storesHint: t('pantry', 'Turn the stores you will visit on or off, and drag to set the order.'),
  noStores: t('pantry', 'No stores have items on the selected lists.'),
  includeUnassigned: t('pantry', 'Include items not assigned to any store'),
  start: t('pantry', 'Start shopping'),
  dragHandle: t('pantry', 'Drag to reorder'),
  // TRANSLATORS: Accessible label for the on/off switch that includes a store in the route.
  toggleStore: t('pantry', 'Visit this store'),
  guardHere: t('pantry', 'You already have a shopping trip in progress.'),
  guardOther: t('pantry', 'You have a shopping trip in progress in another house.'),
  resume: t('pantry', 'Resume'),
  endPrevious: t('pantry', 'End previous trip'),
  loadFailed: t('pantry', 'Could not load shopping setup.'),
  startFailed: t('pantry', 'Could not start shopping.'),
  endFailed: t('pantry', 'Could not end the previous trip.'),
}
</script>

<style scoped lang="scss">
.shop-start {
  height: 100%;
  overflow-y: auto;

  &__inner {
    max-width: 640px;
    margin: 0 auto;
    padding: 1rem 1rem 3rem;
  }

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;

    h2 {
      margin: 0;
    }
  }

  &__center {
    display: flex;
    justify-content: center;
    padding: 3rem;
  }

  &__section {
    margin-bottom: 1.5rem;
  }

  &__section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;

    h3 {
      margin: 0;
    }
  }

  &__hint {
    margin: 0 0 0.5rem;
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }

  &__lists {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0;
  }

  &__stores {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0;
  }

  &__store {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.5rem;
    border-bottom: 1px solid var(--color-border);

    &--dragging {
      opacity: 0.4;
    }

    &--off {
      .shop-start__store-name,
      .shop-start__store-icon {
        opacity: 0.5;
      }
    }
  }

  &__store-handle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    cursor: grab;
    color: var(--color-text-maxcontrast);
    touch-action: none;
  }

  &__store-icon {
    display: inline-flex;
    flex-shrink: 0;
  }

  &__store-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__store-placeholder {
    min-height: 40px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
    margin: 4px 0;
  }

  &__guard {
    padding: 1rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 12px);
    background: var(--color-background-hover);
  }

  &__guard-text {
    margin: 0 0 0.75rem;
  }

  &__guard-actions,
  &__actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
}
</style>
