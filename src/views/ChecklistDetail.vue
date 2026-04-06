<template>
  <div class="pantry-detail">
    <PageToolbar :title="list?.name">
      <template #before-title>
        <NcButton
          variant="tertiary"
          :aria-label="strings.back"
          @click="$router.push({ name: 'lists', params: { houseId } })"
        >
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-detail__body">
      <form class="pantry-detail__add" @submit.prevent="submitAdd">
        <NcTextField
          v-model="newName"
          :label="strings.newItemLabel"
          :placeholder="strings.newItemPlaceholder"
        />
        <NcTextField
          v-model="newQuantity"
          :label="strings.quantityLabel"
          :placeholder="strings.quantityPlaceholder"
        />
        <CategoryPicker
          v-model="newCategoryId"
          :house-id="houseIdNum"
          :placeholder="strings.categoryPlaceholder"
        />
        <NcButton variant="tertiary" @click="showRecurrenceEditor = true">
          <template #icon>
            <RepeatIcon :size="20" />
          </template>
          {{ newRrule ? strings.recurrenceSet : strings.recurrenceButton }}
        </NcButton>
        <NcButton type="submit" variant="primary" :disabled="!newName.trim() || adding">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.add }}
        </NcButton>
      </form>

      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <ClipboardCheckIcon />
        </template>
      </NcEmptyContent>

      <ul v-else class="pantry-items">
        <li
          v-for="item in sortedItems"
          :key="item.id"
          class="pantry-item"
          :class="{ 'pantry-item--bought': item.bought }"
        >
          <NcCheckboxRadioSwitch
            :model-value="item.bought"
            @update:model-value="handleToggle(item.id)"
          >
            <span class="pantry-item__label">
              <button
                v-if="item.imageFileId"
                type="button"
                class="pantry-item__thumb"
                :aria-label="strings.viewImage"
                @click.stop.prevent="openPreview(item)"
              >
                <img :src="thumbUrl(item.imageFileId)" :alt="item.name" />
              </button>
              <span class="pantry-item__name">{{ item.name }}</span>
            </span>
          </NcCheckboxRadioSwitch>
          <div class="pantry-item__meta">
            <span v-if="item.quantity" class="pantry-item__quantity">{{ item.quantity }}</span>
            <span
              v-if="categoryFor(item.categoryId)"
              class="pantry-item__category"
              :style="{ color: categoryFor(item.categoryId)!.color }"
            >
              <component
                :is="categoryIconComponent(categoryFor(item.categoryId)!.icon)"
                :size="14"
              />
              {{ categoryFor(item.categoryId)!.name }}
            </span>
            <span v-if="item.rrule" class="pantry-item__recurrence" :title="item.rrule">
              <RepeatIcon :size="14" />
              {{ formatRrule(item.rrule) }}
            </span>
          </div>
          <div class="pantry-item__actions">
            <NcButton variant="tertiary" :aria-label="strings.editItem" @click="startEdit(item)">
              <template #icon>
                <PencilIcon :size="18" />
              </template>
            </NcButton>
            <NcButton
              variant="tertiary"
              :aria-label="strings.removeItem"
              @click="handleRemove(item.id)"
            >
              <template #icon>
                <DeleteIcon :size="18" />
              </template>
            </NcButton>
          </div>
        </li>
      </ul>
    </div>

    <RecurrenceEditor
      v-model:open="showRecurrenceEditor"
      v-model="newRrule"
      v-model:from-completion="newRepeatFromCompletion"
    />

    <NcDialog
      v-if="editing"
      :name="strings.editDialogTitle"
      :open="!!editing"
      close-on-click-outside
      @update:open="(v) => !v && (editing = null)"
    >
      <form id="pantry-edit-item-form" class="pantry-form" @submit.prevent="submitEdit">
        <NcTextField
          v-model="editName"
          :label="strings.newItemLabel"
          :placeholder="strings.newItemPlaceholder"
        />
        <NcTextField
          v-model="editQuantity"
          :label="strings.quantityLabel"
          :placeholder="strings.quantityPlaceholder"
        />
        <CategoryPicker
          v-model="editCategoryId"
          :house-id="houseIdNum"
          :label="strings.categoryLabel"
          :placeholder="strings.categoryPlaceholder"
        />
        <NcButton variant="tertiary" type="button" @click="showEditRecurrenceEditor = true">
          <template #icon>
            <RepeatIcon :size="20" />
          </template>
          {{ editRrule ? strings.recurrenceSet : strings.recurrenceButton }}
        </NcButton>

        <div class="pantry-form__image">
          <span class="pantry-form__label">{{ strings.imageLabel }}</span>
          <div class="pantry-form__image-row">
            <img
              v-if="editing?.imageFileId"
              class="pantry-form__image-preview"
              :src="thumbUrl(editing.imageFileId, 96)"
              :alt="editing.name"
            />
            <NcButton
              variant="tertiary"
              type="button"
              :disabled="uploadingImage"
              @click="triggerImagePick"
            >
              <template #icon>
                <UploadIcon :size="20" />
              </template>
              {{ editing?.imageFileId ? strings.replaceImage : strings.uploadImage }}
            </NcButton>
            <NcButton
              v-if="editing?.imageFileId"
              variant="tertiary"
              type="button"
              :disabled="uploadingImage"
              @click="removeImage"
            >
              <template #icon>
                <DeleteIcon :size="20" />
              </template>
              {{ strings.removeImage }}
            </NcButton>
            <input
              ref="imageInputRef"
              type="file"
              accept="image/*"
              class="pantry-form__image-input"
              @change="onImagePicked"
            />
          </div>
        </div>
      </form>
      <template #actions>
        <NcButton @click="editing = null">{{ strings.cancel }}</NcButton>
        <NcButton
          form="pantry-edit-item-form"
          type="submit"
          variant="primary"
          :disabled="!editName.trim() || savingEdit"
        >
          {{ strings.save }}
        </NcButton>
      </template>
    </NcDialog>

    <RecurrenceEditor
      v-model:open="showEditRecurrenceEditor"
      v-model="editRrule"
      v-model:from-completion="editRepeatFromCompletion"
    />

    <NcDialog
      v-if="previewing"
      :name="previewing.name"
      :open="!!previewing"
      close-on-click-outside
      size="large"
      @update:open="(v) => !v && (previewing = null)"
    >
      <div class="pantry-preview">
        <img
          v-if="previewing.imageFileId"
          :src="largeUrl(previewing.imageFileId)"
          :alt="previewing.name"
        />
      </div>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { generateUrl } from '@nextcloud/router'
import PageToolbar from '@/components/PageToolbar'
import PlusIcon from '@icons/Plus.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import DeleteIcon from '@icons/Delete.vue'
import PencilIcon from '@icons/Pencil.vue'
import RepeatIcon from '@icons/Repeat.vue'
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import UploadIcon from '@icons/Upload.vue'
import RecurrenceEditor from '@/components/RecurrenceEditor'
import CategoryPicker from '@/components/CategoryPicker'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { useChecklistItems } from '@/composables/useChecklist'
import { useCategories } from '@/composables/useCategories'
import { getList } from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'
import { RRule } from 'rrule'

const props = defineProps<{ houseId: string; listId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const listIdNum = computed(() => Number(props.listId))

const list = ref<Checklist | null>(null)
const { items, loading, load, add, update, toggle, remove, uploadImage, clearImage } =
  useChecklistItems(houseIdNum.value, listIdNum.value)
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

const newName = ref('')
const newQuantity = ref('')
const newCategoryId = ref<number | null>(null)
const newRrule = ref<string | null>(null)
const newRepeatFromCompletion = ref<boolean>(false)
const adding = ref(false)
const showRecurrenceEditor = ref(false)

async function loadList() {
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

onMounted(async () => {
  await Promise.all([loadList(), load(), categories.load()])
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    await Promise.all([loadList(), load()])
  },
)

const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => {
    if (a.bought !== b.bought) return a.bought ? 1 : -1
    if (a.sortOrder !== b.sortOrder) return a.sortOrder - b.sortOrder
    return a.name.localeCompare(b.name)
  })
})

async function submitAdd() {
  const name = newName.value.trim()
  if (!name) return
  adding.value = true
  try {
    await add({
      name,
      quantity: newQuantity.value.trim() || null,
      categoryId: newCategoryId.value,
      rrule: newRrule.value,
      repeatFromCompletion: newRepeatFromCompletion.value,
    })
    newName.value = ''
    newQuantity.value = ''
    newCategoryId.value = null
    newRrule.value = null
    newRepeatFromCompletion.value = false
  } finally {
    adding.value = false
  }
}

async function handleToggle(itemId: number) {
  await toggle(itemId)
}

async function handleRemove(itemId: number) {
  await remove(itemId)
}

const editing = ref<ChecklistItem | null>(null)
const editName = ref('')
const editQuantity = ref('')
const editCategoryId = ref<number | null>(null)
const editRrule = ref<string | null>(null)
const editRepeatFromCompletion = ref<boolean>(false)
const showEditRecurrenceEditor = ref(false)
const savingEdit = ref(false)

function startEdit(item: ChecklistItem) {
  editing.value = item
  editName.value = item.name
  editQuantity.value = item.quantity ?? ''
  editCategoryId.value = item.categoryId ?? null
  editRrule.value = item.rrule ?? null
  editRepeatFromCompletion.value = item.repeatFromCompletion ?? false
}

async function submitEdit() {
  const target = editing.value
  if (!target) return
  const name = editName.value.trim()
  if (!name) return
  savingEdit.value = true
  try {
    await update(target.id, {
      name,
      quantity: editQuantity.value.trim() || null,
      categoryId: editCategoryId.value,
      rrule: editRrule.value,
      repeatFromCompletion: editRepeatFromCompletion.value,
    })
    editing.value = null
  } finally {
    savingEdit.value = false
  }
}

const previewing = ref<ChecklistItem | null>(null)
function openPreview(item: ChecklistItem) {
  previewing.value = item
}

function thumbUrl(fileId: number, size = 64): string {
  const base = generateUrl('/core/preview')
  return `${base}?fileId=${fileId}&x=${size}&y=${size}&a=1`
}

function largeUrl(fileId: number): string {
  const base = generateUrl('/core/preview')
  return `${base}?fileId=${fileId}&x=1600&y=1600&a=1`
}

const imageInputRef = ref<HTMLInputElement | null>(null)
const uploadingImage = ref(false)

function triggerImagePick() {
  imageInputRef.value?.click()
}

async function onImagePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file || !editing.value) return
  uploadingImage.value = true
  try {
    await uploadImage(editing.value.id, file)
    // Refresh the local editing ref with the updated item so the preview appears.
    const refreshed = items.value.find((i) => i.id === editing.value?.id)
    if (refreshed) editing.value = refreshed
  } finally {
    uploadingImage.value = false
    input.value = ''
  }
}

async function removeImage() {
  if (!editing.value) return
  uploadingImage.value = true
  try {
    await clearImage(editing.value.id)
    const refreshed = items.value.find((i) => i.id === editing.value?.id)
    if (refreshed) editing.value = refreshed
  } finally {
    uploadingImage.value = false
  }
}

function formatRrule(rrule: string): string {
  try {
    const rule = RRule.fromString('RRULE:' + rrule.replace(/^RRULE:/i, ''))
    return rule.toText()
  } catch {
    return rrule
  }
}

const strings = {
  back: t('pantry', 'Back to lists'),
  add: t('pantry', 'Add'),
  save: t('pantry', 'Save'),
  cancel: t('pantry', 'Cancel'),
  newItemLabel: t('pantry', 'Item name'),
  newItemPlaceholder: t('pantry', 'e.g. Milk'),
  quantityLabel: t('pantry', 'Quantity'),
  quantityPlaceholder: t('pantry', 'e.g. 2 L'),
  categoryLabel: t('pantry', 'Category'),
  categoryPlaceholder: t('pantry', 'Category'),
  recurrenceButton: t('pantry', 'Repeat …'),
  recurrenceSet: t('pantry', 'Repeat: set'),
  editItem: t('pantry', 'Edit item'),
  editDialogTitle: t('pantry', 'Edit item'),
  imageLabel: t('pantry', 'Image'),
  uploadImage: t('pantry', 'Upload image'),
  replaceImage: t('pantry', 'Replace image'),
  removeImage: t('pantry', 'Remove image'),
  viewImage: t('pantry', 'View image'),
  removeItem: t('pantry', 'Remove item'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
}
</script>

<style scoped lang="scss">
.pantry-detail {
  &__body {
    max-width: 900px;
    margin: 0 auto;
  }

  &__add {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto auto;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 1.5rem;

    :deep(.v-select.select) {
      margin-bottom: 0;
    }

    @media (max-width: 900px) {
      grid-template-columns: 1fr 1fr;
    }
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.pantry-form {
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

.pantry-preview {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0.5rem;

  img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: var(--border-radius, 8px);
  }
}

.pantry-item {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  background: var(--color-main-background);

  &--bought {
    opacity: 0.6;

    .pantry-item__name {
      text-decoration: line-through;
    }
  }

  :deep(.checkbox-content__icon) {
    margin-block: auto !important;
  }

  :deep(.checkbox-radio-switch__content) {
    width: 100%;
    max-width: unset;
  }

  &__label {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
  }

  &__thumb {
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 6px);
    background: var(--color-background-hover);
    cursor: zoom-in;
    overflow: hidden;
    flex-shrink: 0;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    &:hover,
    &:focus-visible {
      border-color: var(--color-primary-element);
    }
  }

  &__name {
    font-weight: 500;
  }

  &__meta {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  &__quantity,
  &__category,
  &__recurrence {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }
}
</style>
