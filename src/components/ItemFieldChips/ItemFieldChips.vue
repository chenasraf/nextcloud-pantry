<template>
  <div class="item-field-chips">
    <div class="item-field-chips__row">
      <PantryChip
        v-for="chip in chips"
        :key="chip.key"
        :variant="chipVariant(chip)"
        class="item-field-chips__chip"
        @click="toggleSection(chip.key)"
      >
        <template #icon>
          <component :is="chip.icon" :size="14" :style="chip.iconStyle" />
        </template>
        {{ chip.text }}
      </PantryChip>
      <slot name="chips-after" />
    </div>

    <div v-if="openSection" class="item-field-chips__section">
      <slot name="section-header" :section="openSection" />

      <CategoryChipList
        v-if="openSection === 'category'"
        v-model="categoryId"
        :house-id="houseId"
        :list-id="listId"
      />

      <StoreChipList v-else-if="openSection === 'stores'" v-model="storeIds" :house-id="houseId" />

      <LabelChipList
        v-else-if="openSection === 'labels'"
        v-model="labelIds"
        :house-id="houseId"
        :list-id="listId"
      />

      <QuantityInput v-else-if="openSection === 'quantity'" v-model="quantity" />

      <ItemPricesEditor
        v-else-if="openSection === 'price'"
        v-model="prices"
        :house-id="houseId"
        :default-currency="defaultCurrency"
      />

      <ItemCustomFieldsEditor
        v-else-if="openSection === 'customfields'"
        v-model="customFieldValues"
        :house-id="houseId"
        :list-id="listId"
      />

      <AutoResizeTextarea
        v-else-if="openSection === 'description'"
        v-model="description"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
        autocomplete="off"
      />

      <!-- Item type + (inline recurrence when Recurring) -->
      <div v-else-if="openSection === 'type'" class="item-field-chips__type">
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

      <div v-else-if="openSection === 'image'" class="item-field-chips__image">
        <div v-if="imageUrl" class="item-field-chips__image-row">
          <img class="item-field-chips__image-preview" :src="imageUrl" :alt="strings.imageAlt" />
          <NcButton variant="tertiary" type="button" @click="triggerImagePick">
            <template #icon>
              <UploadIcon :size="20" />
            </template>
            {{ strings.replaceImage }}
          </NcButton>
          <NcButton variant="tertiary" type="button" @click="image = null">
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
          class="item-field-chips__image-input"
          @change="onImagePicked"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, type Component } from 'vue'
import { t, n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import TextIcon from '@icons/Text.vue'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import ImageIcon from '@icons/Image.vue'
import ImagePlusIcon from '@icons/ImagePlus.vue'
import UploadIcon from '@icons/Upload.vue'
import FormatListBulletedTypeIcon from '@icons/FormatListBulletedType.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import { RecurrenceForm } from '@/components/RecurrenceEditor'
import CategoryChipList from '@/components/CategoryChipList'
import StoreChipList from '@/components/StoreChipList'
import LabelChipList from '@/components/LabelChipList'
import ItemTypeSelector from '@/components/ItemTypeSelector'
import QuantityInput from '@/components/QuantityInput'
import ItemPricesEditor from '@/components/ItemPricesEditor'
import ItemCustomFieldsEditor from '@/components/ItemCustomFieldsEditor'
import PantryChip from '@/components/PantryChip'
import { useCategories } from '@/composables/useCategories'
import { useStores } from '@/composables/useStores'
import { useLabels } from '@/composables/useLabels'
import { useCustomFields } from '@/composables/useCustomFields'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import { entityIcon } from '@/utils/entityIcons'
import { DEFAULT_RRULE, formatRrule } from '@/utils/rrule'
import { formatPrice, storelessPrice } from '@/utils/price'
import { DEFAULT_CURRENCY } from '@/utils/currencies'
import type { ItemPrice, ItemCustomFieldValue } from '@/api/types'
import { ITEM_ONLY_SECTIONS, type ItemFieldChipsMode, type ItemFieldSection } from './types'

const props = withDefaults(
  defineProps<{
    houseId: number
    /** The list the item belongs to; scopes categories, labels and custom fields. */
    listId: number | null
    mode?: ItemFieldChipsMode
    hideImage?: boolean
    /** Currency preselected for new prices (house's last-used). */
    defaultCurrency?: string
  }>(),
  {
    mode: 'compose',
    hideImage: false,
    defaultCurrency: DEFAULT_CURRENCY,
  },
)

const categoryId = defineModel<number | null>('categoryId', { default: null })
const storeIds = defineModel<number[]>('storeIds', { default: () => [] })
const labelIds = defineModel<number[]>('labelIds', { default: () => [] })
const quantity = defineModel<string>('quantity', { default: '' })
const prices = defineModel<ItemPrice[]>('prices', { default: () => [] })
const customFieldValues = defineModel<ItemCustomFieldValue[]>('customFieldValues', {
  default: () => [],
})
const description = defineModel<string>('description', { default: '' })
const deleteOnDone = defineModel<boolean>('deleteOnDone', { default: false })
const rrule = defineModel<string | null>('rrule', { default: null })
const repeatFromCompletion = defineModel<boolean>('repeatFromCompletion', { default: false })
const image = defineModel<File | null>('image', { default: null })
const openSection = defineModel<ItemFieldSection | null>('openSection', { default: null })
// Whether the item type was picked by hand, so the chip can stay a neutral
// "Recurrence" until then.
const typePicked = defineModel<boolean>('typePicked', { default: false })

const { items: categories, load: loadCategories } = useCategories(props.houseId)
const { items: stores, load: loadStores } = useStores(props.houseId)
const { items: labels, load: loadLabels } = useLabels(props.houseId)
const { items: fieldDefs, load: loadFields } = useCustomFields(props.houseId)

function loadAll() {
  void loadCategories()
  void loadStores()
  void loadLabels()
  void loadFields()
}
loadAll()
watch(() => props.houseId, loadAll)

const hasCustomFields = computed(() =>
  fieldDefs.value.some((f) => f.listId == null || f.listId === props.listId),
)

function shows(key: ItemFieldSection): boolean {
  if (props.mode === 'defaults' && ITEM_ONLY_SECTIONS.includes(key)) return false
  if (key === 'image') return !props.hideImage
  if (key === 'customfields') return hasCustomFields.value
  return true
}

watch(
  () => [props.hideImage, props.mode],
  () => {
    if (openSection.value && !shows(openSection.value)) openSection.value = null
  },
)

function toggleSection(key: ItemFieldSection) {
  openSection.value = openSection.value === key ? null : key
}

// ----- Item type -----

type ItemType = 'staple' | 'oneTime' | 'recurring'

const currentType = computed<ItemType>(() => {
  if (deleteOnDone.value) return 'oneTime'
  if (rrule.value) return 'recurring'
  return 'staple'
})

function selectStaple() {
  rrule.value = null
  repeatFromCompletion.value = false
  deleteOnDone.value = false
  typePicked.value = true
}

function selectOneTime() {
  rrule.value = null
  repeatFromCompletion.value = false
  deleteOnDone.value = true
  typePicked.value = true
}

function selectRecurring() {
  deleteOnDone.value = false
  typePicked.value = true
  // The RecurrenceForm renders inline once currentType becomes 'recurring' and
  // live-emits its rule from there.
  if (!rrule.value) {
    rrule.value = DEFAULT_RRULE
  }
}

// ----- Image -----

const imageUrl = ref<string | null>(null)
const imageInputRef = ref<HTMLInputElement | null>(null)

watch(
  image,
  (file) => {
    if (imageUrl.value) URL.revokeObjectURL(imageUrl.value)
    imageUrl.value = file ? URL.createObjectURL(file) : null
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (imageUrl.value) URL.revokeObjectURL(imageUrl.value)
})

function triggerImagePick() {
  imageInputRef.value?.click()
}

function onImagePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  image.value = file
  input.value = ''
}

// ----- Chips -----

const selectedCategory = computed(() =>
  categoryId.value != null
    ? (categories.value.find((c) => c.id === categoryId.value) ?? null)
    : null,
)

const selectedStores = computed(() => stores.value.filter((s) => storeIds.value.includes(s.id)))

const selectedLabels = computed(() => labels.value.filter((l) => labelIds.value.includes(l.id)))

// The chip summarizes the store-less (default) price; per-store prices show in
// their grouped rows.
const priceText = computed(() => {
  const s = storelessPrice(prices.value)
  return s ? formatPrice(s) : null
})

interface Chip {
  key: ItemFieldSection
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
      : entityIcon.category,
    iconStyle: selectedCategory.value ? { color: selectedCategory.value.color } : undefined,
    filled: selectedCategory.value !== null,
  })

  const stored = selectedStores.value
  list.push({
    key: 'stores',
    text:
      stored.length === 0
        ? strings.stores
        : stored.length === 1
          ? stored[0]!.name
          : n('pantry', '%n store', '%n stores', stored.length),
    icon: stored.length === 1 ? storeIconComponent(stored[0]!.icon) : entityIcon.store,
    iconStyle: stored.length === 1 ? { color: stored[0]!.color } : undefined,
    filled: stored.length > 0,
  })

  const labeled = selectedLabels.value
  list.push({
    key: 'labels',
    text:
      labeled.length === 0
        ? strings.labels
        : labeled.length === 1
          ? labeled[0]!.name
          : n('pantry', '%n label', '%n labels', labeled.length),
    icon: labeled.length === 1 ? labelIconComponent(labeled[0]!.icon) : entityIcon.label,
    iconStyle: labeled.length === 1 ? { color: labeled[0]!.color } : undefined,
    filled: labeled.length > 0,
  })

  list.push({
    key: 'quantity',
    text: quantity.value.trim() || strings.quantity,
    icon: FormatListBulletedIcon,
    filled: quantity.value.trim().length > 0,
  })

  list.push({
    key: 'price',
    text: priceText.value ?? strings.price,
    icon: entityIcon.price,
    filled: priceText.value !== null,
  })

  list.push({
    key: 'customfields',
    text: strings.customFields,
    icon: FormatListBulletedTypeIcon,
    filled: customFieldValues.value.length > 0,
  })

  list.push({
    key: 'description',
    text: strings.description,
    icon: TextIcon,
    filled: description.value.trim().length > 0,
  })

  // Stays a neutral "Recurrence" until a type is picked by hand, or the list's
  // default already gives new items a recurrence worth showing.
  if (!typePicked.value && currentType.value === 'staple') {
    list.push({ key: 'type', text: strings.itemType, icon: RepeatIcon, filled: false })
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
    text: image.value ? strings.imageAttached : strings.image,
    icon: ImageIcon,
    filled: image.value !== null,
  })

  return list.filter((chip) => shows(chip.key))
})

function chipVariant(chip: Chip): 'primary' | 'secondary' | 'tertiary' {
  if (openSection.value === chip.key) return 'primary'
  if (chip.filled) return 'secondary'
  return 'tertiary'
}

const strings = {
  category: t('pantry', 'Category'),
  // TRANSLATORS: Noun (plural), shops where the item can be bought. Chip label.
  stores: t('pantry', 'Stores'),
  // TRANSLATORS: Noun (plural), tags on the item. Chip label.
  labels: t('pantry', 'Labels'),
  quantity: t('pantry', 'Quantity'),
  price: t('pantry', 'Price'),
  customFields: t('pantry', 'Custom fields'),
  description: t('pantry', 'Description'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'Notes, instructions, links …'),
  // TRANSLATORS: Noun, chip label for the staple / one-time / recurring choice.
  itemType: t('pantry', 'Recurrence'),
  // TRANSLATORS: An item type. A staple is a recurring household essential that stays on the list after being checked off (e.g. milk, bread).
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
.item-field-chips {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  &__row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;

    > :deep(*) {
      flex: 0 0 auto;
      cursor: pointer;
    }
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
