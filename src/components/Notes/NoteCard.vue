<template>
  <div
    class="note-card"
    :class="{ 'note-card--dragging': isDragging }"
    :style="cardStyle"
    :data-drag-id="note.id"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @dragover.prevent="onDragOver"
    @click="$emit('edit', note)"
  >
    <div class="note-card__actions" @click.stop>
      <NcActions :aria-label="strings.actions">
        <NcActionButton @click.stop="$emit('delete', note)">
          <template #icon><DeleteIcon :size="20" /></template>
          {{ strings.delete }}
        </NcActionButton>
      </NcActions>
    </div>
    <h3 class="note-card__title">{{ note.title }}</h3>
    <div v-if="note.content" class="note-card__content">
      <NcRichText :text="note.content" :use-markdown="true" :use-extended-markdown="true" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import DeleteIcon from '@icons/Delete.vue'
import { contrastColor } from './noteColors'
import type { Note } from '@/api/types'

const props = defineProps<{ note: Note }>()
const emit = defineEmits<{
  edit: [note: Note]
  delete: [note: Note]
  'drag-start': [noteId: number]
  'reorder-over': [noteId: number, event: MouseEvent]
}>()

const isDragging = ref(false)

const cardStyle = computed(() => {
  if (!props.note.color) return {}
  return {
    '--note-bg': props.note.color,
    '--note-fg': contrastColor(props.note.color),
  }
})

function onDragStart(e: DragEvent) {
  if (!e.dataTransfer) return
  isDragging.value = true
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/x-pantry-note', String(props.note.id))
  emit('drag-start', props.note.id)
}

function onDragEnd() {
  isDragging.value = false
}

function onDragOver(e: DragEvent) {
  if (!e.dataTransfer?.types.includes('application/x-pantry-note')) return
  emit('reorder-over', props.note.id, e)
}

const strings = {
  actions: t('pantry', 'Note actions'),
  delete: t('pantry', 'Delete'),
}
</script>

<style scoped lang="scss">
.note-card {
  position: relative;
  border-radius: var(--border-radius-large, 12px);
  overflow: hidden;
  cursor: grab;
  padding: 1rem;
  background: var(--note-bg, var(--color-background-hover));
  color: var(--note-fg, inherit);
  border: 1px solid var(--color-border);
  transition:
    box-shadow 0.15s ease,
    transform 0.15s ease;
  min-height: 80px;
  max-height: 240px;
  display: flex;
  flex-direction: column;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }

  &:active {
    cursor: grabbing;
  }

  &--dragging {
    opacity: 0.35;
    transform: scale(0.95);
    pointer-events: none;
    cursor: grabbing;
  }

  &__actions {
    position: absolute;
    top: 0.25rem;
    inset-inline-end: 0.25rem;
    z-index: 1;
    opacity: 0;
    transition: opacity 0.15s ease;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 99px;
  }

  &:hover &__actions {
    opacity: 1;
  }

  @media (hover: none) {
    .note-card__actions {
      opacity: 1;
    }
  }

  &__title {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding-inline-end: 2rem;
    color: inherit;
  }

  &__content {
    flex: 1;
    overflow: hidden;
    font-size: 0.85rem;
    line-height: 1.4;
    // Fade out overflowing text
    mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
    -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);

    // Force all rendered markdown elements to inherit the note's text color
    :deep(*) {
      color: inherit !important;
    }
  }
}
</style>
