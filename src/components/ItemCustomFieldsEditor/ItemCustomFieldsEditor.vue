<template>
  <div v-if="applicableFields.length > 0" class="cf-values">
    <FieldCard
      v-for="field in applicableFields"
      :key="field.id"
      :label="field.type === 'checkbox' ? undefined : field.name"
      class="cf-values__field"
    >
      <NcTextField
        v-if="field.type === 'text' && !field.multiline"
        :model-value="draft[field.id]?.text ?? ''"
        :label="field.name"
        label-outside
        :placeholder="field.hint ?? ''"
        @update:model-value="setText(field.id, $event)"
      />
      <NcTextArea
        v-else-if="field.type === 'text'"
        :model-value="draft[field.id]?.text ?? ''"
        :label="field.name"
        label-outside
        :placeholder="field.hint ?? ''"
        :rows="2"
        @update:model-value="setText(field.id, $event)"
      />
      <NcTextField
        v-else-if="field.type === 'number'"
        type="number"
        :model-value="draft[field.id]?.number ?? ''"
        :label="field.name"
        label-outside
        :placeholder="field.hint ?? ''"
        @update:model-value="setNumber(field.id, $event)"
      />
      <NcCheckboxRadioSwitch
        v-else-if="field.type === 'checkbox'"
        :model-value="draft[field.id]?.bool ?? false"
        @update:model-value="setBool(field.id, $event)"
      >
        {{ field.name }}
      </NcCheckboxRadioSwitch>
      <NcSelect
        v-else-if="field.type === 'select'"
        :model-value="selectValue(field)"
        :options="selectOptions(field)"
        label="label"
        :clearable="true"
        :placeholder="field.hint ?? ''"
        :aria-label-combobox="field.name"
        :calculate-position="ncSelectCalculatePosition"
        @update:model-value="setOption(field.id, $event)"
      />
      <NcDateTimePickerNative
        v-else-if="field.type === 'date' && field.dateMode !== 'relative'"
        type="date"
        :model-value="draft[field.id]?.date ?? null"
        :label="field.name"
        hide-label
        @update:model-value="setDate(field.id, $event)"
      />
      <div v-else-if="field.type === 'date'" class="cf-values__relative">
        <NcTextField
          type="number"
          :model-value="draft[field.id]?.offsetDays ?? ''"
          :label="field.name"
          label-outside
          :placeholder="strings.daysFromToday"
          @update:model-value="setOffset(field.id, $event)"
        />
        <div v-if="draft[field.id]?.date" class="cf-values__anchor">
          <span class="cf-values__due">{{ dueLabel(draft[field.id]?.date) }}</span>
          <NcButton variant="tertiary" @click="reanchor(field.id)">
            <template #icon><RefreshIcon :size="18" /></template>
            {{ strings.reanchor }}
          </NcButton>
        </div>
      </div>

      <div v-if="showReminderOverride(field)" class="cf-values__reminder">
        <hr class="cf-values__divider" />
        <NcCheckboxRadioSwitch
          :model-value="remindOn(field)"
          @update:model-value="setRemind(field, $event)"
        >
          {{ strings.remindMe }}
        </NcCheckboxRadioSwitch>
        <NcSelect
          v-if="remindOn(field)"
          :model-value="leadValue(field)"
          :options="leadOptions"
          :clearable="false"
          label="label"
          :input-label="strings.leadTime"
          :aria-label-combobox="strings.leadTime"
          :calculate-position="ncSelectCalculatePosition"
          @update:model-value="setLead(field, $event)"
        />
        <div v-if="differsFromDefault(field)" class="cf-values__override-note">
          <span>{{ strings.differsFromDefault }}</span>
          <NcButton variant="tertiary" @click="resetOverride(field)">
            {{ strings.useFieldDefault }}
          </NcButton>
        </div>
      </div>
    </FieldCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import RefreshIcon from '@icons/Refresh.vue'
import FieldCard from '@/components/FieldCard'
import type { FieldDefinition, ItemCustomFieldValue } from '@/api/types'
import { useCustomFields } from '@/composables/useCustomFields'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'

const props = defineProps<{
  modelValue: ItemCustomFieldValue[]
  houseId: number
  /** The item's list, so list-scoped fields apply. */
  listId: number | null
}>()
const emit = defineEmits<{ 'update:modelValue': [value: ItemCustomFieldValue[]] }>()

const strings = {
  daysFromToday: t('pantry', 'Days from today'),
  reanchor: t('pantry', 'Re-anchor date'),
  // TRANSLATORS: Toggle to enable a reminder for this item's date value.
  remindMe: t('pantry', 'Remind me'),
  leadTime: t('pantry', 'Remind'),
  // TRANSLATORS: Signals that this item's reminder differs from the field's default reminder.
  differsFromDefault: t('pantry', 'Custom reminder for this item'),
  useFieldDefault: t('pantry', 'Use field default'),
}

interface LeadOption {
  value: number
  label: string
}
const leadOptions: LeadOption[] = [
  { value: 0, label: t('pantry', 'On the day') },
  { value: 1, label: t('pantry', '1 day before') },
  { value: 2, label: t('pantry', '2 days before') },
  { value: 3, label: t('pantry', '3 days before') },
  { value: 7, label: t('pantry', '1 week before') },
]

const fields = useCustomFields(props.houseId)
onMounted(() => void fields.load())

/** House-wide ∪ this list's fields, in display order. */
const applicableFields = computed<FieldDefinition[]>(() =>
  fields.items.value.filter((f) => f.listId == null || f.listId === props.listId),
)

interface DraftValue {
  text: string
  number: string
  bool: boolean
  optionId: number | null
  date: Date | null
  // Reminder/relative fields are edited elsewhere (S6/S8); carry them through.
  offsetDays: number | null
  notifyOverride: boolean
  notifyEnabled: boolean
  notifyLeadDays: number | null
}

const draft = reactive<Record<number, DraftValue>>({})

function emptyDraft(): DraftValue {
  return {
    text: '',
    number: '',
    bool: false,
    optionId: null,
    date: null,
    offsetDays: null,
    notifyOverride: false,
    notifyEnabled: false,
    notifyLeadDays: null,
  }
}

function draftFromValue(v: ItemCustomFieldValue): DraftValue {
  return {
    text: v.valueText ?? '',
    number: v.valueNumber != null ? String(v.valueNumber) : '',
    bool: v.valueBool,
    optionId: v.valueOptionId,
    date: v.valueDate != null ? new Date(v.valueDate * 1000) : null,
    offsetDays: v.offsetDays,
    notifyOverride: v.notifyOverride,
    notifyEnabled: v.notifyEnabled,
    notifyLeadDays: v.notifyLeadDays,
  }
}

function seedFromModel(model: ItemCustomFieldValue[]): void {
  for (const key of Object.keys(draft)) delete draft[Number(key)]
  const byField = new Map(model.map((v) => [v.fieldId, v]))
  for (const field of applicableFields.value) {
    const existing = byField.get(field.id)
    draft[field.id] = existing ? draftFromValue(existing) : emptyDraft()
  }
}

// Ignore the parent echoing our own update; re-seed only on a genuinely
// different value (the dialog reopens on another item).
let lastEmitted: ItemCustomFieldValue[] | null = null

function compose(): ItemCustomFieldValue[] {
  const out: ItemCustomFieldValue[] = []
  for (const field of applicableFields.value) {
    const d = draft[field.id]
    if (!d) continue
    const base: ItemCustomFieldValue = {
      fieldId: field.id,
      valueText: null,
      valueNumber: null,
      valueBool: false,
      valueDate: null,
      valueOptionId: null,
      offsetDays: d.offsetDays,
      notifyOverride: d.notifyOverride,
      notifyEnabled: d.notifyEnabled,
      notifyLeadDays: d.notifyLeadDays,
    }
    switch (field.type) {
      case 'text':
        if (d.text.trim() === '') continue
        base.valueText = d.text
        break
      case 'number': {
        if (d.number.trim() === '') continue
        const n = Number(d.number)
        if (!Number.isFinite(n)) continue
        base.valueNumber = n
        break
      }
      case 'checkbox':
        if (!d.bool) continue
        base.valueBool = true
        break
      case 'select':
        if (d.optionId == null) continue
        base.valueOptionId = d.optionId
        break
      case 'date': {
        if (!d.date) continue
        const dt = d.date
        base.valueDate = Math.floor(
          new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()).getTime() / 1000,
        )
        break
      }
    }
    out.push(base)
  }
  return out
}

function emitAll(): void {
  const out = compose()
  lastEmitted = out
  emit('update:modelValue', out)
}

function setText(fieldId: number, v: string | number): void {
  ensure(fieldId).text = String(v)
  emitAll()
}
function setNumber(fieldId: number, v: string | number): void {
  ensure(fieldId).number = String(v)
  emitAll()
}
function setBool(fieldId: number, v: boolean): void {
  ensure(fieldId).bool = v
  emitAll()
}
function setDate(fieldId: number, v: Date | null): void {
  ensure(fieldId).date = v
  emitAll()
}

// A relative date is entered as an offset ("in N days") and materialized to an
// absolute value_date at set-time; the reminder machinery only ever sees the
// absolute date. Clearing the offset clears the date.
function setOffset(fieldId: number, v: string | number): void {
  const d = ensure(fieldId)
  const s = String(v).trim()
  if (s === '') {
    d.offsetDays = null
    d.date = null
  } else {
    const n = Math.trunc(Number(s))
    if (!Number.isFinite(n)) return
    d.offsetDays = n
    d.date = anchor(n)
  }
  emitAll()
}

// Re-anchor recomputes today + offset without changing the offset, re-arming
// the reminder (the changed value_date clears the notified stamp server-side).
function reanchor(fieldId: number): void {
  const d = draft[fieldId]
  if (!d || d.offsetDays == null) return
  d.date = anchor(d.offsetDays)
  emitAll()
}

/** Local midnight, `offset` days from today. */
function anchor(offset: number): Date {
  const day = new Date()
  day.setHours(0, 0, 0, 0)
  day.setDate(day.getDate() + offset)
  return day
}

function dueLabel(date: Date | null | undefined): string {
  if (!date) return ''
  // TRANSLATORS: Shows the materialized date of a relative date field. {date} is a formatted date.
  return t('pantry', 'Due {date}', {
    date: date.toLocaleDateString(undefined, { dateStyle: 'medium' }),
  })
}

// Per-item reminder override, offered only when the field allows it and a date is
// set (a reminder needs a value_date). Without an override the value inherits the
// field's default reminder; touching the toggle or lead marks it overridden.
function showReminderOverride(field: FieldDefinition): boolean {
  return (
    field.type === 'date' &&
    field.overridePolicy === 'item-override' &&
    draft[field.id]?.date != null
  )
}
function remindOn(field: FieldDefinition): boolean {
  const d = draft[field.id]
  if (!d) return field.notifyDefault
  return d.notifyOverride ? d.notifyEnabled : field.notifyDefault
}
function setRemind(field: FieldDefinition, on: boolean): void {
  const d = ensure(field.id)
  d.notifyOverride = true
  d.notifyEnabled = on
  if (on && d.notifyLeadDays == null) d.notifyLeadDays = field.leadDays
  emitAll()
}
function currentLead(field: FieldDefinition): number {
  const d = draft[field.id]
  if (!d) return field.leadDays
  return d.notifyOverride ? (d.notifyLeadDays ?? field.leadDays) : field.leadDays
}
function leadValue(field: FieldDefinition): LeadOption {
  const lead = currentLead(field)
  return leadOptions.find((o) => o.value === lead) ?? leadOptions[0]!
}
function setLead(field: FieldDefinition, opt: LeadOption | null): void {
  if (!opt) return
  const d = ensure(field.id)
  d.notifyOverride = true
  d.notifyEnabled = true
  d.notifyLeadDays = opt.value
  emitAll()
}
function differsFromDefault(field: FieldDefinition): boolean {
  const d = draft[field.id]
  if (!d || !d.notifyOverride) return false
  if (d.notifyEnabled !== field.notifyDefault) return true
  return d.notifyEnabled && (d.notifyLeadDays ?? field.leadDays) !== field.leadDays
}
function resetOverride(field: FieldDefinition): void {
  const d = ensure(field.id)
  d.notifyOverride = false
  d.notifyEnabled = false
  d.notifyLeadDays = null
  emitAll()
}

interface SelectOption {
  value: number
  label: string
}
function selectOptions(field: FieldDefinition): SelectOption[] {
  return field.options.map((o) => ({ value: o.id, label: o.label }))
}
function selectValue(field: FieldDefinition): SelectOption | null {
  const id = draft[field.id]?.optionId
  const opt = field.options.find((o) => o.id === id)
  return opt ? { value: opt.id, label: opt.label } : null
}
function setOption(fieldId: number, opt: SelectOption | null): void {
  ensure(fieldId).optionId = opt ? opt.value : null
  emitAll()
}

function ensure(fieldId: number): DraftValue {
  if (!draft[fieldId]) draft[fieldId] = emptyDraft()
  return draft[fieldId]!
}

seedFromModel(props.modelValue)

watch(
  () => props.modelValue,
  (model) => {
    if (lastEmitted && JSON.stringify(model) === JSON.stringify(lastEmitted)) return
    seedFromModel(model)
  },
)

// Re-seed once definitions arrive (they load async and change which fields apply).
watch(applicableFields, () => seedFromModel(props.modelValue))
</script>

<style scoped lang="scss">
.cf-values {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  &__relative {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__anchor {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
  }

  &__due {
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }

  &__reminder {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__divider {
    width: 100%;
    height: 0;
    margin: 0.15rem 0 0.25rem;
    border: 0;
    border-top: 1px solid var(--color-border);
  }

  &__override-note {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
