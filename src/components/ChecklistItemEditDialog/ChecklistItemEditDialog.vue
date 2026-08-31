<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <form
      id="pantry-edit-item-form"
      class="edit-item-form"
      autocomplete="off"
      @submit.prevent="submitEdit"
    >
      <FieldCard :label="strings.nameLabel">
        <NcTextField
          v-model="editName"
          :label="strings.nameLabel"
          label-outside
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
      </FieldCard>

      <FieldCard :label="strings.descriptionLabel">
        <MarkdownEditor
          v-model="editDescription"
          :placeholder="strings.descriptionPlaceholder"
          dir="auto"
          autocomplete="off"
        />
      </FieldCard>

      <FieldCard :label="strings.quantityLabel">
        <QuantityInput v-model="editQuantity" />
      </FieldCard>

      <FieldCard :label="strings.priceLabel">
        <ItemPricesEditor
          v-model="editPrices"
          :house-id="houseId"
          :default-currency="defaultCurrency"
        />
      </FieldCard>

      <FieldCard :label="strings.categoryLabel">
        <CategoryChipList v-model="editCategoryId" :house-id="houseId" :list-id="item.listId" />
      </FieldCard>

      <FieldCard :label="strings.storesLabel">
        <StoreChipList v-model="editStoreIds" :house-id="houseId" />
      </FieldCard>

      <FieldCard :label="strings.labelsLabel">
        <LabelChipList v-model="editLabelIds" :house-id="houseId" :list-id="item.listId" />
      </FieldCard>

      <template v-if="hasCustomFields">
        <ItemCustomFieldsEditor
          v-model="editCustomFields"
          :house-id="houseId"
          :list-id="item.listId"
        />
      </template>

      <FieldCard :label="strings.typeLabel">
        <div class="edit-item-form__type">
          <ItemTypeSelector
            :delete-on-done="editDeleteOnDone"
            :rrule="editRrule"
            @select-staple="selectStaple"
            @select-one-time="selectOneTime"
            @select-recurring="selectRecurring"
          />
          <RecurrenceForm
            v-if="currentType === 'recurring'"
            v-model="editRrule"
            v-model:from-completion="editRepeatFromCompletion"
          />
        </div>
      </FieldCard>

      <FieldCard :label="strings.imageLabel">
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
      </FieldCard>
    </form>

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
import UploadIcon from '@icons/Upload.vue'
import DeleteIcon from '@icons/Delete.vue'
import { MarkdownEditor } from '@/components/MarkdownEditor'
import { RecurrenceForm } from '@/components/RecurrenceEditor'
import FieldCard from '@/components/FieldCard'
import CategoryChipList from '@/components/CategoryChipList'
import StoreChipList from '@/components/StoreChipList'
import LabelChipList from '@/components/LabelChipList'
import ItemTypeSelector from '@/components/ItemTypeSelector'
import QuantityInput from '@/components/QuantityInput'
import ItemPricesEditor from '@/components/ItemPricesEditor'
import ItemCustomFieldsEditor from '@/components/ItemCustomFieldsEditor'
import { itemImagePreviewUrl } from '@/api/images'
import { DEFAULT_CURRENCY } from '@/utils/currencies'
import type { ChecklistItem, ItemCustomFieldValue, ItemPrice } from '@/api/types'
import type { ItemInput } from '@/api/lists'
import { useCustomFields } from '@/composables/useCustomFields'

const props = withDefaults(
  defineProps<{
    open: boolean
    item: ChecklistItem
    houseId: number
    saving: boolean
    /** Currency preselected when the item has no price yet (house's last-used). */
    defaultCurrency?: string
  }>(),
  { defaultCurrency: DEFAULT_CURRENCY },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [itemId: number, patch: Partial<ItemInput>, pendingImage: File | null, clearImage: boolean]
}>()

const editName = ref('')
const editDescription = ref('')
const editQuantity = ref('')
const editCategoryId = ref<number | null>(null)
const editStoreIds = ref<number[]>([])
const editLabelIds = ref<number[]>([])
const editRrule = ref<string | null>(null)
const editRepeatFromCompletion = ref(false)
const editDeleteOnDone = ref(false)
const editPrices = ref<ItemPrice[]>([])
const editCustomFields = ref<ItemCustomFieldValue[]>([])

type ItemType = 'staple' | 'oneTime' | 'recurring'
const currentType = computed<ItemType>(() => {
  if (editDeleteOnDone.value) return 'oneTime'
  if (editRrule.value) return 'recurring'
  return 'staple'
})

const customFields = useCustomFields(props.houseId)
const hasCustomFields = computed(() =>
  customFields.items.value.some((f) => f.listId == null || f.listId === props.item.listId),
)
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
      editStoreIds.value = [...(props.item.storeIds ?? [])]
      editLabelIds.value = [...(props.item.labelIds ?? [])]
      editRrule.value = props.item.rrule ?? null
      editRepeatFromCompletion.value = props.item.repeatFromCompletion ?? false
      editDeleteOnDone.value = props.item.deleteOnDone ?? false
      editPrices.value = (props.item.prices ?? []).map((p) => ({ ...p }))
      editCustomFields.value = (props.item.customFields ?? []).map((v) => ({ ...v }))
      void customFields.load()
      resetImageState()
    }
  },
  { immediate: true },
)

onBeforeUnmount(revokeObjectUrl)

function selectStaple() {
  editRrule.value = null
  editRepeatFromCompletion.value = false
  editDeleteOnDone.value = false
}

function selectOneTime() {
  editRrule.value = null
  editRepeatFromCompletion.value = false
  editDeleteOnDone.value = true
}

function selectRecurring() {
  editDeleteOnDone.value = false
  // RecurrenceForm renders inline below the type selector once currentType
  // becomes 'recurring'; seed a weekly default so it appears immediately.
  if (!editRrule.value) {
    editRrule.value = 'FREQ=WEEKLY;INTERVAL=1'
  }
}

function submitEdit() {
  const name = editName.value.trim()
  if (!name) return
  // "Once" items can't recur — ignore any locally-retained recurrence settings.
  const once = editDeleteOnDone.value
  emit(
    'save',
    props.item.id,
    {
      name,
      description: editDescription.value.trim(),
      quantity: editQuantity.value.trim(),
      categoryId: editCategoryId.value,
      storeIds: editStoreIds.value,
      labelIds: editLabelIds.value,
      rrule: once ? null : editRrule.value,
      repeatFromCompletion: once ? false : editRepeatFromCompletion.value,
      deleteOnDone: once,
      // The full price set is replaced; an empty array clears all prices.
      prices: editPrices.value,
      // Likewise, the full custom-field value set is replaced.
      customFields: editCustomFields.value,
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
  categoryLabel: t('pantry', 'Category'),
  // TRANSLATORS: Noun (plural), shops where this item can be bought. Field label.
  storesLabel: t('pantry', 'Stores'),
  // TRANSLATORS: Noun (plural), tags attached to an item. Field label.
  labelsLabel: t('pantry', 'Labels'),
  priceLabel: t('pantry', 'Price'),
  typeLabel: t('pantry', 'Item type'),
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
  gap: 0.85rem;
  padding: 0.5rem 0;

  &__type {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
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
