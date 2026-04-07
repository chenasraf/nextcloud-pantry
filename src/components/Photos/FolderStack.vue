<template>
  <div
    class="folder-stack"
    :class="{ 'folder-stack--drag-over': isDragOver }"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover.prevent="onDragOver"
    @dragleave="onDragLeave"
    @drop.prevent.stop="onDrop"
    @click="$emit('open', folder)"
  >
    <div class="folder-stack__photos">
      <img
        v-for="(photo, i) in stackPhotos"
        :key="photo.id"
        :src="thumbUrl(photo.id)"
        :alt="photo.caption ?? ''"
        class="folder-stack__photo"
        :style="photoStyle(i)"
      />
      <div v-if="stackPhotos.length === 0" class="folder-stack__empty">
        <FolderIcon :size="48" />
      </div>
    </div>
    <span class="folder-stack__label">{{ folder.name }}</span>
    <span v-if="photoCount > 0" class="folder-stack__count">{{ photoCount }}</span>
    <div class="folder-stack__actions" @click.stop>
      <NcActions :aria-label="strings.actions">
        <NcActionButton @click.stop="$emit('rename', folder)">
          <template #icon><PencilIcon :size="20" /></template>
          {{ strings.rename }}
        </NcActionButton>
        <NcActionButton @click.stop="$emit('delete', folder)">
          <template #icon><DeleteIcon :size="20" /></template>
          {{ strings.delete }}
        </NcActionButton>
      </NcActions>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { photoPreviewUrl } from '@/api/images'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import FolderIcon from '@icons/Folder.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import type { Photo, PhotoFolder } from '@/api/types'

const props = defineProps<{
  folder: PhotoFolder
  photos: Photo[]
  houseId: number
}>()

const emit = defineEmits<{
  open: [folder: PhotoFolder]
  rename: [folder: PhotoFolder]
  delete: [folder: PhotoFolder]
  'drop-photo': [photoId: number, folderId: number]
  'drop-files': [files: File[], folderId: number]
  'drag-over-change': [isOver: boolean]
}>()

const isDragOver = ref(false)

const stackPhotos = computed(() => props.photos.slice(0, 5))
const photoCount = computed(() => props.photos.length)

const rotations = [-6, 3, -2, 5, -1]
const offsets = [
  { x: 0, y: 0 },
  { x: 4, y: -2 },
  { x: -3, y: 3 },
  { x: 2, y: 1 },
  { x: -1, y: -1 },
]

function photoStyle(index: number) {
  const rot = rotations[index] ?? 0
  const off = offsets[index] ?? { x: 0, y: 0 }
  return {
    transform: `translate(-50%, -50%) rotate(${rot}deg) translate(${off.x}px, ${off.y}px)`,
    zIndex: index + 1,
  }
}

function thumbUrl(photoId: number): string {
  return photoPreviewUrl(props.houseId, photoId, 120)
}

function onDragStart(e: DragEvent) {
  if (!e.dataTransfer) return
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/x-pantry-folder', String(props.folder.id))
}

function onDragEnd() {
  setDragOver(false)
}

function setDragOver(value: boolean) {
  if (isDragOver.value !== value) {
    isDragOver.value = value
    emit('drag-over-change', value)
  }
}

function onDragOver(e: DragEvent) {
  if (!e.dataTransfer) return
  if (
    e.dataTransfer.types.includes('application/x-pantry-photo') ||
    e.dataTransfer.types.includes('Files')
  ) {
    setDragOver(true)
  }
}

function onDragLeave() {
  setDragOver(false)
}

function onDrop(e: DragEvent) {
  setDragOver(false)
  if (!e.dataTransfer) return

  // External file drop
  if (e.dataTransfer.files.length > 0) {
    emit('drop-files', Array.from(e.dataTransfer.files), props.folder.id)
    return
  }

  // Internal photo move
  const photoData = e.dataTransfer.getData('application/x-pantry-photo')
  if (photoData) {
    try {
      const { id } = JSON.parse(photoData)
      emit('drop-photo', id, props.folder.id)
    } catch {
      // ignore
    }
  }
}

const strings = {
  actions: t('pantry', 'Folder actions'),
  rename: t('pantry', 'Rename'),
  delete: t('pantry', 'Delete'),
}
</script>

<style scoped lang="scss">
.folder-stack {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  cursor: grab;
  border-radius: var(--border-radius-large, 12px);
  overflow: hidden;
  transition:
    box-shadow 0.15s ease,
    transform 0.15s ease;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }

  &:active {
    cursor: grabbing;
  }

  &--drag-over {
    outline: 3px dashed var(--color-primary-element);
    background: var(--color-primary-element-light);
  }

  &__photos {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: inherit;
  }

  &__photo {
    position: absolute;
    width: 65%;
    height: 65%;
    object-fit: cover;
    border-radius: 4px;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    top: 50%;
    left: 50%;
    transform-origin: center center;
    cursor: inherit;
  }

  &__empty {
    width: 100%;
    height: 100%;
    color: var(--color-text-maxcontrast);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-background-dark);
  }

  &__label {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem 0.5rem 0.5rem;
    text-align: center;
    z-index: 10;
    font-weight: 600;
    font-size: 0.85rem;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.55));
  }

  &__count {
    position: absolute;
    top: 0.5rem;
    inset-inline-start: 0.5rem;
    z-index: 10;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 99px;
  }

  &__actions {
    position: absolute;
    top: 0.25rem;
    inset-inline-end: 0.25rem;
    z-index: 11;
    opacity: 0;
    transition: opacity 0.15s ease;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 99px;
  }

  &:hover &__actions {
    opacity: 1;
  }
}
</style>
