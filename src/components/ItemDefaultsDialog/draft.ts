import type {
  ItemCustomFieldValue,
  ItemDefaultField,
  ItemDefaultFieldValue,
  ItemDefaultKey,
  ItemDefaultMode,
  ItemDefaults,
  ItemDefaultsPatch,
  RecurrenceDefaultValue,
} from '@/api/types'

export interface FieldDraft {
  mode: ItemDefaultMode
  value: ItemDefaultFieldValue | null
}

/** A list's item defaults as the dialog edits them: one mode per key, plus every key's value. */
export interface ItemDefaultsDraft {
  modes: Record<ItemDefaultKey, ItemDefaultMode>
  recurrence: RecurrenceDefaultValue
  storeIds: number[]
  categoryId: number | null
  labelIds: number[]
  quantity: string
  fields: Record<number, FieldDraft>
}

const STAPLE: RecurrenceDefaultValue = { kind: 'none', rrule: null, repeatFromCompletion: false }

const FIELD_VALUE_KEYS = [
  'valueText',
  'valueNumber',
  'valueBool',
  'valueDate',
  'valueOptionId',
  'offsetDays',
] as const

/**
 * Seed the editor from the stored defaults. A remembered value seeds the
 * editor too, so switching a key from "remember" to "fixed" starts from what
 * the list last used.
 */
export function draftFromDefaults(defaults: ItemDefaults | undefined): ItemDefaultsDraft {
  const fields: Record<number, FieldDraft> = {}
  for (const field of defaults?.fields ?? []) {
    fields[field.fieldId] = { mode: field.mode, value: field.value ?? null }
  }
  return {
    modes: {
      recurrence: defaults?.recurrence.mode ?? 'none',
      stores: defaults?.stores.mode ?? 'none',
      category: defaults?.category.mode ?? 'none',
      labels: defaults?.labels.mode ?? 'none',
      quantity: defaults?.quantity.mode ?? 'none',
    },
    recurrence: { ...STAPLE, ...(defaults?.recurrence.value ?? {}) },
    storeIds: [...(defaults?.stores.value ?? [])],
    categoryId: defaults?.category.value ?? null,
    labelIds: [...(defaults?.labels.value ?? [])],
    quantity: defaults?.quantity.value ?? '',
    fields,
  }
}

function draftValue(draft: ItemDefaultsDraft, key: ItemDefaultKey): unknown {
  switch (key) {
    case 'recurrence':
      return draft.recurrence
    case 'stores':
      return draft.storeIds
    case 'category':
      return draft.categoryId
    case 'labels':
      return draft.labelIds
    case 'quantity':
      return draft.quantity.trim()
  }
}

function same(a: unknown, b: unknown): boolean {
  return JSON.stringify(a ?? null) === JSON.stringify(b ?? null)
}

/**
 * Only the keys the dialog actually changed. A key left on "remember" is not
 * sent, so saving never wipes what the list has learned since it was opened.
 */
export function patchFromDraft(
  original: ItemDefaults | undefined,
  draft: ItemDefaultsDraft,
): ItemDefaultsPatch {
  const patch: ItemDefaultsPatch = {}
  const keys: ItemDefaultKey[] = ['recurrence', 'stores', 'category', 'labels', 'quantity']
  for (const key of keys) {
    const before = original?.[key] ?? { mode: 'none' as const }
    const mode = draft.modes[key]
    const value = draftValue(draft, key)
    if (mode === before.mode && (mode !== 'fixed' || same(value, before.value))) continue
    const entry = mode === 'fixed' ? { mode, value } : { mode }
    Object.assign(patch, { [key]: entry })
  }

  const fields: NonNullable<ItemDefaultsPatch['fields']> = []
  const stored = new Map((original?.fields ?? []).map((f) => [f.fieldId, f]))
  const ids = new Set([...stored.keys(), ...Object.keys(draft.fields).map(Number)])
  for (const fieldId of ids) {
    const before: ItemDefaultField = stored.get(fieldId) ?? { fieldId, mode: 'none' }
    const now = draft.fields[fieldId] ?? { mode: 'none', value: null }
    if (now.mode === before.mode && (now.mode !== 'fixed' || same(now.value, before.value))) {
      continue
    }
    fields.push(now.mode === 'fixed' ? { fieldId, ...now } : { fieldId, mode: now.mode })
  }
  if (fields.length > 0) patch.fields = fields

  return patch
}

/** A field default in the shape the custom-field editor works with. */
export function toFieldValue(
  fieldId: number,
  value: ItemDefaultFieldValue | null,
): ItemCustomFieldValue {
  return {
    fieldId,
    valueText: value?.valueText ?? null,
    valueNumber: value?.valueNumber ?? null,
    valueBool: value?.valueBool ?? false,
    valueDate: value?.valueDate ?? null,
    valueOptionId: value?.valueOptionId ?? null,
    offsetDays: value?.offsetDays ?? null,
    notifyOverride: false,
    notifyEnabled: false,
    notifyLeadDays: null,
  }
}

/** The editor's value, keeping only the columns a default carries. */
export function fromFieldValue(
  value: ItemCustomFieldValue | undefined,
): ItemDefaultFieldValue | null {
  if (!value) return null
  const out: ItemDefaultFieldValue = {}
  for (const key of FIELD_VALUE_KEYS) {
    if (value[key] != null && value[key] !== false) Object.assign(out, { [key]: value[key] })
  }
  return out
}
