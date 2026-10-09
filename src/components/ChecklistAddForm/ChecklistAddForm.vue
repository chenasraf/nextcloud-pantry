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

      <ItemFieldChips
        v-model:category-id="categoryId"
        v-model:store-ids="storeIds"
        v-model:label-ids="labelIds"
        v-model:quantity="quantity"
        v-model:prices="prices"
        v-model:custom-field-values="customFieldValues"
        v-model:description="description"
        v-model:delete-on-done="deleteOnDone"
        v-model:rrule="rrule"
        v-model:repeat-from-completion="repeatFromCompletion"
        v-model:image="pendingImage"
        v-model:open-section="openSection"
        v-model:type-picked="userPickedType"
        :house-id="houseId"
        :list-id="effectiveListId"
        :hide-image="multiple"
        :default-currency="defaultCurrency"
      >
        <template #chips-after>
          <PantryChip
            v-if="!multiple"
            :variant="barcode ? 'secondary' : 'tertiary'"
            @click="barcodeDialogOpen = true"
          >
            <template #icon>
              <BarcodeScanIcon :size="14" />
            </template>
            {{ barcode ? strings.barcodeAttached : strings.barcode }}
          </PantryChip>
          <NcButton
            v-if="showDefaultsButton"
            class="checklist-add__defaults"
            variant="tertiary-no-background"
            type="button"
            :aria-label="strings.itemDefaults"
            :title="strings.itemDefaults"
            @click="$emit('open-defaults')"
          >
            <template #icon>
              <TuneVariantIcon :size="18" />
            </template>
          </NcButton>
        </template>
      </ItemFieldChips>

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
import { computed, ref, watch } from 'vue'
import { extract, token_set_ratio } from 'fuzzball'
import { t } from '@nextcloud/l10n'
import { showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import BarcodeScanIcon from '@icons/BarcodeScan.vue'
import TuneVariantIcon from '@icons/TuneVariant.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import { defaultCustomFieldValues } from '@/components/ItemCustomFieldsEditor/defaults'
import ItemFieldChips, { type ItemFieldSection } from '@/components/ItemFieldChips'
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
import { useBarcodeFill } from '@/composables/useBarcodeFill'
import { listArchivedItems } from '@/api/lists'
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'
import { DEFAULT_RRULE } from '@/utils/rrule'
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
  RecurrenceKind,
} from '@/api/types'

const props = withDefaults(
  defineProps<{
    houseId: number
    adding: boolean
    /** Recurrence new items start with, resolved from the target list's default. */
    defaultRecurrenceKind?: RecurrenceKind
    defaultRrule?: string | null
    defaultRepeatFromCompletion?: boolean
    /**
     * The list follows the last item added, so the form reports back whatever
     * recurrence was used. Pinned defaults are left alone.
     */
    remembersRecurrence?: boolean
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
    /** Offer a shortcut to the list's item defaults at the end of the chip row. */
    showDefaultsButton?: boolean
  }>(),
  {
    defaultRecurrenceKind: 'none',
    defaultRrule: null,
    defaultRepeatFromCompletion: false,
    remembersRecurrence: false,
    requireListSelector: false,
    availableLists: () => [],
    reuseCandidates: () => [],
    currentListId: null,
    defaultCurrency: DEFAULT_CURRENCY,
    showDefaultsButton: false,
  },
)

const emit = defineEmits<{
  add: [input: ItemInput, pendingImage: File | null, targetListId: number | null]
  'update:recurrenceDefault': [
    value: { kind: RecurrenceKind; rrule: string | null; repeatFromCompletion: boolean },
  ]
  'reuse-existing': [item: ChecklistItem]
  'open-defaults': []
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
const deleteOnDone = ref(false)
const openSection = ref<ItemFieldSection | null>(null)
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
const userPickedType = ref(false)

// Loaded by the chips; read here for reuse-suggestion rows and barcode matching.
const { items: categories, categoriesForList } = useCategories(props.houseId)
const { items: stores } = useStores(props.houseId)
const { items: labels } = useLabels(props.houseId)
const { items: fieldDefs } = useCustomFields(props.houseId)

// New items start pre-filled with each applicable field's default value.
watch(
  [fieldDefs, effectiveListId],
  () => {
    customFieldValues.value = defaultCustomFieldValues(fieldDefs.value, effectiveListId.value)
  },
  { immediate: true },
)

/** Start a fresh item on the target list's default recurrence. */
function applyRecurrenceDefault() {
  const kind = props.defaultRecurrenceKind
  deleteOnDone.value = kind === 'once'
  rrule.value = kind === 'recurring' ? (props.defaultRrule ?? DEFAULT_RRULE) : null
  repeatFromCompletion.value = kind === 'recurring' && props.defaultRepeatFromCompletion
}

applyRecurrenceDefault()

watch(
  () => [props.defaultRecurrenceKind, props.defaultRrule, props.defaultRepeatFromCompletion],
  () => applyRecurrenceDefault(),
)

watch(multiple, (on) => {
  if (on) pendingImage.value = null
})

/** The recurrence the composed item carries, in the list default's own terms. */
const currentRecurrenceKind = computed<RecurrenceKind>(() =>
  deleteOnDone.value ? 'once' : rrule.value ? 'recurring' : 'none',
)

// ----- Barcode -----
//
// A resolved barcode prefills the draft: name, a best-match category, and the
// product image — each one only if the user leaves it enabled.

const { barcodeFill } = useBarcodeFill()

function onBarcodeResolved(ean: string, result: BarcodeResult | null) {
  if (!result) {
    // Unknown barcode: leave the form untouched (cleared or with whatever the
    // user had already typed) and just let them know.
    showWarning(t('pantry', 'No product found for barcode {ean}.', { ean }))
    return
  }
  barcode.value = ean
  if (barcodeFill.name) {
    name.value = result.name
  }
  if (barcodeFill.category) {
    const matched = matchCategory(result.category)
    if (matched && categoryId.value == null) {
      categoryId.value = matched.id
    }
  }
  if (barcodeFill.image && result.imageUrl && !multiple.value) {
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
    pendingImage.value = new File([blob], `barcode-product.${ext}`, { type: blob.type })
  } catch {
    // Ignore — image prefill is a bonus, never a blocker.
  }
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

/**
 * Report the recurrence just used, so a list that follows the last item added
 * starts the next one the same way.
 */
function rememberRecurrence(usedRrule: string | null, usedFromCompletion: boolean) {
  if (!props.remembersRecurrence) return
  const kind = currentRecurrenceKind.value
  const unchanged =
    kind === props.defaultRecurrenceKind &&
    (kind !== 'recurring' ||
      (usedRrule === props.defaultRrule &&
        usedFromCompletion === props.defaultRepeatFromCompletion))
  if (unchanged) return
  emit('update:recurrenceDefault', {
    kind,
    rrule: usedRrule,
    repeatFromCompletion: usedFromCompletion,
  })
}

defineExpose({ clearName })

// ----- Submit -----

function submitAdd() {
  const names = itemNames.value
  if (names.length === 0) return
  if (props.requireListSelector && targetListId.value === null) return
  const once = deleteOnDone.value
  const usedRrule = once ? null : rrule.value
  const usedFromCompletion = once ? false : repeatFromCompletion.value
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
        rrule: usedRrule,
        repeatFromCompletion: usedFromCompletion,
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
  barcode.value = null
  rememberRecurrence(usedRrule, usedFromCompletion)
  // A list that follows the last item added keeps the recurrence just used, so
  // a run of matching items needs picking it only once; a pinned default wins
  // back the next item.
  if (!props.remembersRecurrence) {
    applyRecurrenceDefault()
  }
  userPickedType.value = false
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
  // TRANSLATORS: Noun, chip button that opens the barcode lookup dialog to fill in the item from a product barcode.
  barcode: t('pantry', 'Barcode'),
  // TRANSLATORS: State of the barcode chip once a barcode has been attached to the item being added.
  barcodeAttached: t('pantry', 'Barcode attached'),
  // TRANSLATORS: Tooltip of the button that opens the values new items on this list start with.
  itemDefaults: t('pantry', 'Item defaults'),
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

  // Sits at the end of the chip row, apart from the field chips.
  &__defaults {
    margin-inline-start: auto;
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
