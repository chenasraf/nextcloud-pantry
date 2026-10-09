<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="list" class="item-defaults">
      <p class="item-defaults__intro">{{ strings.intro }}</p>
      <p v-if="!list.canEdit" class="item-defaults__readonly">{{ strings.readOnly }}</p>

      <ItemFieldChips
        v-model:category-id="draft.categoryId"
        v-model:store-ids="draft.storeIds"
        v-model:label-ids="draft.labelIds"
        v-model:quantity="draft.quantity"
        v-model:delete-on-done="deleteOnDone"
        v-model:rrule="rrule"
        v-model:repeat-from-completion="draft.recurrence.repeatFromCompletion"
        v-model:open-section="openSection"
        :type-picked="true"
        mode="defaults"
        :house-id="list.houseId"
        :list-id="list.id"
        :section-modes="sectionModes"
        :chip-overrides="chipOverrides"
        :class="{ 'item-defaults__chips--readonly': !list.canEdit }"
      >
        <template #section-header="{ section }">
          <div v-if="keyFor(section)" class="item-defaults__mode">
            <div class="item-defaults__modebar">
              <NcCheckboxRadioSwitch
                v-for="opt in modeOptions(keyFor(section)!)"
                :key="opt.value"
                :model-value="draft.modes[keyFor(section)!]"
                :value="opt.value"
                :name="`item-defaults-${section}`"
                type="radio"
                button-variant
                button-variant-grouped="horizontal"
                :disabled="!list.canEdit"
                @update:model-value="draft.modes[keyFor(section)!] = opt.value"
              >
                {{ opt.label }}
              </NcCheckboxRadioSwitch>
            </div>
            <p class="item-defaults__hint">{{ modeHint(draft.modes[keyFor(section)!]) }}</p>
          </div>
        </template>

        <template #section-customfields>
          <div class="item-defaults__fields">
            <div v-for="field in applicableFields" :key="field.id" class="item-defaults__field">
              <span class="item-defaults__field-name">{{ field.name }}</span>
              <div class="item-defaults__modebar">
                <NcCheckboxRadioSwitch
                  v-for="opt in fieldModeOptions"
                  :key="opt.value"
                  :model-value="fieldDraft(field.id).mode"
                  :value="opt.value"
                  :name="`item-defaults-field-${field.id}`"
                  type="radio"
                  button-variant
                  button-variant-grouped="horizontal"
                  :disabled="!list.canEdit"
                  @update:model-value="setFieldMode(field.id, opt.value)"
                >
                  {{ opt.label }}
                </NcCheckboxRadioSwitch>
              </div>
              <ItemCustomFieldsEditor
                v-if="fieldDraft(field.id).mode === 'fixed'"
                :model-value="[toFieldValue(field.id, fieldDraft(field.id).value)]"
                :house-id="list.houseId"
                :list-id="list.id"
                :field-ids="[field.id]"
                as-defaults
                @update:model-value="setFieldValue(field, $event)"
              />
              <p v-else class="item-defaults__hint">
                {{
                  fieldDraft(field.id).mode === 'remember'
                    ? strings.rememberHint
                    : inheritedText(field)
                }}
              </p>
            </div>
          </div>
        </template>
      </ItemFieldChips>
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="!list?.canEdit || saving" @click="save">
        {{ strings.save }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import ItemFieldChips, { type ItemFieldSection } from '@/components/ItemFieldChips'
import ItemCustomFieldsEditor from '@/components/ItemCustomFieldsEditor'
import { useCustomFields } from '@/composables/useCustomFields'
import { updateItemDefaults } from '@/api/lists'
import { defaultValueFor, toFieldValue } from '@/utils/itemDefaults'
import type {
  Checklist,
  FieldDefinition,
  ItemCustomFieldValue,
  ItemDefaultKey,
  ItemDefaultMode,
} from '@/api/types'
import { draftFromDefaults, patchFromDraft, type FieldDraft, type ItemDefaultsDraft } from './draft'

const props = defineProps<{
  open: boolean
  list: Checklist | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  saved: [list: Checklist]
}>()

const draft = ref<ItemDefaultsDraft>(draftFromDefaults(undefined))
const openSection = ref<ItemFieldSection | null>(null)
const saving = ref(false)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    draft.value = draftFromDefaults(props.list?.itemDefaults)
    openSection.value = null
  },
  { immediate: true },
)

// ----- Recurrence, bridged onto the item-type picker's flags -----

const deleteOnDone = computed({
  get: () => draft.value.recurrence.kind === 'once',
  set: (once: boolean) => {
    const r = draft.value.recurrence
    if (once) {
      draft.value.recurrence = { kind: 'once', rrule: null, repeatFromCompletion: false }
    } else if (r.kind === 'once') {
      r.kind = 'none'
    }
  },
})

const rrule = computed({
  get: () => (draft.value.recurrence.kind === 'recurring' ? draft.value.recurrence.rrule : null),
  set: (value: string | null) => {
    const r = draft.value.recurrence
    if (value) {
      r.kind = 'recurring'
      r.rrule = value
    } else if (r.kind === 'recurring') {
      draft.value.recurrence = { kind: 'none', rrule: null, repeatFromCompletion: false }
    }
  },
})

// ----- Modes -----

const SECTION_KEYS: Partial<Record<ItemFieldSection, ItemDefaultKey>> = {
  type: 'recurrence',
  stores: 'stores',
  category: 'category',
  labels: 'labels',
  quantity: 'quantity',
}

function keyFor(section: ItemFieldSection): ItemDefaultKey | null {
  return SECTION_KEYS[section] ?? null
}

const sectionModes = computed(() => {
  const modes: Partial<Record<ItemFieldSection, ItemDefaultMode>> = {}
  for (const [section, key] of Object.entries(SECTION_KEYS)) {
    modes[section as ItemFieldSection] = draft.value.modes[key!]
  }
  return modes
})

interface ModeOption {
  value: ItemDefaultMode
  label: string
}

function modeOptions(key: ItemDefaultKey): ModeOption[] {
  const options: ModeOption[] = [
    { value: 'none', label: strings.modeNone },
    { value: 'fixed', label: strings.modeFixed },
  ]
  // A quantity is too specific to one item to be worth carrying over.
  if (key !== 'quantity') options.push({ value: 'remember', label: strings.modeRemember })
  return options
}

function modeHint(mode: ItemDefaultMode): string {
  switch (mode) {
    case 'fixed':
      return strings.fixedHint
    case 'remember':
      return strings.rememberHint
    default:
      return strings.noneHint
  }
}

// ----- Custom fields -----

const customFields = computed(() => (props.list ? useCustomFields(props.list.houseId) : null))
watch(customFields, (fields) => void fields?.load(), { immediate: true })

const applicableFields = computed<FieldDefinition[]>(
  () =>
    customFields.value?.items.value.filter(
      (f) => f.listId == null || f.listId === props.list?.id,
    ) ?? [],
)

const fieldModeOptions: ModeOption[] = [
  // TRANSLATORS: Option for a custom field's list default, meaning the field's own default value applies.
  { value: 'none', label: t('pantry', 'Field default') },
  { value: 'fixed', label: t('pantry', 'Fixed') },
  { value: 'remember', label: t('pantry', 'Remember last') },
]

function fieldDraft(fieldId: number): FieldDraft {
  return draft.value.fields[fieldId] ?? { mode: 'none', value: null }
}

function setFieldMode(fieldId: number, mode: ItemDefaultMode) {
  draft.value.fields[fieldId] = { ...fieldDraft(fieldId), mode }
}

function setFieldValue(field: FieldDefinition, values: ItemCustomFieldValue[]) {
  draft.value.fields[field.id] = {
    mode: 'fixed',
    value: defaultValueFor(
      field,
      values.find((v) => v.fieldId === field.id),
    ),
  }
}

/** What a field inheriting its own default starts new items with. */
function inheritedText(field: FieldDefinition): string {
  let value: string | null = null
  switch (field.type) {
    case 'text':
      value = field.defaultText || null
      break
    case 'number':
      value = field.defaultNumber != null ? String(field.defaultNumber) : null
      break
    case 'checkbox':
      value = field.defaultBool ? strings.checked : null
      break
    case 'select':
      value = field.options.find((o) => o.id === field.defaultOptionId)?.label ?? null
      break
    case 'date':
      value =
        field.dateMode === 'relative' && field.defaultOffsetDays != null
          ? n('pantry', 'In %n day', 'In %n days', field.defaultOffsetDays)
          : null
      break
  }
  return value === null ? strings.inheritEmpty : strings.inheritValue(value)
}

const chipOverrides = computed(() => {
  const set = Object.values(draft.value.fields).filter((f) => f.mode !== 'none').length
  return {
    customfields: {
      text: set > 0 ? n('pantry', '%n field set', '%n fields set', set) : strings.customFields,
      filled: set > 0,
    },
  }
})

// ----- Save -----

async function save() {
  const list = props.list
  if (!list || !list.canEdit) return
  const patch = patchFromDraft(list.itemDefaults, draft.value)
  if (Object.keys(patch).length === 0) {
    emit('update:open', false)
    return
  }
  saving.value = true
  try {
    const updated = await updateItemDefaults(list.houseId, list.id, patch)
    emit('saved', updated)
    emit('update:open', false)
  } catch (e) {
    showError(strings.saveFailed)
    console.error(e)
  } finally {
    saving.value = false
  }
}

const strings = {
  // TRANSLATORS: Title of the dialog where a list's default values for new items are set.
  title: t('pantry', 'Item defaults'),
  intro: t('pantry', 'New items on this list start with these values.'),
  readOnly: t('pantry', 'Only people who can edit this list can change its item defaults.'),
  // TRANSLATORS: Option for a list default, meaning the field starts empty on new items.
  modeNone: t('pantry', 'Not set'),
  // TRANSLATORS: Option for a list default, meaning new items always start with the value picked here.
  modeFixed: t('pantry', 'Fixed'),
  // TRANSLATORS: Option for a list default, meaning new items reuse the value of the last item added.
  modeRemember: t('pantry', 'Remember last'),
  noneHint: t('pantry', 'New items start without a value.'),
  fixedHint: t('pantry', 'New items always start with the value below.'),
  rememberHint: t('pantry', 'New items start with the value of the last item added to this list.'),
  // TRANSLATORS: {value} is a custom field's own default value, e.g. "Uses the field default: Fridge".
  inheritValue: (value: string) => t('pantry', 'Uses the field default: {value}', { value }),
  inheritEmpty: t('pantry', 'Uses the field default, which is empty.'),
  checked: t('pantry', 'Checked'),
  customFields: t('pantry', 'Custom fields'),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  saveFailed: t('pantry', 'Could not save the item defaults'),
}
</script>

<style scoped lang="scss">
.item-defaults {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.5rem 0;

  &__intro,
  &__readonly {
    margin: 0;
    color: var(--color-text-maxcontrast);
  }

  &__chips--readonly :deep(.item-field-chips__section) {
    pointer-events: none;
    opacity: 0.7;
  }

  &__mode {
    margin-bottom: 0.75rem;
  }

  &__modebar {
    display: flex;
  }

  &__hint {
    margin: 0.35rem 0 0;
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
  }

  &__fields {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__field-name {
    font-weight: 600;
  }
}
</style>
