<template>
  <div class="cf-editor">
    <div class="cf-row">
      <label class="cf-lbl">{{ strings.name }}</label>
      <NcTextField
        v-model="model.name"
        :label="strings.name"
        :placeholder="strings.namePlaceholder"
      />
    </div>

    <div class="cf-row">
      <label class="cf-lbl">{{ strings.type }}</label>
      <div class="cf-typebar">
        <NcCheckboxRadioSwitch
          v-for="tp in typeOptions"
          :key="tp.value"
          :model-value="model.type"
          :value="tp.value"
          :name="typeGroupName"
          type="radio"
          button-variant
          button-variant-grouped="horizontal"
          :disabled="!isNew"
          @update:model-value="onTypeChange"
        >
          {{ tp.label }}
        </NcCheckboxRadioSwitch>
      </div>
      <p v-if="!isNew" class="cf-hint">{{ strings.typeLocked }}</p>
    </div>

    <div class="cf-row">
      <label class="cf-lbl">{{ strings.scope }}</label>
      <NcSelect
        v-model="scopeSel"
        :options="scopeOptions"
        :clearable="false"
        label="label"
        :input-label="''"
        :aria-label-combobox="strings.scope"
        :calculate-position="ncSelectCalculatePosition"
      />
    </div>

    <div v-if="hasHint" class="cf-row">
      <label class="cf-lbl">{{ strings.hint }}</label>
      <NcTextField
        v-model="model.hint"
        :label="strings.hint"
        :placeholder="strings.hintPlaceholder"
      />
    </div>

    <!-- text -->
    <div v-if="model.type === 'text'" class="cf-row">
      <NcCheckboxRadioSwitch v-model="model.multiline">{{
        strings.multiline
      }}</NcCheckboxRadioSwitch>
    </div>

    <!-- select options -->
    <div v-if="model.type === 'select'" class="cf-row">
      <label class="cf-lbl">{{ strings.options }}</label>
      <div ref="optionsRef" class="cf-options">
        <template v-for="row in optionRows" :key="row.type === 'option' ? 'o' + row.index : 'drop'">
          <div
            v-if="row.type === 'placeholder'"
            class="cf-option-placeholder"
            @dragover.prevent
            @drop.prevent.stop="commitOptionDrop"
          />
          <div
            v-else
            class="cf-option"
            :class="{ 'cf-option--dragging': dragIndex === row.index }"
            :data-drag-id="row.index"
            @dragover.prevent="onOptionDragOver($event, row.index)"
            @drop.prevent.stop="commitOptionDrop"
          >
            <span
              class="cf-option__handle"
              draggable="true"
              :aria-label="strings.dragHandle"
              :title="strings.dragHandle"
              @dragstart="onOptionDragStart($event, row.index)"
              @dragend="onOptionDragEnd"
            >
              <DragVerticalIcon :size="20" />
            </span>
            <NcTextField
              v-model="row.opt.label"
              :label="strings.optionLabel"
              :placeholder="strings.optionPlaceholder"
            />
            <NcButton
              variant="tertiary"
              :aria-label="strings.removeOption"
              @click="$emit('remove-option', row.index)"
            >
              <template #icon><CloseIcon :size="18" /></template>
            </NcButton>
          </div>
        </template>
      </div>
      <NcButton variant="tertiary" @click="addOption">
        <template #icon><PlusIcon :size="18" /></template>
        {{ strings.addOption }}
      </NcButton>
    </div>

    <!-- date notification config -->
    <template v-if="model.type === 'date'">
      <div class="cf-row">
        <label class="cf-lbl">{{ strings.entryMode }}</label>
        <div class="cf-typebar">
          <NcCheckboxRadioSwitch
            v-for="dm in dateModeOptions"
            :key="dm.value"
            :model-value="model.dateMode"
            :value="dm.value"
            :name="dateGroupName"
            type="radio"
            button-variant
            button-variant-grouped="horizontal"
            @update:model-value="onDateModeChange"
          >
            {{ dm.label }}
          </NcCheckboxRadioSwitch>
        </div>
      </div>
      <div v-if="model.dateMode === 'relative'" class="cf-row">
        <label class="cf-lbl">{{ strings.defaultOffset }}</label>
        <NcTextField
          v-model="model.defaultOffsetDays"
          type="number"
          :label="strings.defaultOffset"
          :placeholder="strings.defaultOffsetPlaceholder"
        />
      </div>
      <div class="cf-row">
        <NcCheckboxRadioSwitch v-model="model.notifyDefault">{{
          strings.notifyDefault
        }}</NcCheckboxRadioSwitch>
      </div>
      <div v-if="model.notifyDefault" class="cf-row">
        <label class="cf-lbl">{{ strings.leadTime }}</label>
        <NcSelect
          v-model="leadSel"
          :options="leadOptions"
          :clearable="false"
          label="label"
          :input-label="''"
          :aria-label-combobox="strings.leadTime"
          :calculate-position="ncSelectCalculatePosition"
        />
      </div>
      <div class="cf-row">
        <label class="cf-lbl">{{ strings.overridePolicy }}</label>
        <NcSelect
          v-model="overrideSel"
          :options="overrideOptions"
          :clearable="false"
          label="label"
          :input-label="''"
          :aria-label-combobox="strings.overridePolicy"
          :calculate-position="ncSelectCalculatePosition"
        />
      </div>
      <div class="cf-row">
        <NcCheckboxRadioSwitch v-model="model.stopWhenDone">{{
          strings.stopWhenDone
        }}</NcCheckboxRadioSwitch>
      </div>
    </template>

    <!-- default value -->
    <div v-if="model.type === 'text'" class="cf-row">
      <label class="cf-lbl">{{ strings.defaultValue }}</label>
      <NcTextField
        v-model="model.defaultText"
        :label="strings.defaultValue"
        :placeholder="strings.noDefault"
      />
    </div>
    <div v-else-if="model.type === 'number'" class="cf-row">
      <label class="cf-lbl">{{ strings.defaultValue }}</label>
      <NcTextField
        v-model="model.defaultNumber"
        type="number"
        :label="strings.defaultValue"
        :placeholder="strings.noDefault"
      />
    </div>
    <div v-else-if="model.type === 'checkbox'" class="cf-row">
      <NcCheckboxRadioSwitch v-model="model.defaultBool">{{
        strings.defaultChecked
      }}</NcCheckboxRadioSwitch>
    </div>
    <div v-else-if="model.type === 'select' && selectDefaultOptions.length > 1" class="cf-row">
      <label class="cf-lbl">{{ strings.defaultValue }}</label>
      <NcSelect
        v-model="defaultOptionSel"
        :options="selectDefaultOptions"
        label="label"
        :input-label="''"
        :aria-label-combobox="strings.defaultValue"
        :calculate-position="ncSelectCalculatePosition"
      />
    </div>

    <p v-if="error" class="cf-error">{{ error }}</p>

    <div class="cf-actions">
      <NcButton variant="tertiary" @click="$emit('delete')">
        <template #icon><DeleteIcon v-if="!isNew" :size="18" /></template>
        {{ isNew ? strings.cancel : strings.delete }}
      </NcButton>
      <NcButton variant="primary" :disabled="saving || !model.name.trim()" @click="$emit('save')">
        {{ saving ? strings.saving : strings.done }}
      </NcButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PlusIcon from '@icons/Plus.vue'
import CloseIcon from '@icons/Close.vue'
import DeleteIcon from '@icons/Delete.vue'
import DragVerticalIcon from '@icons/DragVertical.vue'
import type { Checklist, FieldDateMode, FieldOverridePolicy, FieldType } from '@/api/types'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { FIELD_TYPES } from './fieldTypeIcons'
import type { DraftOption, FieldDraft } from './draft'

const model = defineModel<FieldDraft>({ required: true })
const props = defineProps<{
  lists: Checklist[]
  saving?: boolean
  error?: string | null
  isNew: boolean
}>()
defineEmits<{ save: []; delete: []; cancel: []; 'remove-option': [index: number] }>()

interface Opt<T> {
  value: T
  label: string
}

// Unique radio-group names so the type / date-entry button groups don't clash
// with any other radio group mounted at the same time.
const groupSuffix = Math.random().toString(36).slice(2, 8)
const typeGroupName = `cf-type-${groupSuffix}`
const dateGroupName = `cf-datemode-${groupSuffix}`

const typeOptions: Opt<FieldType>[] = FIELD_TYPES.map((f) => ({ value: f.key, label: f.label }))
const leadOptions: Opt<number>[] = [
  { value: 0, label: t('pantry', 'On the day') },
  { value: 1, label: t('pantry', '1 day before') },
  { value: 2, label: t('pantry', '2 days before') },
  { value: 3, label: t('pantry', '3 days before') },
  { value: 7, label: t('pantry', '1 week before') },
]
const overrideOptions: Opt<FieldOverridePolicy>[] = [
  // TRANSLATORS: Reminder setting; every item uses the field's reminder, unchangeable per item.
  { value: 'field-only', label: t('pantry', 'Same reminder for every item') },
  // TRANSLATORS: Reminder setting; each item can turn the reminder on/off and set its own lead time.
  { value: 'item-override', label: t('pantry', 'Each item sets its own reminder') },
]
const dateModeOptions: Opt<FieldDateMode>[] = [
  // TRANSLATORS: Button label; the date field holds a fixed calendar date.
  { value: 'absolute', label: t('pantry', 'Absolute') },
  // TRANSLATORS: Button label; the date is entered as an offset ("in N days").
  { value: 'relative', label: t('pantry', 'Relative') },
]

const hasHint = computed(() => ['text', 'number', 'select'].includes(model.value.type))

const scopeOptions = computed<Opt<number | null>[]>(() => [
  { value: null, label: t('pantry', 'All lists') },
  ...props.lists.map((l) => ({ value: l.id, label: l.name })),
])

function proxy<T>(get: () => T, set: (v: T) => void, options: () => Opt<T>[]) {
  return computed<Opt<T>>({
    get: () => options().find((o) => o.value === get()) ?? options()[0]!,
    set: (o) => set(o.value),
  })
}

const scopeSel = proxy(
  () => model.value.listId,
  (v) => (model.value.listId = v),
  () => scopeOptions.value,
)
const leadSel = proxy(
  () => model.value.leadDays,
  (v) => (model.value.leadDays = v),
  () => leadOptions,
)
const overrideSel = proxy(
  () => model.value.overridePolicy,
  (v) => (model.value.overridePolicy = v),
  () => overrideOptions,
)

function onTypeChange(v: unknown): void {
  model.value.type = v as FieldType
}
function onDateModeChange(v: unknown): void {
  model.value.dateMode = v as FieldDateMode
}

const selectDefaultOptions = computed<Opt<number | null>[]>(() => [
  { value: null, label: t('pantry', 'No default') },
  ...model.value.options
    .filter((o) => o.id != null && o.label.trim() !== '')
    .map((o) => ({ value: o.id, label: o.label })),
])
const defaultOptionSel = computed<Opt<number | null>>({
  get: () =>
    selectDefaultOptions.value.find((o) => o.value === model.value.defaultOptionId) ??
    selectDefaultOptions.value[0]!,
  set: (o) => (model.value.defaultOptionId = o?.value ?? null),
})

function addOption(): void {
  model.value.options.push({ id: null, label: '', valueCount: 0 })
}

// -------- Option drag reorder --------
// The dragged option's index and, while dragging, the insertion slot in the
// list-without-the-dragged-item (mirrors LabelManagerDialog). The option array's
// order is only mutated on drop, so indices stay stable during a drag. Saving
// renumbers sort_order from the array position, so a reorder persists as-is.
const optionsRef = ref<HTMLElement | null>(null)
const dragIndex = ref<number | null>(null)
const dropIndex = ref<number | null>(null)

type OptionRow = { type: 'option'; opt: DraftOption; index: number } | { type: 'placeholder' }

const optionRows = computed<OptionRow[]>(() => {
  const opts = model.value.options
  if (dragIndex.value === null || dropIndex.value === null) {
    return opts.map((opt, index) => ({ type: 'option', opt, index }))
  }
  const rows: OptionRow[] = opts
    .map((opt, index) => ({ opt, index }))
    .filter((r) => r.index !== dragIndex.value)
    .map((r) => ({ type: 'option' as const, opt: r.opt, index: r.index }))
  rows.splice(Math.min(dropIndex.value, rows.length), 0, { type: 'placeholder' })
  return rows
})

function onOptionDragStart(e: DragEvent, index: number): void {
  if (!e.dataTransfer) return
  dragIndex.value = index
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(index))
  const row = (e.currentTarget as HTMLElement).closest<HTMLElement>('.cf-option')
  if (row) e.dataTransfer.setDragImage(row, 0, 0)
}

function computeOptionDrop(
  hoveredIndex: number,
  clientY: number,
  target: HTMLElement | null,
): void {
  const di = dragIndex.value
  if (di === null || di === hoveredIndex) return
  const without = model.value.options.map((_, i) => i).filter((i) => i !== di)
  const pos = without.indexOf(hoveredIndex)
  if (pos === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    dropIndex.value = clientY > rect.top + rect.height / 2 ? pos + 1 : pos
  } else {
    dropIndex.value = pos
  }
}

function onOptionDragOver(e: DragEvent, hoveredIndex: number): void {
  computeOptionDrop(hoveredIndex, e.clientY, e.currentTarget as HTMLElement | null)
}

function commitOptionDrop(): void {
  const di = dragIndex.value
  const dp = dropIndex.value
  dragIndex.value = null
  dropIndex.value = null
  if (di === null || dp === null) return
  const opts = model.value.options
  const [moved] = opts.splice(di, 1)
  if (!moved) return
  opts.splice(Math.min(dp, opts.length), 0, moved)
}

function onOptionDragEnd(): void {
  dragIndex.value = null
  dropIndex.value = null
}

useTouchReorder(optionsRef, {
  onDragStart: (id) => {
    dragIndex.value = id
    dropIndex.value = null
  },
  onReorderOver: (hoveredId, _clientX, clientY) => {
    const el = optionsRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
    computeOptionDrop(hoveredId, clientY, el)
  },
  onDrop: commitOptionDrop,
  onCancel: () => {
    dragIndex.value = null
    dropIndex.value = null
  },
})

const strings = {
  name: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Expiry, Aisle'),
  type: t('pantry', 'Type'),
  typeLocked: t('pantry', 'The type cannot be changed after the field is created.'),
  scope: t('pantry', 'Scope'),
  hint: t('pantry', 'Hint text'),
  hintPlaceholder: t('pantry', "Shown as the value's placeholder"),
  multiline: t('pantry', 'Multi-line text area'),
  options: t('pantry', 'Options'),
  optionLabel: t('pantry', 'Option'),
  optionPlaceholder: t('pantry', 'Option label'),
  addOption: t('pantry', 'Add option'),
  dragHandle: t('pantry', 'Drag to reorder'),
  removeOption: t('pantry', 'Remove option'),
  entryMode: t('pantry', 'Date entry'),
  defaultOffset: t('pantry', 'Default offset (days)'),
  defaultOffsetPlaceholder: t('pantry', 'e.g. 7'),
  notifyDefault: t('pantry', 'Remind by default'),
  leadTime: t('pantry', 'Remind'),
  overridePolicy: t('pantry', 'Reminder override'),
  stopWhenDone: t('pantry', 'Stop reminding once the item is done'),
  defaultValue: t('pantry', 'Default value'),
  defaultChecked: t('pantry', 'On by default'),
  noDefault: t('pantry', 'No default'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  done: t('pantry', 'Done'),
  saving: t('pantry', 'Saving …'),
}
</script>

<style scoped lang="scss">
.cf-editor {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.cf-row {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.cf-lbl {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-maxcontrast);
}

.cf-hint {
  font-size: 0.8rem;
  color: var(--color-text-maxcontrast);
  margin: 0.15rem 0 0 0;
}

.cf-typebar {
  display: flex;
  flex-wrap: wrap;
}

.cf-options {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
}

.cf-option {
  display: flex;
  align-items: center;
  gap: 0.25rem;

  &--dragging {
    opacity: 0.4;
  }

  &__handle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    cursor: grab;
    color: var(--color-text-maxcontrast);
    touch-action: none;

    // The icon component renders its own span/svg that doesn't inherit the
    // grab cursor on its own.
    :deep(*) {
      cursor: inherit;
    }
  }
}

.cf-option-placeholder {
  min-height: 40px;
  border: 3px dashed var(--color-primary-element);
  border-radius: var(--border-radius, 8px);
  background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
}

.cf-error {
  color: var(--color-error);
  margin: 0;
}

.cf-actions {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  margin-top: 0.25rem;
}
</style>
