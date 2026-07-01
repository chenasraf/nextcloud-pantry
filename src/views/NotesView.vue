<template>
  <div ref="wallRef" class="pantry-notes">
    <PageToolbar :title="strings.title" :actions="toolbarActions" />

    <div class="pantry-notes__body">
      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="visibleNotes.length === 0"
        :name="trashMode ? strings.trashEmptyTitle : strings.emptyTitle"
        :description="trashMode ? strings.trashEmptyBody : strings.emptyBody"
      >
        <template #icon>
          <TrashCanIcon v-if="trashMode" />
          <NoteIcon v-else />
        </template>
        <template v-if="!trashMode && can.canCreateNotes" #action>
          <NcButton variant="primary" @click="openCreateDialog">
            {{ strings.newNote }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <template v-else>
        <div v-if="trashMode" class="pantry-notes__trash-bar">
          <NcButton variant="error" @click="confirmingEmptyTrash = true">
            <template #icon><TrashCanIcon :size="20" /></template>
            {{ strings.emptyTrashAction }}
          </NcButton>
        </div>

        <!-- Selection bar -->
        <div v-if="!trashMode && selectedNoteIds.size > 0" class="pantry-selection-bar">
          <span>{{ selectionLabel }}</span>
          <NcButton variant="error" @click="confirmBulkDelete">
            <template #icon><DeleteIcon :size="20" /></template>
            {{ strings.remove }}
          </NcButton>
          <NcButton @click="selectedNoteIds.clear()">{{ strings.clearSelection }}</NcButton>
        </div>

        <div class="pantry-notes__grid">
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
              :draggable-enabled="reorderEnabled"
              :selected="selectedNoteIds.has(item.note.id)"
              :trash-mode="trashMode"
              @edit="openEditDialog"
              @delete="confirmDelete"
              @restore="onRestore"
              @toggle-pin="onTogglePin"
              @drag-start="onDragStart"
              @reorder-over="onReorderOver"
              @select="toggleNoteSelection"
            />
          </template>
        </div>
      </template>
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
      :name="deleteDialogTitle"
      :open="!!deletingNote"
      close-on-click-outside
      @update:open="(v) => !v && (deletingNote = null)"
    >
      <p>{{ deleteBody }}</p>
      <template #actions>
        <NcButton @click="deletingNote = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDelete">{{ deleteDialogAction }}</NcButton>
      </template>
    </NcDialog>

    <!-- Bulk delete confirm -->
    <NcDialog
      v-if="bulkDeleting"
      :name="strings.removeTitle"
      :open="bulkDeleting"
      close-on-click-outside
      @update:open="(v) => !v && (bulkDeleting = false)"
    >
      <p>{{ bulkDeleteBody }}</p>
      <template #actions>
        <NcButton @click="bulkDeleting = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitBulkDelete">{{ strings.remove }}</NcButton>
      </template>
    </NcDialog>

    <!-- Empty trash confirm -->
    <NcDialog
      v-if="confirmingEmptyTrash"
      :name="strings.emptyTrashTitle"
      :open="confirmingEmptyTrash"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingEmptyTrash = false)"
    >
      <p>{{ strings.emptyTrashBody }}</p>
      <template #actions>
        <NcButton @click="confirmingEmptyTrash = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitEmptyTrash">{{
          strings.emptyTrashAction
        }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { n, t } from '@nextcloud/l10n'
import { showInfo, showUndo, showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import PageToolbar, { type ToolbarAction } from '@/components/PageToolbar'
import { NoteCard, NoteDialog } from '@/components/Notes'
import PlusIcon from '@icons/Plus.vue'
import NoteIcon from '@icons/Note.vue'
import DeleteIcon from '@icons/Delete.vue'
import SortIcon from '@icons/Sort.vue'
import TrashCanIcon from '@icons/TrashCan.vue'
import type { Note } from '@/api/types'
import type { NoteSort } from '@/api/prefs'
import { getNoteSort, setNoteSort } from '@/api/prefs'
import { useNotes } from '@/composables/useNotes'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useTouchReorder } from '@/composables/useTouchReorder'

const props = defineProps<{ houseId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const {
  notes,
  deletedNotes,
  loading,
  load,
  loadDeleted,
  create,
  update,
  remove,
  restore,
  removePermanently,
  emptyTrash,
  reorder,
  togglePin,
  sortBy,
  trashMode,
} = useNotes(houseIdNum.value)

const { can } = useCurrentHouse()

const visibleNotes = computed(() => (trashMode.value ? deletedNotes.value : notes.value))

async function toggleTrash() {
  trashMode.value = !trashMode.value
  selectedNoteIds.value = new Set()
  await refresh()
}

async function refresh() {
  if (trashMode.value) {
    await loadDeleted()
  } else {
    await load()
  }
}

const confirmingEmptyTrash = ref(false)

async function submitEmptyTrash() {
  confirmingEmptyTrash.value = false
  await emptyTrash()
  showInfo(strings.trashEmptiedToast)
}

async function onRestore(note: Note) {
  await restore(note.id)
  showInfo(strings.noteRestored)
}

// ----- Sort -----

const currentSort = ref<NoteSort>('custom')
const isCustomSort = computed(() => currentSort.value === 'custom')
const reorderEnabled = computed(() => isCustomSort.value && !trashMode.value)

const noteSortOptions: { value: NoteSort; label: string }[] = [
  { value: 'newest', label: t('pantry', 'Newest first') },
  { value: 'oldest', label: t('pantry', 'Oldest first') },
  { value: 'title_asc', label: t('pantry', 'Title A\u2013Z') },
  { value: 'title_desc', label: t('pantry', 'Title Z\u2013A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getNoteSort(houseIdNum.value)
  currentSort.value = prefs.sort
  sortBy.value = prefs.sort
}

async function changeNoteSort(value: NoteSort) {
  currentSort.value = value
  sortBy.value = value
  await setNoteSort(houseIdNum.value, value)
  await load(value)
}

onMounted(async () => {
  await loadSortPref()
  await load()
})
watch(
  () => props.houseId,
  async () => {
    await loadSortPref()
    await load()
  },
)

// ----- Reorder -----

type GridItem = { type: 'note'; key: string; note: Note } | { type: 'placeholder'; key: string }

const draggingNoteId = ref<number | null>(null)
// Index is relative to the dragged note's pin group, not the full list.
const dropIndex = ref<number | null>(null)
const wallRef = ref<HTMLElement | null>(null)

function getDraggedNote(): Note | null {
  const id = draggingNoteId.value
  if (id === null) return null
  return notes.value.find((n) => n.id === id) ?? null
}

function buildGridItems(): GridItem[] {
  if (trashMode.value) {
    return deletedNotes.value.map((n) => ({ type: 'note' as const, key: 'n-' + n.id, note: n }))
  }
  const dragId = draggingNoteId.value
  const dragged = getDraggedNote()
  if (dragId === null || dropIndex.value === null || !dragged) {
    return notes.value.map((n) => ({ type: 'note' as const, key: 'n-' + n.id, note: n }))
  }

  // Pinned notes always occupy the first slots in notes.value (backend ordering),
  // so the placeholder's absolute position is groupOffset + (in-group dropIndex).
  const without = notes.value.filter((n) => n.id !== dragId)
  const pinnedCount = without.filter((n) => n.isPinned).length
  const groupOffset = dragged.isPinned ? 0 : pinnedCount
  const groupSize = dragged.isPinned ? pinnedCount : without.length - pinnedCount
  const clamped = Math.max(0, Math.min(dropIndex.value, groupSize))

  const items: GridItem[] = without.map((n) => ({
    type: 'note' as const,
    key: 'n-' + n.id,
    note: n,
  }))
  items.splice(groupOffset + clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return items
}

const gridItems = computed(() => buildGridItems())

function onDragStart(noteId: number) {
  draggingNoteId.value = noteId
  dropIndex.value = null
}

function computeDropIndex(hoveredNoteId: number, clientX: number, target: HTMLElement | null) {
  const dragId = draggingNoteId.value
  const dragged = getDraggedNote()
  if (!dragId || !dragged || dragId === hoveredNoteId) return

  const hovered = notes.value.find((n) => n.id === hoveredNoteId)
  // Cross-group hover is ignored — pinned and unpinned reorder independently.
  if (!hovered || hovered.isPinned !== dragged.isPinned) return

  // Index relative to the same-pin-group, excluding the dragged note.
  const group = notes.value.filter((n) => n.isPinned === dragged.isPinned && n.id !== dragId)
  const idx = group.findIndex((n) => n.id === hoveredNoteId)
  if (idx === -1) return

  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientX > rect.left + rect.width / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onReorderOver(hoveredNoteId: number, e: MouseEvent) {
  computeDropIndex(hoveredNoteId, e.clientX, e.currentTarget as HTMLElement | null)
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

  // Reorder only within the dragged note's pin group; leave the other group alone.
  const group = notes.value.filter((n) => n.isPinned === dragged.isPinned && n.id !== dragId)
  const clampedIndex = Math.min(idx, group.length)
  const reordered = [...group]
  reordered.splice(clampedIndex, 0, dragged)

  const items = reordered.map((n, i) => ({ id: n.id, sortOrder: i }))
  await reorder(items)
}

// Capture-phase listeners — commit the reorder on drop, reset on dragend.
function onDropCapture() {
  commitReorder()
}
function onDragEndCapture() {
  draggingNoteId.value = null
  dropIndex.value = null
}
onMounted(() => {
  wallRef.value?.addEventListener('drop', onDropCapture, true)
  wallRef.value?.addEventListener('dragend', onDragEndCapture, true)
})
onBeforeUnmount(() => {
  wallRef.value?.removeEventListener('drop', onDropCapture, true)
  wallRef.value?.removeEventListener('dragend', onDragEndCapture, true)
})

// ----- Touch reorder -----
useTouchReorder(
  wallRef,
  {
    onDragStart: onDragStart,
    onReorderOver(hoveredId, clientX) {
      const el = wallRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeDropIndex(hoveredId, clientX, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingNoteId.value = null
      dropIndex.value = null
    },
  },
  reorderEnabled,
)

// ----- Pin -----

async function onTogglePin(note: Note) {
  await togglePin(note.id)
  // After flipping isPinned, re-fetch so backend ordering (pinned first) is reflected.
  await load()
}

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

// ----- Selection -----

const selectedNoteIds = ref(new Set<number>())
const selectionLabel = computed(() =>
  n('pantry', '%n item selected', '%n items selected', selectedNoteIds.value.size),
)

function toggleNoteSelection(noteId: number) {
  const next = new Set(selectedNoteIds.value)
  if (next.has(noteId)) {
    next.delete(noteId)
  } else {
    next.add(noteId)
  }
  selectedNoteIds.value = next
}

const bulkDeleting = ref(false)

const bulkDeleteBody = computed(() =>
  n(
    'pantry',
    'Move %n note to the trash?',
    'Move %n notes to the trash?',
    selectedNoteIds.value.size,
  ),
)

function confirmBulkDelete() {
  bulkDeleting.value = true
}

async function submitBulkDelete() {
  const ids = [...selectedNoteIds.value]
  for (const id of ids) {
    await remove(id)
  }
  selectedNoteIds.value = new Set()
  bulkDeleting.value = false
  showUndo(
    n('pantry', '%n note moved to trash', '%n notes moved to trash', ids.length),
    () => {
      void Promise.all(ids.map((id) => restore(id))).catch(() => showError(strings.restoreFailed))
    },
    { timeout: 6000 },
  )
}

// ----- Delete -----

const deletingNote = ref<Note | null>(null)
const deleteBody = computed(() => {
  const name = deletingNote.value?.title ?? ''
  if (trashMode.value) {
    return t('pantry', 'Permanently delete "{name}"? This cannot be undone.', { name })
  }
  return t('pantry', 'Move "{name}" to the trash?', { name })
})
const deleteDialogTitle = computed(() =>
  trashMode.value ? strings.deletePermanentlyTitle : strings.removeTitle,
)
const deleteDialogAction = computed(() =>
  trashMode.value ? strings.deletePermanently : strings.remove,
)

function confirmDelete(note: Note) {
  deletingNote.value = note
}

async function submitDelete() {
  if (!deletingNote.value) return
  const id = deletingNote.value.id
  if (trashMode.value) {
    await removePermanently(id)
    deletingNote.value = null
    showInfo(strings.notePermanentlyDeleted)
    return
  }
  await remove(id)
  deletingNote.value = null
  showUndo(
    strings.noteMovedToTrash,
    () => {
      void restore(id).catch(() => showError(strings.restoreFailed))
    },
    { timeout: 6000 },
  )
}

const strings = {
  title: t('pantry', 'Notes wall'),
  newNote: t('pantry', 'New note'),
  cancel: t('pantry', 'Cancel'),
  remove: t('pantry', 'Remove'),
  deletePermanently: t('pantry', 'Delete permanently'),
  emptyTitle: t('pantry', 'No notes yet'),
  emptyBody: t('pantry', 'Create your first note to start sharing reminders with your household.'),
  removeTitle: t('pantry', 'Remove note'),
  deletePermanentlyTitle: t('pantry', 'Delete note permanently'),
  sortLabel: t('pantry', 'Sort order'),
  clearSelection: t('pantry', 'Clear selection'),
  trashLabel: t('pantry', 'Trash'),
  trashEmptyTitle: t('pantry', 'Trash is empty'),
  trashEmptyBody: t('pantry', 'Deleted notes will appear here.'),
  emptyTrashAction: t('pantry', 'Empty trash'),
  emptyTrashTitle: t('pantry', 'Empty trash?'),
  emptyTrashBody: t(
    'pantry',
    'This will permanently delete every note in the trash. This cannot be undone.',
  ),
  trashEmptiedToast: t('pantry', 'Trash emptied'),
  noteRestored: t('pantry', 'Note restored'),
  noteMovedToTrash: t('pantry', 'Note moved to trash'),
  notePermanentlyDeleted: t('pantry', 'Note permanently deleted'),
  restoreFailed: t('pantry', 'Could not restore from trash'),
}

const sortMenuName = computed(() => {
  const label = noteSortOptions.find((o) => o.value === currentSort.value)?.label ?? ''
  return t('pantry', 'Sort by: {value}', { value: label })
})

const toolbarActions = computed<ToolbarAction[]>(() => {
  const actions: ToolbarAction[] = []

  if (!trashMode.value) {
    actions.push({
      key: 'sort',
      type: 'menu',
      label: sortMenuName.value,
      caption: strings.sortLabel,
      icon: SortIcon,
      priority: 5,
      options: noteSortOptions.map((opt) => ({
        key: opt.value,
        label: opt.label,
        active: currentSort.value === opt.value,
        onClick: () => changeNoteSort(opt.value),
      })),
    })
  }

  actions.push({
    key: 'trash',
    label: strings.trashLabel,
    icon: TrashCanIcon,
    variant: trashMode.value ? 'primary' : 'tertiary',
    pressed: trashMode.value,
    priority: 2,
    onClick: toggleTrash,
  })

  if (!trashMode.value && can.value.canCreateNotes) {
    actions.push({
      key: 'new-note',
      label: strings.newNote,
      icon: PlusIcon,
      variant: 'primary',
      priority: 6,
      onClick: openCreateDialog,
    })
  }

  return actions
})
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

  &__trash-bar {
    display: flex;
    justify-content: flex-end;
    padding: 0.5rem 1rem 0.75rem;
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-selection-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  margin: 0 1rem 0.75rem;
  background: var(--color-primary-element-light);
  border-radius: var(--border-radius-large, 12px);
  font-weight: 500;

  span:first-child {
    flex: 1;
  }
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
