<template>
  <div v-if="applicableFields.length > 0" class="cf-values">
    <div v-for="field in applicableFields" :key="field.id" class="cf-values__field">
      <NcTextField
        v-if="field.type === 'text' && !field.multiline"
        :model-value="draft[field.id]?.text ?? ''"
        :label="field.name"
        :placeholder="field.hint ?? ''"
        @update:model-value="setText(field.id, $event)"
      />
      <NcTextArea
        v-else-if="field.type === 'text'"
        :model-value="draft[field.id]?.text ?? ''"
        :label="field.name"
        :placeholder="field.hint ?? ''"
        :rows="2"
        @update:model-value="setText(field.id, $event)"
      />
      <NcTextField
        v-else-if="field.type === 'number'"
        type="number"
        :model-value="draft[field.id]?.number ?? ''"
        :label="field.name"
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
        :input-label="field.name"
        :aria-label-combobox="field.name"
        :calculate-position="ncSelectCalculatePosition"
        @update:model-value="setOption(field.id, $event)"
      />
      <NcDateTimePickerNative
        v-else-if="field.type === 'date'"
        type="date"
        :model-value="draft[field.id]?.date ?? null"
        :label="field.name"
        @update:model-value="setDate(field.id, $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
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

  &__field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }
}
</style>
