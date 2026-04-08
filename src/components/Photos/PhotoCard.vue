<template>
  <div
    class="photo-card"
    :class="{ 'photo-card--dragging': isDragging, 'photo-card--selected': selected }"
    :data-drag-id="photo.id"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover.prevent="onDragOver"
    @click="$emit('preview', photo)"
  >
    <img :src="thumbUrl(photo.id)" :alt="photo.caption ?? ''" class="photo-card__img" />
    <div class="photo-card__select" @click.stop>
      <NcCheckboxRadioSwitch
        :model-value="selected"
        @update:model-value="$emit('select', photo.id)"
      />
    </div>
    <div class="photo-card__actions" @click.stop>
      <NcActions :aria-label="strings.actions">
        <NcActionButton @click.stop="$emit('edit', photo)">
          <template #icon>
            <PencilIcon :size="20" />
          </template>
          {{ strings.edit }}
        </NcActionButton>
        <NcActionButton v-if="photo.folderId !== null" @click.stop="$emit('move-to-root', photo)">
          <template #icon>
            <ArrowUpIcon :size="20" />
          </template>
          {{ strings.moveToBoard }}
        </NcActionButton>
        <NcActionButton @click.stop="$emit('delete', photo)">
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
          {{ strings.delete }}
        </NcActionButton>
      </NcActions>
    </div>
    <p v-if="photo.caption" class="photo-card__caption">{{ photo.caption }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { photoPreviewUrl } from '@/api/images'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import ArrowUpIcon from '@icons/ArrowUp.vue'
import type { Photo } from '@/api/types'

const props = withDefaults(
  defineProps<{ photo: Photo; houseId: number; reorderEnabled?: boolean; selected?: boolean }>(),
  { reorderEnabled: true, selected: false },
)
const emit = defineEmits<{
  preview: [photo: Photo]
  edit: [photo: Photo]
  delete: [photo: Photo]
  'move-to-root': [photo: Photo]
  'drag-start': [photoId: number]
  'reorder-over': [photoId: number, event: MouseEvent]
  select: [photoId: number]
}>()

const isDragging = ref(false)

function thumbUrl(photoId: number): string {
  return photoPreviewUrl(props.houseId, photoId, 300)
}

function onDragStart(e: DragEvent) {
  if (!e.dataTransfer) return
  isDragging.value = true
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData(
    'application/x-pantry-photo',
    JSON.stringify({ id: props.photo.id, folderId: props.photo.folderId }),
  )
  if (props.reorderEnabled) {
    emit('drag-start', props.photo.id)
  }
}

function onDragEnd() {
  isDragging.value = false
}

function onDragOver(e: DragEvent) {
  if (!props.reorderEnabled) return
  if (!e.dataTransfer?.types.includes('application/x-pantry-photo')) return
  emit('reorder-over', props.photo.id, e)
}

const strings = {
  actions: t('pantry', 'Photo actions'),
  edit: t('pantry', 'Edit'),
  delete: t('pantry', 'Delete'),
  moveToBoard: t('pantry', 'Move to board'),
}
</script>

<style scoped lang="scss">
.photo-card {
  position: relative;
  border-radius: var(--border-radius-large, 12px);
  overflow: hidden;
  cursor: grab;
  aspect-ratio: 1;
  transition:
    box-shadow 0.2s ease,
    transform 0.2s ease,
    outline-color 0.2s ease;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }

  &:active,
  &--dragging {
    cursor: grabbing;
  }

  &--dragging {
    opacity: 0.35;
    transform: scale(0.95);
    pointer-events: none;
  }

  &__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    border-radius: var(--border-radius-large, 12px);
    cursor: inherit;
  }

  &--selected {
    outline: 3px solid var(--color-primary-element);
    outline-offset: -3px;
  }

  &__select {
    position: absolute;
    top: 0.25rem;
    inset-inline-start: 0.25rem;
    z-index: 2;
    opacity: 0;
    transition:
      opacity 0.2s ease,
      background 0.2s ease;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 99px;

    &:hover {
      background: rgba(0, 0, 0, 0.6);
    }

    :deep(.checkbox-radio-switch__content) {
      transition:
        color 0.2s ease,
        opacity 0.2s ease,
        border-radius 0.2s ease;
    }
  }

  &--selected &__select,
  &:hover &__select {
    opacity: 1;
  }

  @media (hover: none) {
    .photo-card__select {
      opacity: 1;
    }
  }

  &__actions {
    position: absolute;
    top: 0.25rem;
    inset-inline-end: 0.25rem;
    z-index: 1;
    opacity: 0;
    transition: opacity 0.2s ease;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 99px;
  }

  &:hover &__actions {
    opacity: 1;
  }

  @media (hover: none) {
    .photo-card__actions {
      opacity: 1;
    }
  }

  &__caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    margin: 0;
    padding: 0.5rem;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.6));
    color: white;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 0 0 var(--border-radius-large, 12px) var(--border-radius-large, 12px);
  }
}
</style>
