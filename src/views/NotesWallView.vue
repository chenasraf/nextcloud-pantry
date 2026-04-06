<template>
  <div ref="wallRef" class="pantry-notes">
    <PageToolbar :title="strings.title">
      <template #actions>
        <NcButton variant="primary" @click="openCreateDialog">
          <template #icon><PlusIcon :size="20" /></template>
          {{ strings.newNote }}
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-notes__body">
      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="notes.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <NoteIcon />
        </template>
        <template #action>
          <NcButton variant="primary" @click="openCreateDialog">
            {{ strings.newNote }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <div v-else class="pantry-notes__grid">
        <template v-for="item in gridItems" :key="item.key">
          <div
            v-if="item.type === 'placeholder'"
            class="pantry-notes__placeholder"
            @dragover.prevent
            @drop.prevent.stop="onPlaceholderDrop"
          />
          <NoteCard
            v-else
            :note="item.note"
            @edit="openEditDialog"
            @delete="confirmDelete"
            @drag-start="onDragStart"
            @reorder-over="onReorderOver"
          />
        </template>
      </div>
    </div>

    <!-- Create/Edit dialog -->
    <NoteDialog
      v-if="showDialog"
      :open="showDialog"
      :note="editingNote"
      @update:open="closeDialog"
      @save="submitDialog"
    />

    <!-- Delete confirm -->
    <NcDialog
      v-if="deletingNote"
      :name="strings.deleteTitle"
      :open="!!deletingNote"
      close-on-click-outside
      @update:open="(v) => !v && (deletingNote = null)"
    >
      <p>{{ deleteBody }}</p>
      <template #actions>
        <NcButton @click="deletingNote = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDelete">{{ strings.delete }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import PageToolbar from '@/components/PageToolbar'
import { NoteCard, NoteDialog } from '@/components/NotesWall'
import PlusIcon from '@icons/Plus.vue'
import NoteIcon from '@icons/Note.vue'
import type { Note } from '@/api/types'
import { useNotesWall } from '@/composables/useNotesWall'

const props = defineProps<{ houseId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const { notes, loading, load, create, update, remove, reorder } = useNotesWall(houseIdNum.value)

onMounted(load)
watch(
  () => props.houseId,
  () => load(),
)

// ----- Reorder -----

type GridItem = { type: 'note'; key: string; note: Note } | { type: 'placeholder'; key: string }

const draggingNoteId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const wallRef = ref<HTMLElement | null>(null)

function buildGridItems(): GridItem[] {
  const dragId = draggingNoteId.value
  if (dragId === null || dropIndex.value === null) {
    return notes.value.map((n) => ({ type: 'note' as const, key: 'n-' + n.id, note: n }))
  }

  const without = notes.value.filter((n) => n.id !== dragId)
  const items: GridItem[] = without.map((n) => ({
    type: 'note' as const,
    key: 'n-' + n.id,
    note: n,
  }))
  const clampedIndex = Math.min(dropIndex.value, items.length)
  items.splice(clampedIndex, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return items
}

const gridItems = computed(() => buildGridItems())

function onDragStart(noteId: number) {
  draggingNoteId.value = noteId
  dropIndex.value = null
}

function onReorderOver(hoveredNoteId: number, e: MouseEvent) {
  const dragId = draggingNoteId.value
  if (!dragId || dragId === hoveredNoteId) return

  const without = notes.value.filter((n) => n.id !== dragId)
  const idx = without.findIndex((n) => n.id === hoveredNoteId)
  if (idx === -1) return

  const target = e.currentTarget as HTMLElement | null
  if (target) {
    const rect = target.getBoundingClientRect()
    const past = e.clientX > rect.left + rect.width / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingNoteId.value
  const idx = dropIndex.value
  draggingNoteId.value = null
  dropIndex.value = null

  if (dragId === null || idx === null) return

  const dragged = notes.value.find((n) => n.id === dragId)
  if (!dragged) return

  const without = notes.value.filter((n) => n.id !== dragId)
  const clampedIndex = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clampedIndex, 0, dragged)

  const items = reordered.map((n, i) => ({ id: n.id, sortOrder: i }))
  await reorder(items)
}

// Capture-phase listeners
function onDropCapture() {
  draggingNoteId.value = null
  dropIndex.value = null
}
onMounted(() => {
  wallRef.value?.addEventListener('drop', onDropCapture, true)
  wallRef.value?.addEventListener('dragend', onDropCapture, true)
})
onBeforeUnmount(() => {
  wallRef.value?.removeEventListener('drop', onDropCapture, true)
  wallRef.value?.removeEventListener('dragend', onDropCapture, true)
})

// ----- Create / Edit -----

const showDialog = ref(false)
const editingNote = ref<Note | null>(null)

function openCreateDialog() {
  editingNote.value = null
  showDialog.value = true
}

function openEditDialog(note: Note) {
  editingNote.value = note
  showDialog.value = true
}

function closeDialog(v: boolean) {
  if (!v) {
    showDialog.value = false
    editingNote.value = null
  }
}

async function submitDialog(data: { title: string; content: string; color: string }) {
  if (editingNote.value) {
    await update(editingNote.value.id, {
      title: data.title,
      content: data.content,
      color: data.color,
    })
    // Update local ref so subsequent saves use the latest state
    editingNote.value = {
      ...editingNote.value,
      title: data.title,
      content: data.content || null,
      color: data.color || null,
    }
  } else {
    const created = await create(data.title, data.content || null, data.color || null)
    // Switch to editing the newly created note
    editingNote.value = created
  }
}

// ----- Delete -----

const deletingNote = ref<Note | null>(null)
const deleteBody = computed(() =>
  t('pantry', 'Are you sure you want to delete "{name}"?', {
    name: deletingNote.value?.title ?? '',
  }),
)

function confirmDelete(note: Note) {
  deletingNote.value = note
}

async function submitDelete() {
  if (!deletingNote.value) return
  await remove(deletingNote.value.id)
  deletingNote.value = null
}

const strings = {
  title: t('pantry', 'Notes wall'),
  newNote: t('pantry', 'New note'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  emptyTitle: t('pantry', 'No notes yet'),
  emptyBody: t('pantry', 'Create your first note to start sharing reminders with your household.'),
  deleteTitle: t('pantry', 'Delete note'),
}
</script>

<style scoped lang="scss">
.pantry-notes {
  position: relative;
  min-height: 100%;

  &__body {
    max-width: 1100px;
    margin: 0 auto;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    padding: 0 1rem 1rem;
  }

  &__placeholder {
    min-height: 80px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius-large, 12px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}
</style>
