<template>
  <form class="checklist-add" autocomplete="off" @submit.prevent="submitAdd">
    <div class="checklist-add__primary">
      <NcTextField
        v-model="name"
        class="checklist-add__name"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <NcButton type="submit" variant="primary" :disabled="!name.trim() || adding">
        <template #icon>
          <PlusIcon :size="20" />
        </template>
        {{ strings.add }}
      </NcButton>
    </div>

    <div class="checklist-add__chips">
      <PantryChip
        v-for="chip in chips"
        :key="chip.key"
        :variant="chipVariant(chip)"
        class="checklist-add__chip"
        @click="toggleSection(chip.key)"
      >
        <template #icon>
          <component :is="chip.icon" :size="14" :style="chip.iconStyle" />
        </template>
        {{ chip.text }}
      </PantryChip>
    </div>

    <div v-if="openSection" class="checklist-add__section">
      <!-- Category -->
      <CategoryChipList
        v-if="openSection === 'category'"
        v-model="categoryId"
        :house-id="houseId"
      />

      <!-- Quantity -->
      <QuantityInput v-else-if="openSection === 'quantity'" v-model="quantity" />

      <!-- Description -->
      <AutoResizeTextarea
        v-else-if="openSection === 'description'"
        v-model="description"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
        autocomplete="off"
      />

      <!-- Item type + (inline recurrence when Recurring) -->
      <div v-else-if="openSection === 'type'" class="checklist-add__type">
        <ItemTypeSelector
          :delete-on-done="deleteOnDone"
          :rrule="rrule"
          @select-staple="selectStaple"
          @select-one-time="selectOneTime"
          @select-recurring="selectRecurring"
        />
        <RecurrenceForm
          v-if="currentType === 'recurring'"
          v-model="rrule"
          v-model:from-completion="repeatFromCompletion"
        />
      </div>

      <!-- Image -->
      <div v-else-if="openSection === 'image'" class="checklist-add__image">
        <div v-if="previewImageUrl" class="checklist-add__image-row">
          <img
            class="checklist-add__image-preview"
            :src="previewImageUrl"
            :alt="strings.imageAlt"
          />
          <NcButton variant="tertiary" type="button" @click="triggerImagePick">
            <template #icon>
              <UploadIcon :size="20" />
            </template>
            {{ strings.replaceImage }}
          </NcButton>
          <NcButton variant="tertiary" type="button" @click="clearPendingImage">
            <template #icon>
              <DeleteIcon :size="20" />
            </template>
            {{ strings.removeImage }}
          </NcButton>
        </div>
        <NcButton v-else variant="tertiary" type="button" @click="triggerImagePick">
          <template #icon>
            <ImagePlusIcon :size="20" />
          </template>
          {{ strings.addImage }}
        </NcButton>
        <input
          ref="imageInputRef"
          type="file"
          accept="image/*"
          class="checklist-add__image-input"
          @change="onImagePicked"
        />
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, type Component } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import TagOutlineIcon from '@icons/TagOutline.vue'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import TextIcon from '@icons/Text.vue'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import ImageIcon from '@icons/Image.vue'
import ImagePlusIcon from '@icons/ImagePlus.vue'
import UploadIcon from '@icons/Upload.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import { RecurrenceForm } from '@/components/RecurrenceEditor'
import CategoryChipList from '@/components/CategoryChipList'
import ItemTypeSelector from '@/components/ItemTypeSelector'
import QuantityInput from '@/components/QuantityInput'
import PantryChip from '@/components/PantryChip'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { formatRrule } from '@/utils/rrule'
import type { ItemInput } from '@/api/lists'

type SectionKey = 'category' | 'quantity' | 'description' | 'type' | 'image'

const props = withDefaults(
  defineProps<{
    houseId: number
    adding: boolean
    deleteOnDoneDefault?: boolean
  }>(),
  { deleteOnDoneDefault: false },
)

const emit = defineEmits<{
  add: [input: ItemInput, pendingImage: File | null]
  'update:deleteOnDoneDefault': [value: boolean]
}>()

const name = ref('')
const description = ref('')
const quantity = ref('')
const categoryId = ref<number | null>(null)
const rrule = ref<string | null>(null)
const repeatFromCompletion = ref(false)
const deleteOnDone = ref(props.deleteOnDoneDefault)
const openSection = ref<SectionKey | null>(null)

const pendingImage = ref<File | null>(null)
const pendingImageObjectUrl = ref<string | null>(null)
const imageInputRef = ref<HTMLInputElement | null>(null)

// Tracks whether the user has explicitly chosen an item type via the button
// group. The chip stays a neutral "Item type" until they pick one — even if
// the list's deleteOnDoneDefault has implicitly set deleteOnDone=true.
const userPickedType = ref(false)

// Categories are loaded so the chip can show the selected category's name/icon.
const { items: categories, load: loadCategories } = useCategories(props.houseId)
void loadCategories()

watch(
  () => props.houseId,
  () => void useCategories(props.houseId).load(),
)

watch(
  () => props.deleteOnDoneDefault,
  (value) => {
    deleteOnDone.value = value
  },
)

function toggleSection(key: SectionKey) {
  openSection.value = openSection.value === key ? null : key
}

// ----- Item type -----

type ItemType = 'staple' | 'oneTime' | 'recurring'

const currentType = computed<ItemType>(() => {
  if (deleteOnDone.value) return 'oneTime'
  if (rrule.value) return 'recurring'
  return 'staple'
})

function setDeleteOnDoneAndPersist(value: boolean) {
  deleteOnDone.value = value
  if (value !== props.deleteOnDoneDefault) {
    emit('update:deleteOnDoneDefault', value)
  }
}

function selectStaple() {
  rrule.value = null
  repeatFromCompletion.value = false
  userPickedType.value = true
  setDeleteOnDoneAndPersist(false)
}

function selectOneTime() {
  rrule.value = null
  repeatFromCompletion.value = false
  userPickedType.value = true
  setDeleteOnDoneAndPersist(true)
}

function selectRecurring() {
  deleteOnDone.value = false
  userPickedType.value = true
  // The RecurrenceForm renders inline below the type selector once
  // currentType becomes 'recurring'. It will live-emit a default rrule
  // (weekly) as soon as it mounts, which flips currentType for us.
  if (!rrule.value) {
    // Seed a minimal weekly rrule so the form mounts in a "recurring" state.
    rrule.value = 'FREQ=WEEKLY;INTERVAL=1'
  }
}

// ----- Image -----

function revokeObjectUrl() {
  if (pendingImageObjectUrl.value) {
    URL.revokeObjectURL(pendingImageObjectUrl.value)
    pendingImageObjectUrl.value = null
  }
}

const previewImageUrl = computed(() => pendingImageObjectUrl.value)

function triggerImagePick() {
  imageInputRef.value?.click()
}

function onImagePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  revokeObjectUrl()
  pendingImage.value = file
  pendingImageObjectUrl.value = URL.createObjectURL(file)
  input.value = ''
}

function clearPendingImage() {
  revokeObjectUrl()
  pendingImage.value = null
}

onBeforeUnmount(revokeObjectUrl)

// ----- Chips -----

const selectedCategory = computed(() =>
  categoryId.value != null
    ? (categories.value.find((c) => c.id === categoryId.value) ?? null)
    : null,
)

interface Chip {
  key: SectionKey
  text: string
  icon: Component
  iconStyle?: Record<string, string>
  filled: boolean
}

const chips = computed<Chip[]>(() => {
  const list: Chip[] = []

  list.push({
    key: 'category',
    text: selectedCategory.value ? selectedCategory.value.name : strings.category,
    icon: selectedCategory.value
      ? categoryIconComponent(selectedCategory.value.icon)
      : TagOutlineIcon,
    iconStyle: selectedCategory.value ? { color: selectedCategory.value.color } : undefined,
    filled: selectedCategory.value !== null,
  })

  list.push({
    key: 'quantity',
    text: quantity.value.trim() || strings.quantity,
    icon: FormatListBulletedIcon,
    filled: quantity.value.trim().length > 0,
  })

  list.push({
    key: 'description',
    text: strings.description,
    icon: TextIcon,
    filled: description.value.trim().length > 0,
  })

  // Item type chip — stays neutral "Item type" until the user explicitly
  // picks one of the three options.
  if (!userPickedType.value) {
    list.push({
      key: 'type',
      text: strings.itemType,
      icon: RepeatIcon,
      filled: false,
    })
  } else if (currentType.value === 'staple') {
    list.push({ key: 'type', text: strings.staple, icon: PinIcon, filled: true })
  } else if (currentType.value === 'oneTime') {
    list.push({ key: 'type', text: strings.oneTime, icon: DeleteIcon, filled: true })
  } else {
    list.push({
      key: 'type',
      text: rrule.value ? formatRrule(rrule.value) : strings.recurring,
      icon: RepeatIcon,
      filled: true,
    })
  }

  list.push({
    key: 'image',
    text: pendingImage.value ? strings.imageAttached : strings.image,
    icon: ImageIcon,
    filled: pendingImage.value !== null,
  })

  return list
})

function chipVariant(chip: Chip): 'primary' | 'secondary' | 'tertiary' {
  if (openSection.value === chip.key) return 'primary'
  if (chip.filled) return 'secondary'
  return 'tertiary'
}

// ----- Submit -----

function submitAdd() {
  const trimmedName = name.value.trim()
  if (!trimmedName) return
  const once = deleteOnDone.value
  emit(
    'add',
    {
      name: trimmedName,
      description: description.value.trim() || null,
      quantity: quantity.value.trim() || null,
      categoryId: categoryId.value,
      rrule: once ? null : rrule.value,
      repeatFromCompletion: once ? false : repeatFromCompletion.value,
      deleteOnDone: once,
    },
    pendingImage.value,
  )
  // Reset form
  name.value = ''
  description.value = ''
  quantity.value = ''
  categoryId.value = null
  rrule.value = null
  repeatFromCompletion.value = false
  // Keep the user's last-chosen list default.
  deleteOnDone.value = props.deleteOnDoneDefault
  userPickedType.value = false
  revokeObjectUrl()
  pendingImage.value = null
  openSection.value = null
}

const strings = {
  add: t('pantry', 'Add'),
  nameLabel: t('pantry', 'Item name'),
  namePlaceholder: t('pantry', 'e.g. Milk'),
  category: t('pantry', 'Category'),
  quantity: t('pantry', 'Quantity'),
  description: t('pantry', 'Description'),
  descriptionLabel: t('pantry', 'Description'),
  itemType: t('pantry', 'Item type'),
  descriptionPlaceholder: t('pantry', 'Notes, instructions, links …'),
  staple: t('pantry', 'Staple'),
  oneTime: t('pantry', 'One-time'),
  recurring: t('pantry', 'Recurring'),
  image: t('pantry', 'Image'),
  imageAttached: t('pantry', 'Image attached'),
  addImage: t('pantry', 'Add image'),
  replaceImage: t('pantry', 'Replace image'),
  removeImage: t('pantry', 'Remove image'),
  imageAlt: t('pantry', 'Selected image'),
}
</script>

<style scoped lang="scss">
.checklist-add {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.5rem;

  &__primary {
    display: flex;
    align-items: end;
    gap: 0.75rem;
  }

  &__name {
    flex: 1;
    min-width: 0;
  }

  &__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
  }

  &__chip {
    flex: 0 0 auto;
    cursor: pointer;
  }

  &__section {
    padding: 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 8px);
    background: var(--color-background-hover);
  }

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
