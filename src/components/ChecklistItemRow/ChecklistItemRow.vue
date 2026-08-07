<template>
  <li
    class="checklist-row"
    :class="{
      'checklist-row--done': item.done,
      'checklist-row--dragging': isDragging,
      'checklist-row--reorderable': reorderEnabled,
      'checklist-row--with-added-by': showAddedBy && !selectionMode && !suggestion,
      'checklist-row--selecting': selectionMode,
      'checklist-row--selected': selectionMode && selected,
      'checklist-row--suggestion': suggestion,
    }"
    :data-drag-id="item.id"
    :draggable="reorderEnabled && !suggestion ? 'true' : 'false'"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover.prevent="onDragOver"
    @click="onRowClick"
  >
    <div v-if="selectionMode" class="checklist-row__select" @click.stop>
      <NcCheckboxRadioSwitch
        :model-value="selected"
        :aria-label="strings.selectItem"
        @update:model-value="$emit('toggle-select', item.id)"
      />
    </div>
    <span v-if="reorderEnabled" class="checklist-row__handle" :aria-label="strings.dragToReorder">
      <DragVerticalIcon :size="20" />
    </span>
    <div v-if="suggestion || selectionMode" class="checklist-row__check">
      <span class="checklist-row__label checklist-row__label--standalone">
        <button
          v-if="item.imageFileId"
          type="button"
          class="checklist-row__thumb"
          :aria-label="strings.viewImage"
          @click.stop.prevent="$emit('preview', item)"
        >
          <img :src="thumbUrl" :alt="item.name" />
        </button>
        <span class="checklist-row__name">{{ item.name }}</span>
      </span>
    </div>
    <div v-else class="checklist-row__check">
      <NcCheckboxRadioSwitch
        :model-value="item.done"
        :disabled="!canCheck"
        :aria-label="tapRowToComplete ? undefined : item.name"
        :class="{ 'checklist-row__check-fill': tapRowToComplete }"
        @update:model-value="$emit('toggle', item.id)"
      >
        <span v-if="tapRowToComplete" class="checklist-row__label">
          <button
            v-if="item.imageFileId"
            type="button"
            class="checklist-row__thumb"
            :aria-label="strings.viewImage"
            @click.stop.prevent="$emit('preview', item)"
          >
            <img :src="thumbUrl" :alt="item.name" />
          </button>
          <span class="checklist-row__name">{{ item.name }}</span>
        </span>
      </NcCheckboxRadioSwitch>
      <span v-if="!tapRowToComplete" class="checklist-row__label checklist-row__label--standalone">
        <button
          v-if="item.imageFileId"
          type="button"
          class="checklist-row__thumb"
          :aria-label="strings.viewImage"
          @click.stop.prevent="$emit('preview', item)"
        >
          <img :src="thumbUrl" :alt="item.name" />
        </button>
        <span class="checklist-row__name">{{ item.name }}</span>
      </span>
    </div>
    <div class="checklist-row__meta">
      <span v-if="item.quantity" class="checklist-row__quantity">&times; {{ item.quantity }}</span>
      <span v-if="priceText" class="checklist-row__price">{{ priceText }}</span>
      <span
        v-if="item.description"
        class="checklist-row__description"
        :title="item.description"
        :aria-label="strings.hasDescription"
      >
        <TextBoxOutlineIcon :size="14" />
      </span>
      <span
        v-if="item.rrule && !compact"
        class="checklist-row__recurrence"
        :title="recurrenceTooltip"
      >
        <RepeatIcon :size="14" />
        {{ formatRrule(item.rrule) }}
      </span>
      <span v-if="list" class="checklist-row__list" :style="listChipStyle">
        <component :is="checklistIconComponent(list.icon)" :size="14" />
        {{ list.name }}
      </span>
      <span
        v-if="category && !hideCategory"
        class="checklist-row__category"
        :style="{ color: category.color }"
      >
        <component :is="categoryIconComponent(category.icon)" :size="14" />
        {{ category.name }}
      </span>
      <button
        v-for="store in hideStore ? [] : stores"
        :key="store.id"
        type="button"
        class="checklist-row__store"
        :style="{ color: store.color }"
        :aria-label="storeLabel(store.name)"
        @click.stop.prevent="$emit('view-store', store)"
      >
        <component :is="storeIconComponent(store.icon)" :size="14" />
        {{ store.name }}
      </button>
    </div>
    <div v-if="showAddedBy && !selectionMode && !suggestion" class="checklist-row__added-by">
      <NcAvatar
        v-if="item.addedBy"
        :user="item.addedBy"
        :size="24"
        :show-user-status="false"
        :tooltip-message="addedByTooltip"
      />
    </div>
    <div v-if="!selectionMode && !suggestion && !compact" class="checklist-row__actions">
      <NcButton variant="tertiary" :aria-label="strings.viewItem" @click="$emit('view', item)">
        <template #icon>
          <EyeIcon :size="18" />
        </template>
      </NcButton>
      <NcActions :aria-label="strings.itemActions">
        <NcActionButton v-if="canEditItem" close-after-click @click="$emit('edit', item)">
          <template #icon>
            <PencilIcon :size="20" />
          </template>
          {{ strings.editItem }}
        </NcActionButton>
        <NcActionButton v-if="canMoveItem" close-after-click @click="$emit('move', item)">
          <template #icon>
            <ArrowRightIcon :size="20" />
          </template>
          {{ strings.moveItem }}
        </NcActionButton>
        <NcActionButton v-if="canCopyItem" close-after-click @click="$emit('copy', item)">
          <template #icon>
            <ContentCopyIcon :size="20" />
          </template>
          {{ strings.copyItem }}
        </NcActionButton>
        <NcActionButton
          v-if="trashMode && canDeleteItem"
          close-after-click
          @click="$emit('restore', item.id)"
        >
          <template #icon>
            <DeleteRestoreIcon :size="20" />
          </template>
          {{ strings.restoreItem }}
        </NcActionButton>
        <NcActionButton
          v-if="!trashMode && !archiveMode && canEditItem"
          close-after-click
          @click="$emit('archive', item.id)"
        >
          <template #icon>
            <ArchiveArrowDownOutlineIcon :size="20" />
          </template>
          {{ strings.archiveItem }}
        </NcActionButton>
        <NcActionButton
          v-if="archiveMode && canEditItem"
          close-after-click
          @click="$emit('unarchive', item.id)"
        >
          <template #icon>
            <ArchiveArrowUpOutlineIcon :size="20" />
          </template>
          {{ strings.unarchiveItem }}
        </NcActionButton>
        <NcActionButton v-if="canDeleteItem" close-after-click @click="$emit('remove', item.id)">
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
          {{ trashMode || archiveMode ? strings.deletePermanently : strings.removeItem }}
        </NcActionButton>
      </NcActions>
    </div>
    <div v-if="sessionRemovable" class="checklist-row__session-remove">
      <NcButton
        variant="tertiary"
        :aria-label="strings.removeFromTrip"
        :title="strings.removeFromTrip"
        @click="$emit('session-remove', item.id)"
      >
        <template #icon>
          <CloseIcon :size="18" />
        </template>
      </NcButton>
    </div>
  </li>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import DragVerticalIcon from '@icons/DragVertical.vue'
import RepeatIcon from '@icons/Repeat.vue'
import TextBoxOutlineIcon from '@icons/TextBoxOutline.vue'
import PencilIcon from '@icons/Pencil.vue'
import EyeIcon from '@icons/Eye.vue'
import CloseIcon from '@icons/Close.vue'
import DeleteIcon from '@icons/Delete.vue'
import DeleteRestoreIcon from '@icons/DeleteRestore.vue'
import ArchiveArrowDownOutlineIcon from '@icons/ArchiveArrowDownOutline.vue'
import ArchiveArrowUpOutlineIcon from '@icons/ArchiveArrowUpOutline.vue'
import ArrowRightIcon from '@icons/ArrowRight.vue'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule, formatNextRecurrence } from '@/utils/rrule'
import { formatPrice } from '@/utils/price'
import { useHouseMembers } from '@/composables/useHouseMembers'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import type { ChecklistItem, Category, Checklist, Store } from '@/api/types'

const { can } = useCurrentHouse()

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    category: Category | null
    /** Hide the category chip (used when category headers already group rows). */
    hideCategory?: boolean
    /** Hide the store chips (used when store headers already group rows). */
    hideStore?: boolean
    /** Stores attached to this item, resolved to entities by the parent. */
    stores?: Store[]
    list?: Checklist | null
    houseId: number
    reorderEnabled?: boolean
    trashMode?: boolean
    archiveMode?: boolean
    tapRowToComplete?: boolean
    showAddedBy?: boolean
    /**
     * Whether the parent list accepts writes for the current user. False for a
     * view-only shared list, which bounds every item action to read-only. When
     * true (the default), the granular role capabilities apply as usual.
     */
    listWritable?: boolean
    /** Multi-select mode: shows a selection checkbox and taps toggle selection. */
    selectionMode?: boolean
    /** Whether this row is currently selected (only meaningful in selection mode). */
    selected?: boolean
    /**
     * Renders the row as a checkbox-less reuse suggestion: no checkbox, drag
     * handle, added-by avatar or actions — just the name and meta chips, with the
     * whole row a single tap target that emits `select`.
     */
    suggestion?: boolean
    /**
     * Hides the per-row actions (view / edit / move / archive / delete). Used
     * where those operations do not belong, e.g. the shopping view, while
     * keeping the check control, image, and meta chips.
     */
    compact?: boolean
    /**
     * Shows a single "remove from this trip" button that emits `session-remove`.
     * Used in shopping mode to drop an item from the current session only,
     * without deleting the checklist item. Independent of `compact`.
     */
    sessionRemovable?: boolean
  }>(),
  {
    hideCategory: false,
    hideStore: false,
    stores: () => [],
    list: null,
    reorderEnabled: false,
    trashMode: false,
    archiveMode: false,
    tapRowToComplete: false,
    showAddedBy: false,
    listWritable: true,
    selectionMode: false,
    selected: false,
    suggestion: false,
    compact: false,
    sessionRemovable: false,
  },
)

// Write affordances require both the list to be writable (a view-only shared
// list disables all of them) and the specific role capability.
const canCheck = computed(() => props.listWritable && can.value.canCheckItems)
const canEditItem = computed(() => props.listWritable && can.value.canEditLists)
const canMoveItem = computed(() => props.listWritable && can.value.canMoveItems)
const canCopyItem = computed(() => props.listWritable && can.value.canCopyItems)
const canDeleteItem = computed(() => props.listWritable && can.value.canDeleteItems)

const listChipStyle = computed(() => {
  if (!props.list?.color) return undefined
  return { background: props.list.color, color: contrastColor(props.list.color) }
})

const emit = defineEmits<{
  toggle: [id: number]
  view: [item: ChecklistItem]
  'view-store': [store: Store]
  edit: [item: ChecklistItem]
  move: [item: ChecklistItem]
  copy: [item: ChecklistItem]
  remove: [id: number]
  'session-remove': [id: number]
  restore: [id: number]
  archive: [id: number]
  unarchive: [id: number]
  preview: [item: ChecklistItem]
  select: [item: ChecklistItem]
  'toggle-select': [id: number]
  'drag-start': [itemId: number]
  'reorder-over': [itemId: number, event: MouseEvent]
}>()

const isDragging = ref(false)

// In selection mode, a tap anywhere on the row body toggles selection. The
// selection checkbox and the image thumb stop propagation so they keep their
// own behavior.
function onRowClick() {
  if (props.suggestion) {
    emit('select', props.item)
    return
  }
  if (props.selectionMode) {
    emit('toggle-select', props.item.id)
  }
}

function onDragStart(e: DragEvent) {
  if (!props.reorderEnabled || !e.dataTransfer) return
  isDragging.value = true
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/x-pantry-checklist-item', String(props.item.id))
  emit('drag-start', props.item.id)
}

function onDragEnd() {
  isDragging.value = false
}

function onDragOver(e: DragEvent) {
  if (!props.reorderEnabled) return
  if (!e.dataTransfer?.types.includes('application/x-pantry-checklist-item')) return
  emit('reorder-over', props.item.id, e)
}

const thumbUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 64)
    : '',
)

const { displayNameByUid } = useHouseMembers(props.houseId)
const addedByTooltip = computed(() => {
  const uid = props.item.addedBy
  if (!uid) return ''
  const name = displayNameByUid.value[uid] ?? uid
  return t('pantry', 'Added by {user}', { user: name })
})

const priceText = computed(() => formatPrice(props.item))

const recurrenceTooltip = computed(() => {
  const next = formatNextRecurrence(
    props.item.nextDueAt,
    props.item.repeatFromCompletion,
    props.item.done,
  )
  return next ? t('pantry', 'Next: {next}', { next }) : formatRrule(props.item.rrule!)
})

const strings = {
  dragToReorder: t('pantry', 'Drag to reorder'),
  hasDescription: t('pantry', 'Has a description'),
  selectItem: t('pantry', 'Select item'),
  viewImage: t('pantry', 'View image'),
  viewItem: t('pantry', 'View item'),
  itemActions: t('pantry', 'Item actions'),
  editItem: t('pantry', 'Edit item'),
  moveItem: t('pantry', 'Move to list'),
  copyItem: t('pantry', 'Copy to list'),
  removeItem: t('pantry', 'Remove item'),
  // TRANSLATORS: Button that removes an item from the current shopping trip
  // only; it stays on the list and is not marked done.
  removeFromTrip: t('pantry', 'Remove from this trip'),
  deletePermanently: t('pantry', 'Delete permanently'),
  restoreItem: t('pantry', 'Restore'),
  // TRANSLATORS: Verb. Menu action that moves this item to the archive.
  archiveItem: t('pantry', 'Archive item'),
  unarchiveItem: t('pantry', 'Unarchive'),
}

function storeLabel(name: string): string {
  // TRANSLATORS: Accessible label for a store chip that opens the store details. The placeholder is the store name.
  return t('pantry', 'View store {name}', { name })
}
</script>

<style scoped lang="scss">
.checklist-row {
  display: grid;
  // Columns: [handle] check(1fr) meta [added-by] actions. The optional tracks
  // are toggled via modifier classes so the actions column is always the last
  // track on every row — otherwise rows missing an avatar shift the eye/kebab.
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  background: var(--color-main-background);

  &--reorderable {
    grid-template-columns: auto 1fr auto auto;
  }

  &--with-added-by {
    grid-template-columns: 1fr auto auto auto;
  }

  &--reorderable#{&}--with-added-by {
    grid-template-columns: auto 1fr auto auto auto;
  }

  // Selection mode drops the handle, actions and added-by, prepending a single
  // selection checkbox track. It never co-occurs with the reorder handle.
  &--selecting {
    grid-template-columns: auto 1fr auto;
    cursor: pointer;
  }

  &--selected {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
  }

  // Reuse suggestion: no checkbox/handle/actions tracks — just the name and its
  // meta chips. Transparent so it blends into the surrounding suggestions panel.
  &--suggestion {
    grid-template-columns: 1fr auto;
    background: transparent;
    border-color: transparent;
    cursor: pointer;

    // The whole row is a single tap target — force every descendant (name, meta
    // chips, icons) to follow the pointer instead of their own cursors.
    :deep(*) {
      cursor: pointer;
    }

    // The panel itself sits on --color-background-hover, so hover uses the
    // darker shade to stay visible against it.
    &:hover,
    &:focus-visible {
      background: var(--color-background-dark);
    }

    @media (max-width: 600px) {
      grid-template-columns: 1fr;
      grid-template-areas:
        'check'
        'meta';
    }
  }

  @media (max-width: 600px) {
    grid-template-columns: 1fr auto auto;
    grid-template-areas:
      'check added actions'
      'meta  meta  meta';
    gap: 0.25rem 0.5rem;

    &.checklist-row--reorderable {
      grid-template-columns: auto 1fr auto auto;
      grid-template-areas:
        'handle check added actions'
        'handle meta  meta  meta';
    }

    &.checklist-row--selecting {
      grid-template-columns: auto 1fr;
      grid-template-areas:
        'select check'
        'meta   meta';
    }

    .checklist-row__select {
      grid-area: select;
    }

    .checklist-row__handle {
      grid-area: handle;
    }

    .checklist-row__check {
      grid-area: check;
    }

    .checklist-row__added-by {
      grid-area: added;
    }

    .checklist-row__actions,
    .checklist-row__session-remove {
      grid-area: actions;
    }

    .checklist-row__meta {
      grid-area: meta;
    }
  }

  &--done {
    opacity: 0.6;

    .checklist-row__name {
      text-decoration: line-through;
    }
  }

  &--dragging {
    opacity: 0.35;
  }

  &[draggable='true'] {
    cursor: grab;

    &:active {
      cursor: grabbing;
    }
  }

  &__handle {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-maxcontrast);
    cursor: grab;

    &:active {
      cursor: grabbing;
    }

    // The icon component renders its own span/svg which reset the cursor.
    :deep(*) {
      cursor: inherit;
      pointer-events: none;
    }
  }

  &__check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  // When the row-tap pref is on, the label content sits inside the
  // NcCheckboxRadioSwitch slot. Stretch the checkbox component (and its
  // inner content wrapper) so the hover highlight and click target span
  // the whole row.
  &__check-fill {
    flex: 1;
    min-width: 0;

    :deep(.checkbox-radio-switch__content) {
      width: 100%;
      max-width: unset;
    }
  }

  :deep(.checkbox-content__icon) {
    margin-block: auto !important;
  }

  &__label {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;

    // Standalone label (checkbox-only mode): fills the remaining space
    // next to the checkbox in the flex container.
    &--standalone {
      flex: 1;
    }
  }

  &__thumb {
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 6px);
    background: var(--color-background-hover);
    cursor: zoom-in;
    overflow: hidden;
    flex-shrink: 0;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    &:hover,
    &:focus-visible {
      border-color: var(--color-primary-element);
    }
  }

  &__name {
    font-weight: 500;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  &__session-remove {
    display: flex;
    align-items: center;
  }

  &__added-by {
    display: flex;
    align-items: center;
  }

  &__select {
    display: flex;
    align-items: center;
  }

  &__quantity,
  &__price,
  &__category,
  &__store,
  &__recurrence,
  &__description,
  &__list {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }

  &__description {
    cursor: help;

    // The icon component renders its own span/svg which reset the cursor.
    :deep(*) {
      cursor: inherit;
    }
  }

  // Reset the global button styles so the chip keeps the same box as the
  // other (span-based) meta chips instead of the 44px clickable-area height.
  &__store {
    appearance: none;
    margin: 0;
    min-height: 0;
    height: auto;
    border: none;
    font: inherit;
    line-height: inherit;
    color: inherit;
    cursor: pointer;

    &:hover,
    &:focus-visible {
      background: var(--color-background-dark);
    }
  }
}
</style>
