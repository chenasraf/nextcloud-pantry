<template>
  <FieldCard :label="formLabel" class="checklist-add-card">
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
        <PantryChip
          v-if="!multiple"
          :variant="barcode ? 'secondary' : 'tertiary'"
          class="checklist-add__chip"
          @click="barcodeDialogOpen = true"
        >
          <template #icon>
            <BarcodeScanIcon :size="14" />
          </template>
          {{ barcode ? strings.barcodeAttached : strings.barcode }}
        </PantryChip>
      </div>

      <div v-if="openSection" class="checklist-add__section">
        <CategoryChipList
          v-if="openSection === 'category'"
          v-model="categoryId"
          :house-id="houseId"
          :list-id="effectiveListId"
        />

        <StoreChipList
          v-else-if="openSection === 'stores'"
          v-model="storeIds"
          :house-id="houseId"
        />

        <LabelChipList
          v-else-if="openSection === 'labels'"
          v-model="labelIds"
          :house-id="houseId"
          :list-id="effectiveListId"
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
          :list-id="effectiveListId"
        />

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

      <!-- Live "reuse existing item" suggestions. Mutually exclusive with an open
         meta tray (they share this vertical slot) — only shown while typing a
         single item name. -->
      <div v-if="reuseMatches.length > 0" class="checklist-add__suggestions">
        <span class="checklist-add__suggestions-header">{{ strings.suggestionsHeader }}</span>
        <ul class="checklist-add__suggestions-list">
          <ChecklistItemRow
            v-for="match in reuseMatches"
            :key="match.id"
            :item="match"
            :category="categoryForItem(match.categoryId)"
            :stores="storesForItem(match.storeIds)"
            :labels="labelsForItem(match.labelIds)"
            :house-id="houseId"
            suggestion
            @select="$emit('reuse-existing', $event)"
          />
        </ul>
      </div>

      <BarcodeLookupDialog v-model:open="barcodeDialogOpen" @resolved="onBarcodeResolved" />
    </form>
  </FieldCard>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, type Component } from 'vue'
import { extract, token_set_ratio } from 'fuzzball'
import { t, n } from '@nextcloud/l10n'
import { showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import TextIcon from '@icons/Text.vue'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import ImageIcon from '@icons/Image.vue'
import ImagePlusIcon from '@icons/ImagePlus.vue'
import UploadIcon from '@icons/Upload.vue'
import BarcodeScanIcon from '@icons/BarcodeScan.vue'
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
import { defaultCustomFieldValues } from '@/components/ItemCustomFieldsEditor/defaults'
import PantryChip from '@/components/PantryChip'
import FieldCard from '@/components/FieldCard'
import BarcodeLookupDialog from '@/components/BarcodeLookupDialog'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { type BarcodeResult } from '@/api/barcode'
import { useCategories } from '@/composables/useCategories'
import { useStores } from '@/composables/useStores'
import { useLabels } from '@/composables/useLabels'
import { useCustomFields } from '@/composables/useCustomFields'
import { useSuggestArchivedItems } from '@/composables/useSuggestArchivedItems'
import { listArchivedItems } from '@/api/lists'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'
import { entityIcon } from '@/utils/entityIcons'
import { formatRrule } from '@/utils/rrule'
import { formatPrice, storelessPrice } from '@/utils/price'
import { DEFAULT_CURRENCY } from '@/utils/currencies'
import type { ItemInput } from '@/api/lists'
import type {
  Checklist,
  ChecklistItem,
  Category,
  Store,
  Label,
  ItemPrice,
  ItemCustomFieldValue,
} from '@/api/types'

type SectionKey =
  | 'category'
  | 'labels'
  | 'stores'
  | 'quantity'
  | 'price'
  | 'customfields'
  | 'description'
  | 'type'
  | 'image'

const props = withDefaults(
  defineProps<{
    houseId: number
    adding: boolean
    deleteOnDoneDefault?: boolean
    requireListSelector?: boolean
    availableLists?: Checklist[]
    /**
     * Active items eligible to be surfaced as live reuse suggestions. In the meta
     * "All lists" view this spans every list; the form narrows them to the
     * currently-picked target list. Empty disables suggestions (e.g. no check
     * permission).
     */
    reuseCandidates?: ChecklistItem[]
    /** The list id in single-list mode, used to scope reuse suggestions. */
    currentListId?: number | null
    /** Currency preselected for new prices (house's last-used). */
    defaultCurrency?: string
  }>(),
  {
    deleteOnDoneDefault: false,
    requireListSelector: false,
    availableLists: () => [],
    reuseCandidates: () => [],
    currentListId: null,
    defaultCurrency: DEFAULT_CURRENCY,
  },
)

const emit = defineEmits<{
  add: [input: ItemInput, pendingImage: File | null, targetListId: number | null]
  'update:deleteOnDoneDefault': [value: boolean]
  'reuse-existing': [item: ChecklistItem]
}>()

const name = ref('')
const multiple = ref(false)
const description = ref('')
const quantity = ref('')
const prices = ref<ItemPrice[]>([])
const customFieldValues = ref<ItemCustomFieldValue[]>([])
const categoryId = ref<number | null>(null)
const storeIds = ref<number[]>([])
const labelIds = ref<number[]>([])
const targetListId = ref<number | null>(null)
const rrule = ref<string | null>(null)
const repeatFromCompletion = ref(false)
const deleteOnDone = ref(props.deleteOnDoneDefault)
const openSection = ref<SectionKey | null>(null)
const barcode = ref<string | null>(null)
const barcodeDialogOpen = ref(false)

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

// The list the new item lands on: the picked target in meta "All lists" mode,
// otherwise the list in focus. Drives category scoping and reuse suggestions.
const effectiveListId = computed<number | null>(() =>
  props.requireListSelector ? targetListId.value : props.currentListId,
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
const { items: categories, load: loadCategories, categoriesForList } = useCategories(props.houseId)
void loadCategories()

const { items: stores, load: loadStores } = useStores(props.houseId)
void loadStores()

const { items: labels, load: loadLabels } = useLabels(props.houseId)
void loadLabels()

const { items: fieldDefs, load: loadFields } = useCustomFields(props.houseId)
void loadFields()
const hasCustomFields = computed(() =>
  fieldDefs.value.some((f) => f.listId == null || f.listId === effectiveListId.value),
)
// New items start pre-filled with each applicable field's default value.
watch(
  [fieldDefs, effectiveListId],
  () => {
    customFieldValues.value = defaultCustomFieldValues(fieldDefs.value, effectiveListId.value)
  },
  { immediate: true },
)

watch(
  () => props.houseId,
  () => {
    void useCategories(props.houseId).load()
    void useStores(props.houseId).load()
    void useLabels(props.houseId).load()
  },
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

// ----- Barcode -----
//
// A resolved barcode prefills the draft: name, a best-match category, and the
// product image.

function onBarcodeResolved(ean: string, result: BarcodeResult | null) {
  if (!result) {
    // Unknown barcode: leave the form untouched (cleared or with whatever the
    // user had already typed) and just let them know.
    showWarning(t('pantry', 'No product found for barcode {ean}.', { ean }))
    return
  }
  barcode.value = ean
  name.value = result.name
  const matched = matchCategory(result.category)
  if (matched && categoryId.value == null) {
    categoryId.value = matched.id
  }
  if (result.imageUrl && !multiple.value) {
    void prefillImageFromUrl(result.imageUrl)
  }
}

/**
 * Fuzzy-match a provider category hint (e.g. "Beverages") against the house's
 * own categories, reusing the fuzzball scorer used for reuse suggestions.
 */
function matchCategory(hint: string | null): Category | null {
  // Only auto-assign a category the target list actually offers (its own scoped
  // categories plus globals) so a barcode never pulls in another list's category.
  const candidates = categoriesForList(effectiveListId.value)
  if (!hint || candidates.length === 0) return null
  const results = extract(hint, candidates, {
    processor: (c: Category) => c.name,
    scorer: token_set_ratio,
    limit: 1,
    cutoff: 65,
  })
  return results.length > 0 ? (results[0]![0] as Category) : null
}

/**
 * Download the product image from its URL and stage it as the pending item
 * image. Best-effort: a CORS failure or non-image response is silently ignored.
 */
async function prefillImageFromUrl(url: string) {
  try {
    const resp = await fetch(url)
    if (!resp.ok) return
    const blob = await resp.blob()
    if (!blob.type.startsWith('image/')) return
    const ext = blob.type.split('/')[1] || 'jpg'
    const file = new File([blob], `barcode-product.${ext}`, { type: blob.type })
    revokeObjectUrl()
    pendingImage.value = file
    pendingImageObjectUrl.value = URL.createObjectURL(file)
  } catch {
    // Ignore — image prefill is a bonus, never a blocker.
  }
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

const formLabel = computed(() => (multiple.value ? strings.addItems : strings.addItem))

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

  if (hasCustomFields.value) {
    list.push({
      key: 'customfields',
      text: strings.customFields,
      icon: FormatListBulletedTypeIcon,
      filled: customFieldValues.value.length > 0,
    })
  }

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

// ----- Reuse suggestions -----
//
// While the user types a single item name (and no meta tray is open), surface
// existing items on the target list that fuzzily match. Tapping one emits
// `reuse-existing`; the parent confirms and reuses it.

// Candidates on the list the new item would be added to: the picked target in
// meta mode, otherwise the list in focus.
const reuseTargetListId = effectiveListId

// When the pref is on, archived items on the target list are folded into the
// same fuzzy pool. They are fetched lazily — the first time the user searches a
// given list — and cached per list so repeat searches don't refetch.
const { suggestArchivedItems } = useSuggestArchivedItems()
const archivedByList = ref<Map<number, ChecklistItem[]>>(new Map())
const archivedLoading = new Set<number>()

async function ensureArchivedLoaded(listId: number): Promise<void> {
  if (!suggestArchivedItems.value) return
  if (archivedByList.value.has(listId) || archivedLoading.has(listId)) return
  archivedLoading.add(listId)
  try {
    const loaded = await listArchivedItems(props.houseId, listId)
    archivedByList.value = new Map(archivedByList.value).set(listId, loaded)
  } catch {
    // Leave it unloaded so the next search retries.
  } finally {
    archivedLoading.delete(listId)
  }
}

// The list being actively searched (single-item name typed, no meta tray open),
// or null when suggestions are suppressed. Drives the lazy archive fetch.
const searchListId = computed<number | null>(() => {
  if (multiple.value || openSection.value !== null) return null
  if (!name.value.trim()) return null
  const listId = reuseTargetListId.value
  return listId != null && listId > 0 ? listId : null
})

watch(
  [searchListId, suggestArchivedItems],
  ([listId, on]) => {
    if (listId != null && on) void ensureArchivedLoaded(listId)
  },
  { immediate: true },
)

const reuseMatches = computed<ChecklistItem[]>(() => {
  // Never in bulk mode, only while typing a name, and never while a meta tray
  // occupies the same slot.
  if (multiple.value || openSection.value !== null) return []
  const query = name.value.trim()
  if (!query) return []
  const listId = reuseTargetListId.value
  if (listId == null || listId <= 0) return []
  const active = props.reuseCandidates.filter((i) => i.listId === listId)
  // Fold in archived items only when the pref is on. Drop any that have since
  // become active (e.g. just reused) so they aren't offered twice.
  const activeIds = new Set(active.map((i) => i.id))
  const archived = suggestArchivedItems.value
    ? (archivedByList.value.get(listId) ?? []).filter(
        (i) => i.listId === listId && !activeIds.has(i.id),
      )
    : []
  const candidates = [...active, ...archived]
  if (candidates.length === 0) return []
  // token_set_ratio is the fuzzball scorer that reproduces the intended ranking
  // (e.g. "Organic milk" surfaces "Milk" as the top match). Cutoff 60 lets weak
  // single-word overlaps through; results come back sorted best-first.
  const results = extract(query, candidates, {
    processor: (i: ChecklistItem) => i.name,
    scorer: token_set_ratio,
    limit: 6,
    cutoff: 60,
  })
  return results.map((r) => r[0] as ChecklistItem)
})

function categoryForItem(id: number | null): Category | null {
  return id == null ? null : (categories.value.find((c) => c.id === id) ?? null)
}

function storesForItem(ids: number[] | null | undefined): Store[] {
  if (!ids || ids.length === 0) return []
  return ids.map((id) => stores.value.find((s) => s.id === id)).filter((s): s is Store => s != null)
}

function labelsForItem(ids: number[] | null | undefined): Label[] {
  if (!ids || ids.length === 0) return []
  return ids.map((id) => labels.value.find((l) => l.id === id)).filter((l): l is Label => l != null)
}

// The parent clears the name (and keeps focus) after a confirmed reuse.
function clearName() {
  name.value = ''
}

defineExpose({ clearName })

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
        storeIds: storeIds.value,
        labelIds: labelIds.value,
        prices: prices.value,
        customFields: customFieldValues.value,
        rrule: once ? null : rrule.value,
        repeatFromCompletion: once ? false : repeatFromCompletion.value,
        deleteOnDone: once,
        // A barcode identifies a single product, so only the first line of a
        // bulk add carries it.
        barcode: index === 0 ? barcode.value : null,
      },
      index === 0 ? pendingImage.value : null,
      targetListId.value,
    )
  })
  // Reset form — keep the chosen list so users can add multiple items in a row.
  name.value = ''
  description.value = ''
  quantity.value = ''
  // Drop the amounts; the next item's default currency comes from the house's
  // remembered currency (updated after the add).
  prices.value = []
  customFieldValues.value = defaultCustomFieldValues(fieldDefs.value, effectiveListId.value)
  categoryId.value = null
  storeIds.value = []
  labelIds.value = []
  rrule.value = null
  repeatFromCompletion.value = false
  barcode.value = null
  // Keep the user's last-chosen list default.
  deleteOnDone.value = props.deleteOnDoneDefault
  userPickedType.value = false
  revokeObjectUrl()
  pendingImage.value = null
  openSection.value = null
}

const strings = {
  // TRANSLATORS: Title above the item compose form, when adding a single item.
  addItem: t('pantry', 'Add item'),
  // TRANSLATORS: Title above the item compose form, when adding several items at once.
  addItems: t('pantry', 'Add items'),
  // TRANSLATORS: Verb. Label of the button that adds the new item to the list.
  add: t('pantry', 'Add'),
  // TRANSLATORS: Label of a toggle that switches the form to adding several items at once.
  multiple: t('pantry', 'Multiple'),
  multipleHint: t('pantry', 'Separate items by new lines'),
  nameLabel: t('pantry', 'Item name'),
  namePlaceholder: t('pantry', 'e.g. Milk'),
  list: t('pantry', 'Pick a list …'),
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
  itemType: t('pantry', 'Item type'),
  descriptionPlaceholder: t('pantry', 'Notes, instructions, links …'),
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
  // TRANSLATORS: Noun, chip button that opens the barcode lookup dialog to fill in the item from a product barcode.
  barcode: t('pantry', 'Barcode'),
  // TRANSLATORS: State of the barcode chip once a barcode has been attached to the item being added.
  barcodeAttached: t('pantry', 'Barcode attached'),
  // TRANSLATORS: Header above the list of existing items that match what the user is typing, offered so they can reuse one instead of adding a duplicate.
  suggestionsHeader: t('pantry', 'Already on this list'),
}
</script>

<style scoped lang="scss">
.checklist-add-card {
  margin-bottom: 1.5rem;
}

.checklist-add {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

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

  &__suggestions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0.25rem;
    // Matches the colorless PantryChip border so the panel lifts off the
    // field-card background instead of blending into it.
    border: 1px solid color-mix(in srgb, var(--color-border) 55%, var(--color-border-maxcontrast));
    border-radius: var(--border-radius-large, 8px);
    background: var(--color-background-hover);
  }

  &__suggestions-header {
    padding: 0 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  &__suggestions-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;

    // Divide the suggestion rows so they read as a compact panel.
    > :not(:last-child) {
      border-bottom: 1px solid var(--color-border);
    }
  }
}
</style>
