<template>
  <div
    ref="wallRef"
    class="pantry-photos"
    @dragenter.prevent="onWallDragEnter"
    @dragover.prevent
    @dragleave="onWallDragLeave"
    @drop.prevent="onWallDrop"
  >
    <PageToolbar :title="activeFolderId ? activeFolder?.name : strings.title">
      <template v-if="activeFolderId" #before-title>
        <NcButton variant="tertiary" :aria-label="strings.back" @click="navigateToFolder(null)">
          <template #icon><ArrowLeftIcon :size="20" /></template>
        </NcButton>
      </template>
      <template #actions>
        <NcButton v-if="!activeFolderId" @click="showFolderDialog = true">
          <template #icon><FolderPlusIcon :size="20" /></template>
          {{ strings.newFolder }}
        </NcButton>
        <NcButton variant="primary" @click="triggerUpload">
          <template #icon><UploadIcon :size="20" /></template>
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
          v-if="folders.length === 0 && rootPhotos.length === 0"
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
          <FolderStack
            v-for="folder in folders"
            :key="'f-' + folder.id"
            :folder="folder"
            :photos="photosInFolder(folder.id)"
            @open="(f) => navigateToFolder(f.id)"
            @rename="startRenameFolder(folder)"
            @delete="confirmDeleteFolder(folder)"
            @drop-photo="onDropPhotoToFolder"
            @drop-files="onDropFilesToFolder"
          />
          <template v-for="(item, i) in rootGridItems" :key="item.key">
            <div
              v-if="item.type === 'placeholder'"
              class="pantry-photos__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <PhotoCard
              v-else
              :photo="item.photo"
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
          v-if="activeFolderPhotos.length === 0"
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
            <PhotoCard
              v-else
              :photo="item.photo"
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PageToolbar from '@/components/PageToolbar'
import { PhotoCard, FolderStack, FolderDialog, PhotoPreview } from '@/components/PhotoWall'
import UploadIcon from '@icons/Upload.vue'
import ImageIcon from '@icons/Image.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import FolderPlusIcon from '@icons/FolderPlus.vue'
import type { Photo, PhotoFolder } from '@/api/types'
import { usePhotoWall } from '@/composables/usePhotoWall'

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
} = usePhotoWall(houseIdNum.value)

onMounted(load)
watch(
  () => props.houseId,
  () => load(),
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

type GridItem = { type: 'photo'; key: string; photo: Photo } | { type: 'placeholder'; key: string }

const draggingPhotoId = ref<number | null>(null)
const dropIndex = ref<number | null>(null)

function buildGridItems(source: Photo[]): GridItem[] {
  const dragId = draggingPhotoId.value
  if (dragId === null || dropIndex.value === null) {
    return source.map((p) => ({ type: 'photo' as const, key: 'p-' + p.id, photo: p }))
  }

  const without = source.filter((p) => p.id !== dragId)
  const items: GridItem[] = without.map((p) => ({
    type: 'photo' as const,
    key: 'p-' + p.id,
    photo: p,
  }))

  const clampedIndex = Math.min(dropIndex.value, items.length)
  items.splice(clampedIndex, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return items
}

const rootGridItems = computed(() => buildGridItems(rootPhotos.value))
const folderGridItems = computed(() => buildGridItems(activeFolderPhotos.value))

function onPhotoDragStart(photoId: number) {
  draggingPhotoId.value = photoId
  dropIndex.value = null
}

function onReorderOver(hoveredPhotoId: number, source: Photo[], e: MouseEvent) {
  const dragId = draggingPhotoId.value
  if (!dragId || dragId === hoveredPhotoId) return

  const without = source.filter((p) => p.id !== dragId)
  const idx = without.findIndex((p) => p.id === hoveredPhotoId)
  if (idx === -1) return

  // Determine if cursor is in the left or right half of the hovered card
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
const wallRef = ref<HTMLElement | null>(null)

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
  wallRef.value?.addEventListener('drop', onDropCapture, true)
  wallRef.value?.addEventListener('dragend', onDragEndCapture, true)
})
onBeforeUnmount(() => {
  wallRef.value?.removeEventListener('drop', onDropCapture, true)
  wallRef.value?.removeEventListener('dragend', onDragEndCapture, true)
})

// Folder dialog
const showFolderDialog = ref(false)
const renamingFolder = ref<PhotoFolder | null>(null)

// Delete confirmations
const deletingPhoto = ref<Photo | null>(null)
const deletingFolder = ref<PhotoFolder | null>(null)
const deleteFolderBody = computed(() =>
  t(
    'pantry',
    'Are you sure you want to delete the folder "{name}"? Photos will be moved to the wall.',
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

function onWallDragEnter(e: DragEvent) {
  dragCounter++
  if (e.dataTransfer?.types.includes('Files')) {
    isFileDragOver.value = true
  }
}

function onWallDragLeave() {
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    isFileDragOver.value = false
  }
}

async function onWallDrop(e: DragEvent) {
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
  title: t('pantry', 'Photo wall'),
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
</style>
