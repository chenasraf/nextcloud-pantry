<template>
  <div ref="gridWrapRef" class="pantry-lists">
    <PageToolbar :title="trashMode ? strings.trashTitle : strings.title">
      <template #actions>
        <NcActions v-if="!trashMode" :aria-label="strings.sortLabel" type="tertiary">
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
        <NcButton
          :variant="trashMode ? 'primary' : 'tertiary'"
          :aria-label="strings.trashLabel"
          :title="strings.trashLabel"
          :aria-pressed="trashMode"
          @click="toggleTrash"
        >
          <template #icon>
            <TrashCanIcon :size="20" />
          </template>
          {{ strings.trashLabel }}
        </NcButton>
        <NcButton
          v-if="!trashMode && can.canEditLists"
          variant="primary"
          @click="showCategoryManager = true"
        >
          <template #icon>
            <TagIcon :size="20" />
          </template>
          {{ strings.manageCategories }}
        </NcButton>
        <NcButton
          v-if="!trashMode && can.canCreateLists"
          variant="primary"
          @click="showCreate = true"
        >
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-lists__body">
      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <template v-else-if="trashMode">
        <NcEmptyContent
          v-if="deletedLists.length === 0"
          :name="strings.trashEmptyTitle"
          :description="strings.trashEmptyBody"
        >
          <template #icon>
            <TrashCanIcon />
          </template>
        </NcEmptyContent>
        <template v-else>
          <div class="pantry-lists__trash-bar">
            <NcButton variant="error" @click="confirmingEmptyTrash = true">
              <template #icon><TrashCanIcon :size="20" /></template>
              {{ strings.emptyTrashAction }}
            </NcButton>
          </div>
          <ul class="pantry-lists__grid">
            <li v-for="list in deletedLists" :key="'t-' + list.id" class="pantry-list-card-wrap">
              <div class="pantry-list-card pantry-list-card--trash">
                <span class="pantry-list-card__icon-wrap" :style="iconWrapStyle(list.color)">
                  <component
                    :is="checklistIconComponent(list.icon)"
                    :size="28"
                    class="pantry-list-card__icon"
                  />
                </span>
                <div class="pantry-list-card__body">
                  <h3>{{ list.name }}</h3>
                  <p v-if="list.description">{{ list.description }}</p>
                </div>
              </div>
              <NcActions class="pantry-list-card__actions" :aria-label="strings.listMenu">
                <NcActionButton close-after-click @click="onRestoreList(list)">
                  <template #icon><RestoreIcon :size="20" /></template>
                  {{ strings.restore }}
                </NcActionButton>
                <NcActionButton close-after-click @click="confirmDelete(list)">
                  <template #icon><DeleteIcon :size="20" /></template>
                  {{ strings.deletePermanently }}
                </NcActionButton>
              </NcActions>
            </li>
          </ul>
        </template>
      </template>

      <NcEmptyContent
        v-else-if="lists.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <ClipboardCheckIcon />
        </template>
        <template #action>
          <NcButton v-if="can.canCreateLists" variant="primary" @click="showCreate = true">
            {{ strings.newList }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <ul v-else class="pantry-lists__grid">
        <li class="pantry-list-card-wrap pantry-list-card-wrap--meta">
          <router-link
            :to="{ name: 'all-lists', params: { houseId: String(houseIdNum) } }"
            class="pantry-list-card pantry-list-card--meta"
          >
            <span class="pantry-list-card__icon-wrap pantry-list-card__icon-wrap--meta">
              <ViewListIcon :size="28" class="pantry-list-card__icon" />
            </span>
            <div class="pantry-list-card__body">
              <h3>{{ strings.allListsTitle }}</h3>
              <p>{{ strings.allListsBody }}</p>
            </div>
          </router-link>
        </li>
        <template v-for="item in gridItems" :key="item.key">
          <li
            v-if="item.type === 'placeholder'"
            class="pantry-list-card-placeholder"
            @dragover.prevent
            @drop.prevent.stop="onPlaceholderDrop"
          />
          <li
            v-else
            class="pantry-list-card-wrap"
            :class="{ 'pantry-list-card-wrap--dragging': draggingListId === item.list.id }"
            :data-drag-id="item.list.id"
            :draggable="isCustomSort ? 'true' : 'false'"
            @dragstart="onCardDragStart(item.list.id, $event)"
            @dragend="onCardDragEnd"
            @dragover.prevent="onCardDragOver(item.list.id, $event)"
          >
            <router-link
              :to="{
                name: 'list-detail',
                params: { houseId: String(houseIdNum), listId: String(item.list.id) },
              }"
              class="pantry-list-card"
            >
              <span class="pantry-list-card__icon-wrap" :style="iconWrapStyle(item.list.color)">
                <component
                  :is="checklistIconComponent(item.list.icon)"
                  :size="28"
                  class="pantry-list-card__icon"
                />
              </span>
              <div class="pantry-list-card__body">
                <h3>{{ item.list.name }}</h3>
                <p v-if="item.list.description">{{ item.list.description }}</p>
              </div>
            </router-link>
            <NcActions
              v-if="can.canEditLists || can.canDeleteLists"
              class="pantry-list-card__actions"
              :aria-label="strings.listMenu"
            >
              <NcActionButton
                v-if="can.canEditLists"
                close-after-click
                @click="startEdit(item.list)"
              >
                <template #icon><PencilIcon :size="20" /></template>
                {{ strings.edit }}
              </NcActionButton>
              <NcActionButton
                v-if="can.canDeleteLists"
                close-after-click
                @click="confirmDelete(item.list)"
              >
                <template #icon><DeleteIcon :size="20" /></template>
                {{ strings.remove }}
              </NcActionButton>
            </NcActions>
          </li>
        </template>
      </ul>
    </div>

    <ChecklistFormDialog
      :open="showCreate"
      @update:open="showCreate = $event"
      @save="submitCreate"
    />

    <ChecklistFormDialog
      :open="!!editing"
      :list="editing"
      @update:open="(v) => !v && (editing = null)"
      @save="submitEdit"
    />

    <NcDialog
      v-if="deleting"
      :name="deleteDialogTitle"
      :open="!!deleting"
      close-on-click-outside
      @update:open="(v) => !v && (deleting = null)"
    >
      <p>{{ deleteConfirmBody }}</p>
      <template #actions>
        <NcButton @click="deleting = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDelete">{{ deleteDialogAction }}</NcButton>
      </template>
    </NcDialog>

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

    <CategoryManagerDialog
      :open="showCategoryManager"
      :house-id="houseIdNum"
      @update:open="showCategoryManager = $event"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import { showInfo, showUndo, showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PageToolbar from '@/components/PageToolbar'
import { CategoryManagerDialog } from '@/components/CategoryManager'
import PlusIcon from '@icons/Plus.vue'
import TagIcon from '@icons/Tag.vue'
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import SortIcon from '@icons/Sort.vue'
import TrashCanIcon from '@icons/TrashCan.vue'
import RestoreIcon from '@icons/Restore.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import ViewListIcon from '@icons/ViewList.vue'
import type { Checklist } from '@/api/types'
import type { ChecklistSort } from '@/api/prefs'
import { getChecklistSort, setChecklistSort } from '@/api/prefs'
import { useChecklists } from '@/composables/useChecklist'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useTouchReorder } from '@/composables/useTouchReorder'
import {
  checklistIconComponent,
  ChecklistFormDialog,
  contrastColor,
} from '@/components/ChecklistIconPicker'

function iconWrapStyle(color: string | null) {
  if (!color) return undefined
  return { background: color, color: contrastColor(color) }
}

const props = defineProps<{ houseId: string }>()
const router = useRouter()

const houseIdNum = computed(() => Number(props.houseId))
const {
  lists,
  deletedLists,
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
  sortBy,
  trashMode,
} = useChecklists(houseIdNum.value)

const { can } = useCurrentHouse()

async function toggleTrash() {
  trashMode.value = !trashMode.value
  await refresh()
}

async function refresh() {
  if (trashMode.value) {
    await loadDeleted()
  } else {
    await load(true)
  }
}

const confirmingEmptyTrash = ref(false)

async function submitEmptyTrash() {
  confirmingEmptyTrash.value = false
  await emptyTrash()
  showInfo(strings.trashEmptiedToast)
}

async function onRestoreList(list: Checklist) {
  await restore(list.id)
  showInfo(strings.listRestored)
}

// ----- Sort -----

const currentSort = ref<ChecklistSort>('custom')
const isCustomSort = computed(() => currentSort.value === 'custom')

const sortOptions: { value: ChecklistSort; label: string }[] = [
  { value: 'name_asc', label: t('pantry', 'Name A–Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z–A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPref() {
  const prefs = await getChecklistSort(houseIdNum.value)
  currentSort.value = prefs.sort
  sortBy.value = prefs.sort
}

async function changeSort(value: ChecklistSort) {
  currentSort.value = value
  sortBy.value = value
  await setChecklistSort(houseIdNum.value, value)
  await load(value)
}

onMounted(async () => {
  await loadSortPref()
  await load(true)
})
watch(
  () => props.houseId,
  async () => {
    await loadSortPref()
    await load(true)
  },
)

const showCategoryManager = ref(false)

const showCreate = ref(false)

async function submitCreate(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const list = await create(data.name, data.description || null, data.icon, data.color || null)
  showCreate.value = false
  await router.push({
    name: 'list-detail',
    params: { houseId: String(houseIdNum.value), listId: String(list.id) },
  })
}

const editing = ref<Checklist | null>(null)

function startEdit(list: Checklist) {
  editing.value = list
}

async function submitEdit(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const target = editing.value
  if (!target) return
  await update(target.id, {
    name: data.name,
    description: data.description,
    icon: data.icon,
    color: data.color || null,
  })
  editing.value = null
}

const deleting = ref<Checklist | null>(null)
const deleteConfirmBody = computed(() => {
  const name = deleting.value?.name ?? ''
  if (trashMode.value) {
    return t(
      'pantry',
      'Permanently delete {name}? Every item in this list will also be erased. This cannot be undone.',
      { name },
    )
  }
  return t('pantry', 'Move {name} to the trash? You can restore it later.', { name })
})
const deleteDialogTitle = computed(() =>
  trashMode.value ? strings.deletePermanentlyDialogTitle : strings.deleteDialogTitle,
)
const deleteDialogAction = computed(() =>
  trashMode.value ? strings.deletePermanently : strings.remove,
)

function confirmDelete(list: Checklist) {
  deleting.value = list
}

async function submitDelete() {
  const target = deleting.value
  if (!target) return
  if (trashMode.value) {
    await removePermanently(target.id)
    deleting.value = null
    showInfo(strings.listPermanentlyDeleted)
    return
  }
  const id = target.id
  await remove(id)
  deleting.value = null
  showUndo(
    strings.listMovedToTrash,
    () => {
      void restore(id).catch(() => showError(strings.restoreFailed))
    },
    { timeout: 6000 },
  )
}

// ----- Reorder (custom sort only) -----

type GridItem =
  | { type: 'list'; key: string; list: Checklist }
  | { type: 'placeholder'; key: string }

const draggingListId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
const gridWrapRef = ref<HTMLElement | null>(null)

function buildGridItems(): GridItem[] {
  if (draggingListId.value === null || dropIndex.value === null) {
    return lists.value.map((l) => ({ type: 'list' as const, key: 'l-' + l.id, list: l }))
  }
  const without = lists.value.filter((l) => l.id !== draggingListId.value)
  const clamped = Math.max(0, Math.min(dropIndex.value, without.length))
  const items: GridItem[] = without.map((l) => ({
    type: 'list' as const,
    key: 'l-' + l.id,
    list: l,
  }))
  items.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return items
}

const gridItems = computed(() => buildGridItems())

function onCardDragStart(listId: number, e: DragEvent) {
  if (!isCustomSort.value || !e.dataTransfer) return
  draggingListId.value = listId
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/x-pantry-list', String(listId))
}

function onCardDragEnd() {
  draggingListId.value = null
  dropIndex.value = null
}

function computeDropIndex(hoveredListId: number, clientX: number, target: HTMLElement | null) {
  const dragId = draggingListId.value
  if (dragId === null || dragId === hoveredListId) return
  const without = lists.value.filter((l) => l.id !== dragId)
  const idx = without.findIndex((l) => l.id === hoveredListId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientX > rect.left + rect.width / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onCardDragOver(hoveredListId: number, e: DragEvent) {
  if (!isCustomSort.value) return
  if (!e.dataTransfer?.types.includes('application/x-pantry-list')) return
  computeDropIndex(hoveredListId, e.clientX, e.currentTarget as HTMLElement | null)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingListId.value
  const idx = dropIndex.value
  draggingListId.value = null
  dropIndex.value = null

  if (dragId === null || idx === null) return

  const dragged = lists.value.find((l) => l.id === dragId)
  if (!dragged) return

  const without = lists.value.filter((l) => l.id !== dragId)
  const clampedIndex = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clampedIndex, 0, dragged)

  const items = reordered.map((l, i) => ({ id: l.id, sortOrder: i }))
  await reorder(items)
}

function onDropCapture() {
  commitReorder()
}
function onDragEndCapture() {
  draggingListId.value = null
  dropIndex.value = null
}
onMounted(() => {
  gridWrapRef.value?.addEventListener('drop', onDropCapture, true)
  gridWrapRef.value?.addEventListener('dragend', onDragEndCapture, true)
})
onBeforeUnmount(() => {
  gridWrapRef.value?.removeEventListener('drop', onDropCapture, true)
  gridWrapRef.value?.removeEventListener('dragend', onDragEndCapture, true)
})

useTouchReorder(
  gridWrapRef,
  {
    onDragStart(id) {
      draggingListId.value = id
      dropIndex.value = null
    },
    onReorderOver(hoveredId, clientX) {
      const el =
        gridWrapRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeDropIndex(hoveredId, clientX, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingListId.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

const strings = {
  title: t('pantry', 'Checklists'),
  trashTitle: t('pantry', 'Checklists trash'),
  newList: t('pantry', 'New list'),
  manageCategories: t('pantry', 'Manage categories'),
  cancel: t('pantry', 'Cancel'),
  edit: t('pantry', 'Edit'),
  remove: t('pantry', 'Remove'),
  deletePermanently: t('pantry', 'Delete permanently'),
  restore: t('pantry', 'Restore'),
  listMenu: t('pantry', 'List actions'),
  deleteDialogTitle: t('pantry', 'Remove checklist'),
  deletePermanentlyDialogTitle: t('pantry', 'Delete checklist permanently'),
  emptyTitle: t('pantry', 'No lists yet'),
  emptyBody: t('pantry', 'Create your first checklist to start adding items.'),
  allListsTitle: t('pantry', 'All lists'),
  allListsBody: t('pantry', 'Items from every list in this house.'),
  sortLabel: t('pantry', 'Sort order'),
  trashLabel: t('pantry', 'Trash'),
  trashEmptyTitle: t('pantry', 'Trash is empty'),
  trashEmptyBody: t('pantry', 'Deleted checklists will appear here.'),
  emptyTrashAction: t('pantry', 'Empty trash'),
  emptyTrashTitle: t('pantry', 'Empty trash?'),
  emptyTrashBody: t(
    'pantry',
    'This will permanently delete every checklist in the trash, along with all of their items. This cannot be undone.',
  ),
  trashEmptiedToast: t('pantry', 'Trash emptied'),
  listRestored: t('pantry', 'Checklist restored'),
  listMovedToTrash: t('pantry', 'Checklist moved to trash'),
  listPermanentlyDeleted: t('pantry', 'Checklist permanently deleted'),
  restoreFailed: t('pantry', 'Could not restore from trash'),
}
</script>

<style scoped lang="scss">
.pantry-lists {
  &__body {
    max-width: 1100px;
    margin: 0 auto;
  }

  &__grid {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }

  &__trash-bar {
    display: flex;
    justify-content: flex-end;
    padding: 0.5rem 0 0.75rem;
  }
}

.pantry-list-card-wrap {
  position: relative;
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;

  &__actions,
  .pantry-list-card__actions {
    position: absolute;
    top: 0.5rem;
    inset-inline-end: 0.5rem;
    z-index: 1;
  }

  &--dragging {
    opacity: 0.35;
  }
}

.pantry-list-card-placeholder {
  min-height: 80px;
  border: 3px dashed var(--color-primary-element);
  border-radius: var(--border-radius-large, 12px);
  background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
  list-style: none;
}

.pantry-list-card {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  padding-inline-end: 3rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  background: var(--color-main-background);
  color: inherit;
  text-decoration: none;
  transition: background-color 0.15s ease;

  &--meta {
    border-style: dashed;
    background: var(--color-background-hover);
    padding-inline-end: 1rem;
  }

  &:hover,
  &:focus-visible {
    background: var(--color-background-hover);
  }

  &__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);

    &--meta {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }
  }

  &__icon {
    color: inherit;
  }

  &__body {
    flex: 1;
    min-width: 0;

    h3 {
      margin: 0 0 4px 0;
      font-size: 1.05rem;
    }

    p {
      margin: 0;
      color: var(--color-text-maxcontrast);
      font-size: 0.9rem;
    }
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
