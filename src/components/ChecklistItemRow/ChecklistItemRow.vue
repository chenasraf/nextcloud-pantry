<template>
  <li
    class="checklist-row"
    :class="{
      'checklist-row--done': item.done,
      'checklist-row--dragging': isDragging,
      'checklist-row--reorderable': reorderEnabled,
      'checklist-row--with-added-by': showAddedBy,
    }"
    :data-drag-id="item.id"
    :draggable="reorderEnabled ? 'true' : 'false'"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover.prevent="onDragOver"
  >
    <span v-if="reorderEnabled" class="checklist-row__handle" :aria-label="strings.dragToReorder">
      <DragVerticalIcon :size="20" />
    </span>
    <div class="checklist-row__check">
      <NcCheckboxRadioSwitch
        :model-value="item.done"
        :disabled="!can.canCheckItems"
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
      <span v-if="item.rrule" class="checklist-row__recurrence" :title="recurrenceTooltip">
        <RepeatIcon :size="14" />
        {{ formatRrule(item.rrule) }}
      </span>
      <span v-if="list" class="checklist-row__list" :style="listChipStyle">
        <component :is="checklistIconComponent(list.icon)" :size="14" />
        {{ list.name }}
      </span>
      <span v-if="category" class="checklist-row__category" :style="{ color: category.color }">
        <component :is="categoryIconComponent(category.icon)" :size="14" />
        {{ category.name }}
      </span>
    </div>
    <div v-if="showAddedBy" class="checklist-row__added-by">
      <NcAvatar
        v-if="item.addedBy"
        :user="item.addedBy"
        :size="24"
        :show-user-status="false"
        :tooltip-message="addedByTooltip"
      />
    </div>
    <div class="checklist-row__actions">
      <NcButton variant="tertiary" :aria-label="strings.viewItem" @click="$emit('view', item)">
        <template #icon>
          <EyeIcon :size="18" />
        </template>
      </NcButton>
      <NcActions :aria-label="strings.itemActions">
        <NcActionButton v-if="can.canEditLists" close-after-click @click="$emit('edit', item)">
          <template #icon>
            <PencilIcon :size="20" />
          </template>
          {{ strings.editItem }}
        </NcActionButton>
        <NcActionButton v-if="can.canMoveItems" close-after-click @click="$emit('move', item)">
          <template #icon>
            <ArrowRightIcon :size="20" />
          </template>
          {{ strings.moveItem }}
        </NcActionButton>
        <NcActionButton v-if="can.canCopyItems" close-after-click @click="$emit('copy', item)">
          <template #icon>
            <ContentCopyIcon :size="20" />
          </template>
          {{ strings.copyItem }}
        </NcActionButton>
        <NcActionButton
          v-if="trashMode && can.canDeleteItems"
          close-after-click
          @click="$emit('restore', item.id)"
        >
          <template #icon>
            <DeleteRestoreIcon :size="20" />
          </template>
          {{ strings.restoreItem }}
        </NcActionButton>
        <NcActionButton
          v-if="can.canDeleteItems"
          close-after-click
          @click="$emit('remove', item.id)"
        >
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
          {{ trashMode ? strings.deletePermanently : strings.removeItem }}
        </NcActionButton>
      </NcActions>
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
import PencilIcon from '@icons/Pencil.vue'
import EyeIcon from '@icons/Eye.vue'
import DeleteIcon from '@icons/Delete.vue'
import DeleteRestoreIcon from '@icons/DeleteRestore.vue'
import ArrowRightIcon from '@icons/ArrowRight.vue'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule, formatNextRecurrence } from '@/utils/rrule'
import { useHouseMembers } from '@/composables/useHouseMembers'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import type { ChecklistItem, Category, Checklist } from '@/api/types'

const { can } = useCurrentHouse()

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    category: Category | null
    list?: Checklist | null
    houseId: number
    reorderEnabled?: boolean
    trashMode?: boolean
    tapRowToComplete?: boolean
    showAddedBy?: boolean
  }>(),
  {
    list: null,
    reorderEnabled: false,
    trashMode: false,
    tapRowToComplete: false,
    showAddedBy: false,
  },
)

const listChipStyle = computed(() => {
  if (!props.list?.color) return undefined
  return { background: props.list.color, color: contrastColor(props.list.color) }
})

const emit = defineEmits<{
  toggle: [id: number]
  view: [item: ChecklistItem]
  edit: [item: ChecklistItem]
  move: [item: ChecklistItem]
  copy: [item: ChecklistItem]
  remove: [id: number]
  restore: [id: number]
  preview: [item: ChecklistItem]
  'drag-start': [itemId: number]
  'reorder-over': [itemId: number, event: MouseEvent]
}>()

const isDragging = ref(false)

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
  viewImage: t('pantry', 'View image'),
  viewItem: t('pantry', 'View item'),
  itemActions: t('pantry', 'Item actions'),
  editItem: t('pantry', 'Edit item'),
  moveItem: t('pantry', 'Move to list'),
  copyItem: t('pantry', 'Copy to list'),
  removeItem: t('pantry', 'Remove item'),
  deletePermanently: t('pantry', 'Delete permanently'),
  restoreItem: t('pantry', 'Restore'),
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

    .checklist-row__handle {
      grid-area: handle;
    }

    .checklist-row__check {
      grid-area: check;
    }

    .checklist-row__added-by {
      grid-area: added;
    }

    .checklist-row__actions {
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
    transform: scale(0.98);
    pointer-events: none;
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

  &__added-by {
    display: flex;
    align-items: center;
  }

  &__quantity,
  &__category,
  &__recurrence,
  &__list {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }
}
</style>
