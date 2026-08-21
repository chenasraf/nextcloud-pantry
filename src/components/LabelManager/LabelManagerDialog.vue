<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="!labelLoading && labelItems.length > 0" class="pantry-label-toolbar">
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

    <div v-if="labelLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p v-if="labelItems.length === 0" class="pantry-label-hint">
        {{ strings.noLabelsHint }}
      </p>
      <ul v-else ref="listRef" class="pantry-label-list">
        <template v-for="gi in gridItems" :key="gi.key">
          <li v-if="gi.type === 'header'" class="pantry-label-list__group">
            {{ gi.title }}
          </li>
          <li
            v-else-if="gi.type === 'placeholder'"
            class="pantry-label-list__placeholder"
            @dragover.prevent
            @drop.prevent.stop="onPlaceholderDrop"
          />
          <li
            v-else
            :class="[
              'pantry-label-list__item',
              { 'pantry-label-list__item--dragging': draggingId === gi.label.id },
            ]"
            :data-drag-id="gi.label.id"
            :data-group-id="gi.groupId ?? ''"
            :draggable="isCustomSort ? 'true' : 'false'"
            @dragstart="onDragStart($event, gi.label.id, gi.groupId)"
            @dragend="onDragEnd"
            @dragover.prevent="onDragOver($event, gi.label.id, gi.groupId)"
            @drop.prevent.stop="commitReorder"
          >
            <span
              v-if="isCustomSort"
              class="pantry-label-list__handle"
              :aria-label="strings.dragHandle"
              :title="strings.dragHandle"
            >
              <DragVerticalIcon :size="20" />
            </span>
            <span class="pantry-label-list__icon" :style="{ color: gi.label.color }">
              <component :is="labelIconComponent(gi.label.icon)" :size="20" />
            </span>
            <span class="pantry-label-list__name">{{ gi.label.name }}</span>
            <div class="pantry-label-list__actions">
              <NcButton
                variant="tertiary"
                :aria-label="strings.editLabel"
                @click="startEditLabel(gi.label)"
              >
                <template #icon><PencilIcon :size="18" /></template>
              </NcButton>
              <NcButton
                variant="tertiary"
                :aria-label="strings.deleteLabel"
                @click="confirmDeleteLabel(gi.label)"
              >
                <template #icon><DeleteIcon :size="18" /></template>
              </NcButton>
            </div>
          </li>
        </template>
      </ul>
    </template>
    <template #actions>
      <NcButton variant="primary" @click="openCreateLabel">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.newLabel }}
      </NcButton>
    </template>
  </NcDialog>

  <!-- Create/edit form -->
  <LabelFormDialog
    :open="showForm"
    :house-id="houseId"
    :label="editingLabel"
    :saving="labelSaving"
    :error="labelError"
    @update:open="closeForm"
    @save="submitForm"
  />

  <!-- Delete label confirm -->
  <NcDialog
    v-if="deletingLabel"
    :name="strings.deleteLabelTitle"
    :open="!!deletingLabel"
    close-on-click-outside
    @update:open="(v) => !v && (deletingLabel = null)"
  >
    <p>{{ deleteLabelConfirmBody }}</p>
    <template #actions>
      <NcButton @click="deletingLabel = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="submitDeleteLabel">{{ strings.delete }}</NcButton>
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
import type { Label } from '@/api/types'
import type { LabelSort } from '@/api/prefs'
import { getLabelSort, setLabelSort } from '@/api/prefs'
import { useLabels } from '@/composables/useLabels'
import { useChecklists } from '@/composables/useChecklist'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import LabelFormDialog from './LabelFormDialog.vue'

const props = defineProps<{ open: boolean; houseId: number }>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  'sort-changed': []
  // A mutation that may have changed which labels items carry server-side (a
  // scope change re-homes a label and detaches items on other lists; a delete
  // detaches every item). The parent should refetch its items.
  'items-affected': []
}>()

const labels = useLabels(props.houseId)
const labelItems = computed(() => labels.items.value)
const labelLoading = computed(() => labels.loading.value)

const { lists, load: loadLists } = useChecklists(props.houseId)

// Labels grouped by their list scope: globals first, then one group per list
// that has labels, in the lists' own display order. Grouping is purely visual —
// sort_order stays a single house-wide sequence.
interface LabelGroup {
  listId: number | null
  title: string
  labels: Label[]
}
const groups = computed<LabelGroup[]>(() => {
  const byList = new Map<number, Label[]>()
  const globals: Label[] = []
  for (const l of labelItems.value) {
    if (l.listId == null) {
      globals.push(l)
    } else {
      const arr = byList.get(l.listId) ?? []
      arr.push(l)
      byList.set(l.listId, arr)
    }
  }
  const out: LabelGroup[] = []
  if (globals.length) out.push({ listId: null, title: strings.globalGroup, labels: globals })
  for (const list of lists.value) {
    const ls = byList.get(list.id)
    if (ls && ls.length) out.push({ listId: list.id, title: list.name, labels: ls })
  }
  // Labels whose list is not currently loaded (defensive; keeps them visible).
  for (const [lid, ls] of byList) {
    if (!lists.value.some((l) => l.id === lid)) {
      out.push({ listId: lid, title: t('pantry', 'List #{id}', { id: String(lid) }), labels: ls })
    }
  }
  return out
})
const showGroups = computed(() => groups.value.length > 1)

const currentSort = ref<LabelSort>('name_asc')
const isCustomSort = computed(() => currentSort.value === 'custom')

const sortOptions: { value: LabelSort; label: string }[] = [
  { value: 'name_asc', label: t('pantry', 'Name A–Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z–A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getLabelSort(props.houseId)
  currentSort.value = prefs.sort
  labels.setSortBy(prefs.sort)
}

const sortDirty = ref(false)

async function changeSort(value: LabelSort) {
  if (value === currentSort.value) return
  currentSort.value = value
  labels.setSortBy(value)
  sortDirty.value = true
  await setLabelSort(props.houseId, value)
}

watch(
  () => props.open,
  async (isOpen, wasOpen) => {
    if (isOpen) {
      sortDirty.value = false
      await loadSortPref()
      await Promise.all([labels.load(), loadLists()])
    } else if (wasOpen && sortDirty.value) {
      sortDirty.value = false
      emit('sort-changed')
    }
  },
  { immediate: true },
)

// -------- Drag & drop reorder --------

type ListGridItem =
  | { type: 'header'; key: string; title: string }
  | { type: 'label'; key: string; label: Label; groupId: number | null }
  | { type: 'placeholder'; key: string }

const draggingId = ref<number | null>(null)
// Reordering is confined to the dragged label's own group; a null group is the
// global scope.
const draggingGroupId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const listRef = ref<HTMLElement | null>(null)

const gridItems = computed<ListGridItem[]>(() => {
  const out: ListGridItem[] = []
  const dragId = draggingId.value
  const dropAt = dropIndex.value
  const dragging = isCustomSort.value && dragId !== null && dropAt !== null
  for (const g of groups.value) {
    if (showGroups.value) {
      out.push({ type: 'header', key: 'h-' + String(g.listId), title: g.title })
    }
    if (dragging && draggingGroupId.value === g.listId) {
      const without = g.labels.filter((l) => l.id !== dragId)
      const rows: ListGridItem[] = without.map((l) => ({
        type: 'label' as const,
        key: 'l-' + l.id,
        label: l,
        groupId: g.listId,
      }))
      const clamped = Math.min(dropAt as number, rows.length)
      rows.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
      out.push(...rows)
    } else {
      for (const l of g.labels) {
        out.push({ type: 'label', key: 'l-' + l.id, label: l, groupId: g.listId })
      }
    }
  }
  return out
})

function onDragStart(e: DragEvent, id: number, groupId: number | null) {
  if (!isCustomSort.value || !e.dataTransfer) return
  draggingId.value = id
  draggingGroupId.value = groupId
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  // Some browsers refuse to start a drag without data — set anything.
  e.dataTransfer.setData('text/plain', String(id))
}

function onDragEnd() {
  draggingId.value = null
  draggingGroupId.value = null
  dropIndex.value = null
}

function computeDropIndex(
  hoveredId: number,
  hoveredGroupId: number | null,
  clientY: number,
  target: HTMLElement | null,
) {
  const dragId = draggingId.value
  if (!dragId || dragId === hoveredId) return
  // Only reorder within the same group; ignore hovers over other groups.
  if (hoveredGroupId !== draggingGroupId.value) return
  const group = groups.value.find((g) => g.listId === draggingGroupId.value)
  if (!group) return
  const without = group.labels.filter((l) => l.id !== dragId)
  const idx = without.findIndex((l) => l.id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientY > rect.top + rect.height / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onDragOver(e: DragEvent, hoveredId: number, hoveredGroupId: number | null) {
  computeDropIndex(hoveredId, hoveredGroupId, e.clientY, e.currentTarget as HTMLElement | null)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingId.value
  const idx = dropIndex.value
  const groupId = draggingGroupId.value
  draggingId.value = null
  draggingGroupId.value = null
  dropIndex.value = null
  if (dragId === null || idx === null) return

  const group = groups.value.find((g) => g.listId === groupId)
  const dragged = group?.labels.find((l) => l.id === dragId)
  if (!group || !dragged) return

  const without = group.labels.filter((l) => l.id !== dragId)
  const clamped = Math.min(idx, without.length)
  const newGroupLabels = [...without]
  newGroupLabels.splice(clamped, 0, dragged)

  // Renumber the whole house-wide sequence, substituting the reordered group, so
  // sort_order stays a single coherent order that also keeps groups intact.
  const flat: Label[] = []
  for (const g of groups.value) {
    flat.push(...(g.listId === groupId ? newGroupLabels : g.labels))
  }
  const entries = flat.map((l, n) => ({ id: l.id, sortOrder: n }))
  sortDirty.value = true
  await labels.reorder(entries)
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
      draggingGroupId.value = labelItems.value.find((l) => l.id === id)?.listId ?? null
      dropIndex.value = null
    },
    onReorderOver(hoveredId, _clientX, clientY) {
      const el = listRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      const hoveredGroupId = labelItems.value.find((l) => l.id === hoveredId)?.listId ?? null
      computeDropIndex(hoveredId, hoveredGroupId, clientY, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingId.value = null
      draggingGroupId.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

// -------- Form state --------
const showForm = ref(false)
const editingLabel = ref<Label | null>(null)
const deletingLabel = ref<Label | null>(null)
const labelSaving = ref(false)
const labelError = ref<string | null>(null)

function openCreateLabel() {
  editingLabel.value = null
  labelError.value = null
  showForm.value = true
}

function startEditLabel(label: Label) {
  editingLabel.value = label
  labelError.value = null
  showForm.value = true
}

function closeForm(v: boolean) {
  if (!v) {
    showForm.value = false
    editingLabel.value = null
  }
}

function confirmDeleteLabel(label: Label) {
  deletingLabel.value = label
}

const deleteLabelConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the label "{name}"?', {
    name: deletingLabel.value?.name ?? '',
  }),
)

async function submitForm(data: {
  name: string
  icon: string
  color: string
  listId: number | null
}) {
  labelSaving.value = true
  labelError.value = null
  try {
    if (editingLabel.value) {
      // A scope change detaches this label from items on other lists.
      const scopeChanged = editingLabel.value.listId !== data.listId
      await labels.update(editingLabel.value.id, data)
      if (scopeChanged) emit('items-affected')
    } else {
      await labels.create(data)
    }
    showForm.value = false
    editingLabel.value = null
  } catch (e) {
    labelError.value =
      (e as Error).message ||
      (editingLabel.value
        ? t('pantry', 'Could not update label.')
        : t('pantry', 'Could not create label.'))
  } finally {
    labelSaving.value = false
  }
}

async function submitDeleteLabel() {
  const target = deletingLabel.value
  if (!target) return
  await labels.remove(target.id)
  deletingLabel.value = null
  // Deleting a label removes it from every item that carried it.
  emit('items-affected')
}

const strings = {
  title: t('pantry', 'Manage labels'),
  noLabelsHint: t('pantry', 'No labels yet. Labels help tag and filter checklist items.'),
  newLabel: t('pantry', 'New label'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  editLabel: t('pantry', 'Edit'),
  deleteLabel: t('pantry', 'Delete'),
  deleteLabelTitle: t('pantry', 'Delete label'),
  sortLabel: t('pantry', 'Sort order'),
  dragHandle: t('pantry', 'Drag to reorder'),
  // TRANSLATORS: Header for labels available on every list (not tied to one list)
  globalGroup: t('pantry', 'All lists'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-label-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.25rem;
}

.pantry-label-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-label-list {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem 0;

  &__group {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--color-text-maxcontrast);
    padding: 0.75rem 0 0.25rem 0;

    &:first-child {
      padding-top: 0.25rem;
    }
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
