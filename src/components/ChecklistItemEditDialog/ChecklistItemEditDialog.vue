<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <form
      id="pantry-edit-item-form"
      class="edit-item-form"
      autocomplete="off"
      @submit.prevent="submitEdit"
    >
      <NcTextField
        v-model="editName"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <AutoResizeTextarea
        v-model="editDescription"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
        dir="auto"
        autocomplete="off"
      />
      <NcTextField
        v-model="editQuantity"
        :label="strings.quantityLabel"
        :placeholder="strings.quantityPlaceholder"
        autocomplete="off"
      />
      <CategoryPicker
        v-model="editCategoryId"
        :house-id="houseId"
        :label="strings.categoryLabel"
        :placeholder="strings.categoryPlaceholder"
      />
      <NcButton variant="tertiary" type="button" @click="showRecurrenceEditor = true">
        <template #icon>
          <RepeatIcon :size="20" />
        </template>
        {{ editRrule ? strings.recurrenceSet : strings.recurrenceButton }}
      </NcButton>

      <div class="edit-item-form__image">
        <span class="edit-item-form__label">{{ strings.imageLabel }}</span>
        <div class="edit-item-form__image-row">
          <img
            v-if="previewImageUrl"
            class="edit-item-form__image-preview"
            :src="previewImageUrl"
            :alt="item.name"
          />
          <NcButton variant="tertiary" type="button" @click="triggerImagePick">
            <template #icon>
              <UploadIcon :size="20" />
            </template>
            {{ hasImage ? strings.replaceImage : strings.uploadImage }}
          </NcButton>
          <NcButton v-if="hasImage" variant="tertiary" type="button" @click="clearPendingImage">
            <template #icon>
              <DeleteIcon :size="20" />
            </template>
            {{ strings.removeImage }}
          </NcButton>
          <input
            ref="imageInputRef"
            type="file"
            accept="image/*"
            class="edit-item-form__image-input"
            @change="onImagePicked"
          />
        </div>
      </div>
    </form>

    <RecurrenceEditor
      v-model:open="showRecurrenceEditor"
      v-model="editRrule"
      v-model:from-completion="editRepeatFromCompletion"
    />

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton
        form="pantry-edit-item-form"
        type="submit"
        variant="primary"
        :disabled="!editName.trim() || saving"
      >
        {{ strings.save }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import RepeatIcon from '@icons/Repeat.vue'
import UploadIcon from '@icons/Upload.vue'
import DeleteIcon from '@icons/Delete.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import RecurrenceEditor from '@/components/RecurrenceEditor'
import CategoryPicker from '@/components/CategoryPicker'
import { itemImagePreviewUrl } from '@/api/images'
import type { ChecklistItem } from '@/api/types'
import type { ItemInput } from '@/api/lists'

const props = defineProps<{
  open: boolean
  item: ChecklistItem
  houseId: number
  saving: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [itemId: number, patch: Partial<ItemInput>, pendingImage: File | null, clearImage: boolean]
}>()

const editName = ref('')
const editDescription = ref('')
const editQuantity = ref('')
const editCategoryId = ref<number | null>(null)
const editRrule = ref<string | null>(null)
const editRepeatFromCompletion = ref(false)
const showRecurrenceEditor = ref(false)
const imageInputRef = ref<HTMLInputElement | null>(null)

const pendingImageFile = ref<File | null>(null)
const pendingImageObjectUrl = ref<string | null>(null)
const pendingClearImage = ref(false)

const serverThumbUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 96)
    : null,
)

const hasImage = computed(() => {
  if (pendingClearImage.value) return !!pendingImageFile.value
  return !!pendingImageFile.value || !!props.item.imageFileId
})

const previewImageUrl = computed(() => {
  if (pendingImageObjectUrl.value) return pendingImageObjectUrl.value
  if (pendingClearImage.value) return null
  return serverThumbUrl.value
})

function revokeObjectUrl() {
  if (pendingImageObjectUrl.value) {
    URL.revokeObjectURL(pendingImageObjectUrl.value)
    pendingImageObjectUrl.value = null
  }
}

function resetImageState() {
  revokeObjectUrl()
  pendingImageFile.value = null
  pendingClearImage.value = false
}

watch(
  () => props.open,
  (v) => {
    if (v) {
      editName.value = props.item.name
      editDescription.value = props.item.description ?? ''
      editQuantity.value = props.item.quantity ?? ''
      editCategoryId.value = props.item.categoryId ?? null
      editRrule.value = props.item.rrule ?? null
      editRepeatFromCompletion.value = props.item.repeatFromCompletion ?? false
      resetImageState()
    }
  },
  { immediate: true },
)

onBeforeUnmount(revokeObjectUrl)

function submitEdit() {
  const name = editName.value.trim()
  if (!name) return
  emit(
    'save',
    props.item.id,
    {
      name,
      description: editDescription.value.trim(),
      quantity: editQuantity.value.trim(),
      categoryId: editCategoryId.value,
      rrule: editRrule.value,
      repeatFromCompletion: editRepeatFromCompletion.value,
    },
    pendingImageFile.value,
    pendingClearImage.value,
  )
}

function triggerImagePick() {
  imageInputRef.value?.click()
}

function onImagePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  revokeObjectUrl()
  pendingImageFile.value = file
  pendingImageObjectUrl.value = URL.createObjectURL(file)
  pendingClearImage.value = false
  input.value = ''
}

function clearPendingImage() {
  revokeObjectUrl()
  pendingImageFile.value = null
  pendingClearImage.value = true
}

const strings = {
  title: t('pantry', 'Edit item'),
  save: t('pantry', 'Save'),
  cancel: t('pantry', 'Cancel'),
  nameLabel: t('pantry', 'Item name'),
  namePlaceholder: t('pantry', 'e.g. Milk'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'Add a description …'),
  quantityLabel: t('pantry', 'Quantity'),
  quantityPlaceholder: t('pantry', 'e.g. 2 L'),
  categoryLabel: t('pantry', 'Category'),
  categoryPlaceholder: t('pantry', 'Category'),
  recurrenceButton: t('pantry', 'Repeat …'),
  recurrenceSet: t('pantry', 'Repeat: set'),
  imageLabel: t('pantry', 'Image'),
  uploadImage: t('pantry', 'Upload image'),
  replaceImage: t('pantry', 'Replace image'),
  removeImage: t('pantry', 'Remove image'),
}
</script>

<style scoped lang="scss">
.edit-item-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;

  &__image {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  &__label {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__image-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  &__image-preview {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: var(--border-radius, 6px);
    border: 1px solid var(--color-border);
  }

  &__image-input {
    display: none;
  }
}
</style>
