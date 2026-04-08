<template>
  <div
    ref="boardRef"
    class="pantry-photos"
    @dragenter.prevent="onBoardDragEnter"
    @dragover.prevent
    @dragleave="onBoardDragLeave"
    @drop.prevent="onBoardDrop"
  >
    <PageToolbar :title="activeFolderId ? activeFolder?.name : strings.title">
      <template v-if="activeFolderId" #before-title>
        <NcButton variant="tertiary" :aria-label="strings.back" @click="navigateToFolder(null)">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
        </NcButton>
      </template>
      <template #actions>
        <NcActions :aria-label="strings.sortLabel" type="tertiary">
          <template #icon>
            <SortIcon :size="20" />
          </template>
          <NcActionCheckbox :checked="foldersFirst" @update:checked="toggleFoldersFirst">
            {{ strings.foldersFirst }}
          </NcActionCheckbox>
          <NcActionSeparator />
          <NcActionButton
            v-for="opt in photoSortOptions"
            :key="opt.value"
            :class="{ 'pantry-sort-active': sortPrefs.sort === opt.value }"
            @click="changePhotoSort(opt.value)"
          >
            <template #icon>
              <RadioboxMarkedIcon v-if="sortPrefs.sort === opt.value" :size="20" />
              <RadioboxBlankIcon v-else :size="20" />
            </template>
            {{ opt.label }}
          </NcActionButton>
        </NcActions>
        <NcButton v-if="!activeFolderId" @click="showFolderDialog = true">
          <template #icon>
            <FolderPlusIcon :size="20" />
          </template>
          {{ strings.newFolder }}
        </NcButton>
        <NcButton variant="primary" @click="triggerUpload">
          <template #icon>
            <UploadIcon :size="20" />
          </template>
          {{ strings.upload }}
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-photos__body">
      <!-- Drop zone overlay -->
      <div v-if="isFileDragOver" class="pantry-photos__drop-overlay">
        <UploadIcon :size="48" />
        <p>{{ strings.dropToUpload }}</p>
      </div>

      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <!-- Root view -->
      <template v-else-if="!activeFolderId">
        <NcEmptyContent
          v-if="folders.length === 0 && rootPhotos.length === 0 && rootUploads.length === 0"
          :name="strings.emptyTitle"
          :description="strings.emptyBody"
        >
          <template #icon>
            <ImageIcon />
          </template>
          <template #action>
            <NcButton variant="primary" @click="triggerUpload">
              {{ strings.upload }}
            </NcButton>
          </template>
        </NcEmptyContent>

        <div v-else class="pantry-photos__grid">
          <template v-if="foldersFirst">
            <FolderStack
              v-for="folder in folders"
              :key="'f-' + folder.id"
              :folder="folder"
              :photos="photosInFolder(folder.id)"
              :house-id="houseIdNum"
              @open="(f) => navigateToFolder(f.id)"
              @rename="startRenameFolder(folder)"
              @delete="confirmDeleteFolder(folder)"
              @drop-photo="onDropPhotoToFolder"
              @drop-files="onDropFilesToFolder"
              @drag-over-change="onFolderDragOverChange"
            />
          </template>
          <template v-for="item in rootGridItems" :key="item.key">
            <div
              v-if="item.type === 'placeholder'"
              class="pantry-photos__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <div v-else-if="item.type === 'upload'" class="pantry-photos__upload-card">
              <NcProgressBar :value="item.progress" size="medium" />
              <span class="pantry-photos__upload-name">{{ item.fileName }}</span>
            </div>
            <FolderStack
              v-else-if="item.type === 'folder'"
              :folder="item.folder"
              :photos="photosInFolder(item.folder.id)"
              :house-id="houseIdNum"
              @open="(f) => navigateToFolder(f.id)"
              @rename="startRenameFolder(item.folder)"
              @delete="confirmDeleteFolder(item.folder)"
              @drop-photo="onDropPhotoToFolder"
              @drop-files="onDropFilesToFolder"
              @drag-over-change="onFolderDragOverChange"
            />
            <PhotoCard
              v-else
              :photo="item.photo"
              :house-id="houseIdNum"
              :reorder-enabled="isCustomSort"
              @preview="openPreview"
              @edit="startEditPhoto"
              @delete="confirmDeletePhoto"
              @move-to-root="movePhotoToRoot"
              @drag-start="onPhotoDragStart"
              @reorder-over="(id, e) => onReorderOver(id, rootPhotos, e)"
            />
          </template>
        </div>
      </template>

      <!-- Folder view -->
      <template v-else>
        <NcEmptyContent
          v-if="activeFolderPhotos.length === 0 && folderUploads.length === 0"
          :name="strings.folderEmpty"
          :description="strings.folderEmptyBody"
        >
          <template #icon>
            <ImageIcon />
          </template>
          <template #action>
            <NcButton variant="primary" @click="triggerUpload">
              {{ strings.upload }}
            </NcButton>
          </template>
        </NcEmptyContent>

        <div v-else class="pantry-photos__grid">
          <template v-for="(item, i) in folderGridItems" :key="item.key">
            <div
              v-if="item.type === 'placeholder'"
              class="pantry-photos__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <div v-else-if="item.type === 'upload'" class="pantry-photos__upload-card">
              <NcProgressBar :value="item.progress" size="medium" />
              <span class="pantry-photos__upload-name">{{ item.fileName }}</span>
            </div>
            <PhotoCard
              v-else-if="item.type === 'photo'"
              :photo="item.photo"
              :house-id="houseIdNum"
              :reorder-enabled="isCustomSort"
              @preview="openPreview"
              @edit="startEditPhoto"
              @delete="confirmDeletePhoto"
              @move-to-root="movePhotoToRoot"
              @drag-start="onPhotoDragStart"
              @reorder-over="(id, e) => onReorderOver(id, activeFolderPhotos, e)"
            />
          </template>
        </div>
      </template>
    </div>

    <!-- Hidden file input -->
    <input
      ref="fileInputRef"
      type="file"
      accept="image/*"
      multiple
      class="pantry-hidden-input"
      @change="onFilesSelected"
    />

    <!-- Preview dialog -->
    <PhotoPreview
      v-if="previewing"
      :open="!!previewing"
      :photo="previewing"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (previewing = null)"
    />

    <!-- Folder create/rename dialog -->
    <FolderDialog
      v-if="showFolderDialog || renamingFolder"
      :open="showFolderDialog || !!renamingFolder"
      :folder="renamingFolder"
      @update:open="closeFolderDialog"
      @save="submitFolderDialog"
    />

    <!-- Edit photo caption -->
    <NcDialog
      v-if="editingPhoto"
      :name="strings.editPhotoTitle"
      :open="!!editingPhoto"
      close-on-click-outside
      @update:open="(v) => !v && (editingPhoto = null)"
    >
      <form
        id="pantry-edit-photo-form"
        class="pantry-form"
        autocomplete="off"
        @submit.prevent="submitEditPhoto"
      >
        <NcTextField
          v-model="editCaption"
          :label="strings.captionLabel"
          :placeholder="strings.captionPlaceholder"
          autocomplete="off"
        />
      </form>
      <template #actions>
        <NcButton @click="editingPhoto = null">{{ strings.cancel }}</NcButton>
        <NcButton form="pantry-edit-photo-form" type="submit" variant="primary">
          {{ strings.save }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Delete photo confirm -->
    <NcDialog
      v-if="deletingPhoto"
      :name="strings.deletePhotoTitle"
      :open="!!deletingPhoto"
      close-on-click-outside
      @update:open="(v) => !v && (deletingPhoto = null)"
    >
      <p>{{ strings.deletePhotoBody }}</p>
      <template #actions>
        <NcButton @click="deletingPhoto = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDeletePhoto">{{ strings.delete }}</NcButton>
      </template>
    </NcDialog>

    <!-- Delete folder confirm -->
    <NcDialog
      v-if="deletingFolder"
      :name="strings.deleteFolderTitle"
      :open="!!deletingFolder"
      close-on-click-outside
      @update:open="(v) => !v && (deletingFolder = null)"
    >
      <p>{{ deleteFolderBody }}</p>
      <template #actions>
        <NcButton @click="deletingFolder = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDeleteFolder">{{ strings.delete }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import PageToolbar from '@/components/PageToolbar'
import { PhotoCard, FolderStack, FolderDialog, PhotoPreview } from '@/components/Photos'
import UploadIcon from '@icons/Upload.vue'
import ImageIcon from '@icons/Image.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import FolderPlusIcon from '@icons/FolderPlus.vue'
import SortIcon from '@icons/Sort.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import type { Photo, PhotoFolder } from '@/api/types'
import type { PhotoSort, PhotoSortPrefs } from '@/api/prefs'
import { getPhotoSort, setPhotoSort } from '@/api/prefs'
import { usePhotos, type UploadEntry } from '@/composables/usePhotos'
import { useTouchReorder } from '@/composables/useTouchReorder'

const props = defineProps<{ houseId: string; folderId?: string }>()
const router = useRouter()

const houseIdNum = computed(() => Number(props.houseId))
const {
  folders,
  loading,
  load,
  rootPhotos,
  photosInFolder,
  upload,
  updatePhoto,
  removePhoto,
  reorderPhotos,
  createFolder,
  updateFolder,
  removeFolder,
  uploads,
  sortBy,
} = usePhotos(houseIdNum.value)

// ----- Sort -----

const sortPrefs = reactive<PhotoSortPrefs>({ sort: 'custom', foldersFirst: true })
const foldersFirst = computed(() => sortPrefs.foldersFirst)
const isCustomSort = computed(() => sortPrefs.sort === 'custom')

const photoSortOptions: { value: PhotoSort; label: string }[] = [
  { value: 'newest', label: t('pantry', 'Newest first') },
  { value: 'oldest', label: t('pantry', 'Oldest first') },
  { value: 'description_asc', label: t('pantry', 'By description A\u2013Z') },
  { value: 'description_desc', label: t('pantry', 'By description Z\u2013A') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

async function loadSortPrefs() {
  const prefs = await getPhotoSort(houseIdNum.value)
  sortPrefs.sort = prefs.sort
  sortPrefs.foldersFirst = prefs.foldersFirst
  sortBy.value = prefs.sort
}

async function changePhotoSort(value: PhotoSort) {
  sortPrefs.sort = value
  sortBy.value = value
  await setPhotoSort(houseIdNum.value, { sort: value })
  await load(value)
}

async function toggleFoldersFirst(value: boolean) {
  sortPrefs.foldersFirst = value
  await setPhotoSort(houseIdNum.value, { foldersFirst: value })
}

onMounted(async () => {
  await loadSortPrefs()
  await load()
})
watch(
  () => props.houseId,
  async () => {
    await loadSortPrefs()
    await load()
  },
)

// ----- State -----

const activeFolderId = computed(() => (props.folderId ? Number(props.folderId) : null))
const activeFolder = computed(
  () => folders.value.find((f) => f.id === activeFolderId.value) ?? null,
)
const activeFolderPhotos = computed(() =>
  activeFolderId.value ? photosInFolder(activeFolderId.value) : [],
)

function navigateToFolder(folderId: number | null) {
  if (folderId) {
    router.push({
      name: 'photo-folder',
      params: { houseId: props.houseId, folderId: String(folderId) },
    })
  } else {
    router.push({
      name: 'photos',
      params: { houseId: props.houseId },
    })
  }
}

// ----- Reorder state -----

type GridItem =
  | { type: 'photo'; key: string; photo: Photo }
  | { type: 'folder'; key: string; folder: PhotoFolder }
  | { type: 'placeholder'; key: string }
  | { type: 'upload'; key: string; fileName: string; progress: number }

const draggingPhotoId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)

function buildGridItems(
  source: Photo[],
  activeUploads: UploadEntry[],
  mixedFolders?: PhotoFolder[],
): GridItem[] {
  // Upload placeholders go first (newest-first sort means in-progress uploads are at the top).
  const uploadItems: GridItem[] = activeUploads.map((u) => ({
    type: 'upload' as const,
    key: u.id,
    fileName: u.fileName,
    progress: u.progress,
  }))

  const folderItems: GridItem[] = (mixedFolders ?? []).map((f) => ({
    type: 'folder' as const,
    key: 'f-' + f.id,
    folder: f,
  }))

  const dragId = draggingPhotoId.value
  if (dragId === null || dropIndex.value === null || !isCustomSort.value) {
    const photoItems: GridItem[] = source.map((p) => ({
      type: 'photo' as const,
      key: 'p-' + p.id,
      photo: p,
    }))
    return [...uploadItems, ...folderItems, ...photoItems]
  }

  const without = source.filter((p) => p.id !== dragId)
  const items: GridItem[] = without.map((p) => ({
    type: 'photo' as const,
    key: 'p-' + p.id,
    photo: p,
  }))

  const clampedIndex = Math.min(dropIndex.value, items.length)
  items.splice(clampedIndex, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return [...uploadItems, ...folderItems, ...items]
}

const rootUploads = computed(() => uploads.value.filter((u) => u.folderId === null))
const folderUploads = computed(() =>
  uploads.value.filter((u) => u.folderId === activeFolderId.value),
)

const rootGridItems = computed(() =>
  buildGridItems(
    rootPhotos.value,
    rootUploads.value,
    foldersFirst.value ? undefined : folders.value,
  ),
)
const folderGridItems = computed(() =>
  buildGridItems(activeFolderPhotos.value, folderUploads.value),
)

function onPhotoDragStart(photoId: number) {
  draggingPhotoId.value = photoId
  dropIndex.value = null
}

function computePhotoDropIndex(
  hoveredPhotoId: number,
  source: Photo[],
  clientX: number,
  target: HTMLElement | null,
) {
  const dragId = draggingPhotoId.value
  if (!dragId || dragId === hoveredPhotoId) return

  const without = source.filter((p) => p.id !== dragId)
  const idx = without.findIndex((p) => p.id === hoveredPhotoId)
  if (idx === -1) return

  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientX > rect.left + rect.width / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onReorderOver(hoveredPhotoId: number, source: Photo[], e: MouseEvent) {
  computePhotoDropIndex(hoveredPhotoId, source, e.clientX, e.currentTarget as HTMLElement | null)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingPhotoId.value
  const idx = dropIndex.value
  draggingPhotoId.value = null
  dropIndex.value = null

  if (dragId === null || idx === null) return

  const source = activeFolderId.value ? activeFolderPhotos.value : rootPhotos.value
  const dragged = source.find((p) => p.id === dragId)
  if (!dragged) return

  const without = source.filter((p) => p.id !== dragId)
  const clampedIndex = Math.min(idx, without.length)
  const reordered = [...without]
  reordered.splice(clampedIndex, 0, dragged)

  const items = reordered.map((p, i) => ({ id: p.id, sortOrder: i }))
  await reorderPhotos(items)
}

const previewing = ref<Photo | null>(null)
const isFileDragOver = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
const boardRef = ref<HTMLElement | null>(null)

// Capture-phase listeners for drag state management
function onDropCapture() {
  isFileDragOver.value = false
  dragCounter = 0
}
function onDragEndCapture() {
  draggingPhotoId.value = null
  dropIndex.value = null
}
onMounted(() => {
  boardRef.value?.addEventListener('drop', onDropCapture, true)
  boardRef.value?.addEventListener('dragend', onDragEndCapture, true)
})
onBeforeUnmount(() => {
  boardRef.value?.removeEventListener('drop', onDropCapture, true)
  boardRef.value?.removeEventListener('dragend', onDragEndCapture, true)
})

// ----- Touch reorder -----
useTouchReorder(
  boardRef,
  {
    onDragStart: onPhotoDragStart,
    onReorderOver(hoveredId, clientX) {
      const source = activeFolderId.value ? activeFolderPhotos.value : rootPhotos.value
      const el = boardRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`)
      computePhotoDropIndex(hoveredId, source, clientX, el)
    },
    onDrop: commitReorder,
    onCancel() {
      draggingPhotoId.value = null
      dropIndex.value = null
    },
  },
  isCustomSort,
)

// Folder dialog
const showFolderDialog = ref(false)
const renamingFolder = ref<PhotoFolder | null>(null)

// Delete confirmations
const deletingPhoto = ref<Photo | null>(null)
const deletingFolder = ref<PhotoFolder | null>(null)
const deleteFolderBody = computed(() =>
  t(
    'pantry',
    'Are you sure you want to delete the folder "{name}"? Photos will be moved to the board.',
    { name: deletingFolder.value?.name ?? '' },
  ),
)

// ----- Upload -----

function triggerUpload() {
  fileInputRef.value?.click()
}

async function onFilesSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files) return
  for (const file of Array.from(files)) {
    await upload(file, activeFolderId.value)
  }
  input.value = ''
}

// ----- Drag and drop (file upload) -----

let dragCounter = 0
const folderHasDrag = ref(false)

function onFolderDragOverChange(isOver: boolean) {
  folderHasDrag.value = isOver
  if (isOver) {
    isFileDragOver.value = false
  }
}

function onBoardDragEnter(e: DragEvent) {
  dragCounter++
  if (e.dataTransfer?.types.includes('Files') && !folderHasDrag.value) {
    isFileDragOver.value = true
  }
}

function onBoardDragLeave() {
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    isFileDragOver.value = false
  }
}

async function onBoardDrop(e: DragEvent) {
  isFileDragOver.value = false
  dragCounter = 0
  if (!e.dataTransfer) return

  // External file drop
  if (e.dataTransfer.files.length > 0) {
    for (const file of Array.from(e.dataTransfer.files)) {
      await upload(file, activeFolderId.value)
    }
    return
  }

  // Internal photo reorder or move
  const photoData = e.dataTransfer.getData('application/x-pantry-photo')
  if (photoData) {
    // If there's a pending reorder, commit it
    if (draggingPhotoId.value && dropIndex.value !== null) {
      await commitReorder()
      return
    }
    // Otherwise handle move-to-root
    if (!activeFolderId.value) {
      try {
        const { id, folderId } = JSON.parse(photoData)
        if (folderId !== null) {
          await updatePhoto(id, { folderId: 0 })
        }
      } catch {
        // ignore
      }
    }
    draggingPhotoId.value = null
    dropIndex.value = null
  }
}

// ----- Photo actions -----

function openPreview(photo: Photo) {
  previewing.value = photo
}

// Edit caption
const editingPhoto = ref<Photo | null>(null)
const editCaption = ref('')

function startEditPhoto(photo: Photo) {
  editingPhoto.value = photo
  editCaption.value = photo.caption ?? ''
}

async function submitEditPhoto() {
  if (!editingPhoto.value) return
  await updatePhoto(editingPhoto.value.id, { caption: editCaption.value })
  editingPhoto.value = null
}

function confirmDeletePhoto(photo: Photo) {
  deletingPhoto.value = photo
}

async function submitDeletePhoto() {
  if (!deletingPhoto.value) return
  await removePhoto(deletingPhoto.value.id)
  deletingPhoto.value = null
}

async function movePhotoToRoot(photo: Photo) {
  await updatePhoto(photo.id, { folderId: 0 })
}

async function onDropPhotoToFolder(photoId: number, folderId: number) {
  draggingPhotoId.value = null
  dropIndex.value = null
  await updatePhoto(photoId, { folderId })
}

async function onDropFilesToFolder(files: File[], folderId: number) {
  for (const file of files) {
    await upload(file, folderId)
  }
}

// ----- Folder actions -----

function startRenameFolder(folder: PhotoFolder) {
  renamingFolder.value = folder
}

function closeFolderDialog(v: boolean) {
  if (!v) {
    showFolderDialog.value = false
    renamingFolder.value = null
  }
}

async function submitFolderDialog(name: string) {
  if (renamingFolder.value) {
    await updateFolder(renamingFolder.value.id, { name })
    renamingFolder.value = null
  } else {
    await createFolder(name)
    showFolderDialog.value = false
  }
}

function confirmDeleteFolder(folder: PhotoFolder) {
  deletingFolder.value = folder
}

async function submitDeleteFolder() {
  if (!deletingFolder.value) return
  await removeFolder(deletingFolder.value.id)
  deletingFolder.value = null
}

const strings = {
  title: t('pantry', 'Photo board'),
  upload: t('pantry', 'Upload'),
  newFolder: t('pantry', 'New folder'),
  back: t('pantry', 'Back'),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  delete: t('pantry', 'Delete'),
  editPhotoTitle: t('pantry', 'Edit photo'),
  captionLabel: t('pantry', 'Caption'),
  captionPlaceholder: t('pantry', 'Add a description'),
  emptyTitle: t('pantry', 'No photos yet'),
  emptyBody: t('pantry', 'Upload your first photo or drag and drop images here.'),
  folderEmpty: t('pantry', 'This folder is empty'),
  folderEmptyBody: t('pantry', 'Upload photos or drag them into this folder.'),
  dropToUpload: t('pantry', 'Drop files to upload'),
  deletePhotoTitle: t('pantry', 'Delete photo'),
  deletePhotoBody: t('pantry', 'Are you sure you want to delete this photo?'),
  deleteFolderTitle: t('pantry', 'Delete folder'),
  sortLabel: t('pantry', 'Sort order'),
  foldersFirst: t('pantry', 'Folders first'),
}
</script>

<style scoped lang="scss">
.pantry-photos {
  position: relative;
  min-height: 100%;

  &__body {
    max-width: 1100px;
    margin: 0 auto;
    position: relative;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
    padding: 0 1rem 1rem;
  }

  &__placeholder {
    aspect-ratio: 1;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius-large, 12px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
  }

  &__upload-card {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 12px);
    background: var(--color-background-dark);
    padding: 1rem;
  }

  &__upload-name {
    font-size: 0.75rem;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    text-align: center;
  }

  &__drop-overlay {
    position: absolute;
    inset: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.1);
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius-large, 12px);
    color: var(--color-primary-element);
    font-size: 1.1rem;
    font-weight: 600;
    pointer-events: none;
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-hidden-input {
  position: absolute;
  width: 0;
  height: 0;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
