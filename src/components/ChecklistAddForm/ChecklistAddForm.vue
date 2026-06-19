<template>
  <form class="checklist-add" autocomplete="off" @submit.prevent="submitAdd">
    <div class="checklist-add__primary" :class="{ 'checklist-add__primary--multiple': multiple }">
      <div class="checklist-add__name-wrapper">
        <AutoResizeTextarea
          v-if="multiple"
          v-model="name"
          class="checklist-add__name-textarea"
          :rows="3"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
        <NcTextField
          v-else
          v-model="name"
          class="checklist-add__name"
          :class="{ 'checklist-add__name--compact': requireListSelector }"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
        <div v-if="multiple" class="checklist-add__hint">
          {{ strings.multipleHint }}
        </div>
      </div>
      <NcSelect
        v-if="requireListSelector"
        class="checklist-add__list-select"
        :model-value="selectedListOption"
        :options="listOptions"
        :clearable="false"
        :placeholder="strings.list"
        input-label=""
        @update:model-value="onListSelected"
      >
        <template #option="opt">
          <span class="checklist-add__list-option">
            <span class="checklist-add__list-option-icon" :style="listIconStyle(opt.list)">
              <component :is="checklistIconComponent(opt.list.icon)" :size="14" />
            </span>
            {{ opt.label }}
          </span>
        </template>
        <template #selected-option="opt">
          <span class="checklist-add__list-option">
            <span class="checklist-add__list-option-icon" :style="listIconStyle(opt.list)">
              <component :is="checklistIconComponent(opt.list.icon)" :size="14" />
            </span>
            {{ opt.label }}
          </span>
        </template>
      </NcSelect>
      <NcCheckboxRadioSwitch v-model="multiple" class="checklist-add__multiple-toggle">
        {{ strings.multiple }}
      </NcCheckboxRadioSwitch>
      <NcButton
        type="submit"
        variant="primary"
        :disabled="!canSubmit || adding"
        :class="{ 'checklist-add__submit--compact': requireListSelector && !multiple }"
      >
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
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
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'
import { formatRrule } from '@/utils/rrule'
import type { ItemInput } from '@/api/lists'
import type { Checklist } from '@/api/types'

type SectionKey = 'category' | 'quantity' | 'description' | 'type' | 'image'

const props = withDefaults(
  defineProps<{
    houseId: number
    adding: boolean
    deleteOnDoneDefault?: boolean
    requireListSelector?: boolean
    availableLists?: Checklist[]
  }>(),
  { deleteOnDoneDefault: false, requireListSelector: false, availableLists: () => [] },
)

const emit = defineEmits<{
  add: [input: ItemInput, pendingImage: File | null, targetListId: number | null]
  'update:deleteOnDoneDefault': [value: boolean]
}>()

const name = ref('')
const multiple = ref(false)
const description = ref('')
const quantity = ref('')
const categoryId = ref<number | null>(null)
const targetListId = ref<number | null>(null)
const rrule = ref<string | null>(null)
const repeatFromCompletion = ref(false)
const deleteOnDone = ref(props.deleteOnDoneDefault)
const openSection = ref<SectionKey | null>(null)

interface ListOption {
  value: number
  label: string
  list: Checklist
}

const listOptions = computed<ListOption[]>(() =>
  props.availableLists.map((l) => ({ value: l.id, label: l.name, list: l })),
)

const selectedListOption = computed<ListOption | null>(
  () => listOptions.value.find((o) => o.value === targetListId.value) ?? null,
)

function onListSelected(option: ListOption | null) {
  targetListId.value = option?.value ?? null
}

function listIconStyle(list: Checklist) {
  if (!list.color) return undefined
  return { background: list.color, color: contrastColor(list.color) }
}

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

watch(multiple, (on) => {
  if (!on) return
  if (openSection.value === 'image') openSection.value = null
  revokeObjectUrl()
  pendingImage.value = null
})

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

const itemNames = computed(() =>
  multiple.value
    ? name.value
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.length > 0)
    : name.value.trim().length > 0
      ? [name.value.trim()]
      : [],
)

const canSubmit = computed(() => {
  if (itemNames.value.length === 0) return false
  if (props.requireListSelector && targetListId.value === null) return false
  return true
})

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

  if (!multiple.value) {
    list.push({
      key: 'image',
      text: pendingImage.value ? strings.imageAttached : strings.image,
      icon: ImageIcon,
      filled: pendingImage.value !== null,
    })
  }

  return list
})

function chipVariant(chip: Chip): 'primary' | 'secondary' | 'tertiary' {
  if (openSection.value === chip.key) return 'primary'
  if (chip.filled) return 'secondary'
  return 'tertiary'
}

// ----- Submit -----

function submitAdd() {
  const names = itemNames.value
  if (names.length === 0) return
  if (props.requireListSelector && targetListId.value === null) return
  const once = deleteOnDone.value
  names.forEach((itemName, index) => {
    emit(
      'add',
      {
        name: itemName,
        description: description.value.trim() || null,
        quantity: quantity.value.trim() || null,
        categoryId: categoryId.value,
        rrule: once ? null : rrule.value,
        repeatFromCompletion: once ? false : repeatFromCompletion.value,
        deleteOnDone: once,
      },
      index === 0 ? pendingImage.value : null,
      targetListId.value,
    )
  })
  // Reset form — keep the chosen list so users can add multiple items in a row.
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
  multiple: t('pantry', 'Multiple'),
  multipleHint: t('pantry', 'Separate items by new lines'),
  nameLabel: t('pantry', 'Item name'),
  namePlaceholder: t('pantry', 'e.g. Milk'),
  list: t('pantry', 'Pick a list …'),
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
    align-items: center;
    gap: 0.75rem;

    &--multiple {
      align-items: flex-start;
    }
  }

  &__name-wrapper {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  &__name-textarea {
    width: 100%;
    margin-block-start: -2px;
  }

  &__hint {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
  }

  &__multiple-toggle {
    flex: 0 0 auto;
  }

  &__name {
    flex: 1;
    min-width: 0;
    margin-block-start: 0;

    // NcSelect renders ~36 px tall and is awkward to enlarge. When the list
    // selector is visible, shrink the text field to match so the two controls
    // align in the row. NcTextField wraps its input in a label-aware container
    // whose top space lives outside the box — pull it up to compensate.
    &--compact {
      margin-block-start: -6px;
    }

    &--compact :deep(.input-field__main-wrapper),
    &--compact :deep(.input-field__input) {
      height: 36px;
      min-height: 36px;
    }

    // Re-center the floating label inside the compact 36 px input. Only when
    // the input is empty and unfocused — once it has content or focus, the
    // label floats above as normal.
    &--compact :deep(.input-field__input:not(:focus):placeholder-shown + .input-field__label) {
      inset-block-start: calc((var(--default-clickable-area) - 1lh) / 2 + 3px);
    }
  }

  &__list-select {
    flex: 0 0 auto;
    min-width: 180px;

    :deep(.v-select),
    :deep(.vs__dropdown-toggle) {
      min-height: 36px;
    }
  }

  &__submit--compact {
    margin-block-start: -6px;
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

  &__list-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
  }

  &__list-option-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: var(--color-background-dark);
    color: var(--color-main-text);
    flex-shrink: 0;
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
