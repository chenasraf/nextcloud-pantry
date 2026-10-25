import { defaultCustomFieldValues } from '@/components/ItemCustomFieldsEditor/defaults'
import type {
  FieldDefinition,
  ItemCustomFieldValue,
  ItemDefaultField,
  ItemDefaultFieldValue,
  ItemDefaults,
  ItemDefaultsPatch,
  RecurrenceDefaultValue,
  StockMode,
} from '@/api/types'
import { DEFAULT_RRULE } from '@/utils/rrule'

/** Epoch seconds at local midnight, `offset` days from today. */
function anchorEpoch(offset: number): number {
  const day = new Date()
  day.setHours(0, 0, 0, 0)
  day.setDate(day.getDate() + offset)
  return Math.floor(day.getTime() / 1000)
}

/**
 * A field default in the shape items and the custom-field editor use. A
 * relative date default holds only its offset, so it is anchored to today.
 */
export function toFieldValue(
  fieldId: number,
  value: ItemDefaultFieldValue | null | undefined,
): ItemCustomFieldValue {
  const offsetDays = value?.offsetDays ?? null
  return {
    fieldId,
    valueText: value?.valueText ?? null,
    valueNumber: value?.valueNumber ?? null,
    valueBool: value?.valueBool ?? false,
    valueDate: value?.valueDate ?? (offsetDays != null ? anchorEpoch(offsetDays) : null),
    valueOptionId: value?.valueOptionId ?? null,
    offsetDays,
    notifyOverride: false,
    notifyEnabled: false,
    notifyLeadDays: null,
  }
}

/**
 * The part of an item's value a list default keeps: only the column the
 * field's type uses, the same way the server stores it. Reminder settings
 * belong to a single item and are dropped.
 */
export function defaultValueFor(
  field: FieldDefinition,
  value: ItemCustomFieldValue | undefined,
): ItemDefaultFieldValue | null {
  if (!value) return null
  switch (field.type) {
    case 'text':
      return { valueText: value.valueText }
    case 'number':
      return { valueNumber: value.valueNumber }
    case 'checkbox':
      return { valueBool: value.valueBool }
    case 'select':
      return { valueOptionId: value.valueOptionId }
    case 'date':
      return field.dateMode === 'relative'
        ? { offsetDays: value.offsetDays }
        : { valueDate: value.valueDate }
  }
  return null
}

export function sameValue(a: unknown, b: unknown): boolean {
  return JSON.stringify(a ?? null) === JSON.stringify(b ?? null)
}

/** What a new item's chips start with. */
export interface ItemStartValues {
  categoryId: number | null
  storeIds: number[]
  labelIds: number[]
  quantity: string
  recurrence: RecurrenceDefaultValue
  customFieldValues: ItemCustomFieldValue[]
  /** Read only on inventories. */
  stock: {
    stockMode: StockMode
    target: string
    reorderPoint: string
    shelfLifeDays: number | null
  }
}

const STAPLE: RecurrenceDefaultValue = { kind: 'none', rrule: null, repeatFromCompletion: false }

/** A key pre-fills only when it is pinned or has remembered something. */
function prefill<T>(entry: { mode: string; value?: T | null } | undefined): T | null {
  if (!entry || entry.mode === 'none') return null
  return entry.value ?? null
}

/**
 * Resolve a list's item defaults into the values a new item starts with.
 * Custom fields start from their own definition's default, which a list
 * default replaces.
 */
export function startValues(
  defaults: ItemDefaults | null | undefined,
  fieldDefs: FieldDefinition[],
  listId: number | null,
): ItemStartValues {
  const recurrence = prefill(defaults?.recurrence) ?? STAPLE
  const fieldValues = new Map(
    defaultCustomFieldValues(fieldDefs, listId).map((v) => [v.fieldId, v]),
  )
  for (const field of defaults?.fields ?? []) {
    const value = prefill(field)
    if (value) fieldValues.set(field.fieldId, toFieldValue(field.fieldId, value))
  }
  return {
    categoryId: prefill(defaults?.category),
    storeIds: [...(prefill(defaults?.stores) ?? [])],
    labelIds: [...(prefill(defaults?.labels) ?? [])],
    quantity: prefill(defaults?.quantity) ?? '',
    recurrence: {
      ...recurrence,
      rrule: recurrence.kind === 'recurring' ? (recurrence.rrule ?? DEFAULT_RRULE) : null,
    },
    customFieldValues: [...fieldValues.values()],
    stock: {
      stockMode: prefill(defaults?.stockMode) ?? 'count',
      target: prefill(defaults?.target) ?? '',
      reorderPoint: prefill(defaults?.reorderPoint) ?? '',
      shelfLifeDays: prefill(defaults?.shelfLifeDays),
    },
  }
}

/** What the item just added actually used. Only an inventory item has a stock mode. */
export type ItemUsedValues = Omit<ItemStartValues, 'quantity' | 'stock'> & { stockMode?: StockMode }

/**
 * The write-back for keys that follow the last item added: only those in
 * "remember" mode whose value differs from what is already remembered.
 */
export function rememberPatch(
  defaults: ItemDefaults | null | undefined,
  used: ItemUsedValues,
  fieldDefs: FieldDefinition[],
): ItemDefaultsPatch {
  const patch: ItemDefaultsPatch = {}
  if (!defaults) return patch

  const remember = <T>(
    entry: { mode: string; value?: T | null },
    value: T,
    write: (v: T) => void,
  ) => {
    if (entry.mode === 'remember' && !sameValue(entry.value, value)) write(value)
  }
  if (defaults.recurrence) {
    remember(defaults.recurrence, used.recurrence, (value) => (patch.recurrence = { value }))
  }
  remember(defaults.stores, used.storeIds, (value) => (patch.stores = { value }))
  remember(defaults.category, used.categoryId, (value) => (patch.category = { value }))
  remember(defaults.labels, used.labelIds, (value) => (patch.labels = { value }))
  if (defaults.stockMode && used.stockMode) {
    remember(defaults.stockMode, used.stockMode, (value) => (patch.stockMode = { value }))
  }

  const defsById = new Map(fieldDefs.map((f) => [f.id, f]))
  const usedById = new Map(used.customFieldValues.map((v) => [v.fieldId, v]))
  const fields: NonNullable<ItemDefaultsPatch['fields']> = []
  for (const field of defaults.fields) {
    const def = defsById.get(field.fieldId)
    if (field.mode !== 'remember' || !def) continue
    const value = defaultValueFor(def, usedById.get(field.fieldId))
    if (!sameValue(field.value, value)) fields.push({ fieldId: field.fieldId, value })
  }
  if (fields.length > 0) patch.fields = fields

  return patch
}

/** The defaults once a remember write-back lands, for updating optimistically. */
export function withRemembered(defaults: ItemDefaults, patch: ItemDefaultsPatch): ItemDefaults {
  const next: ItemDefaults = { ...defaults }
  if (patch.recurrence && next.recurrence)
    next.recurrence = { ...next.recurrence, value: patch.recurrence.value }
  if (patch.stores) next.stores = { ...next.stores, value: patch.stores.value }
  if (patch.category) next.category = { ...next.category, value: patch.category.value }
  if (patch.labels) next.labels = { ...next.labels, value: patch.labels.value }
  if (patch.stockMode && next.stockMode)
    next.stockMode = { ...next.stockMode, value: patch.stockMode.value }
  if (patch.fields) {
    const updates = new Map(patch.fields.map((f) => [f.fieldId, f.value]))
    next.fields = next.fields.map((f): ItemDefaultField =>
      updates.has(f.fieldId) ? { ...f, value: updates.get(f.fieldId) } : f,
    )
  }
  return next
}

/**
 * The defaults an editor configured, without what "remember" keys have
 * learned. It changes when someone edits the defaults, not on every add.
 */
export function configSignature(defaults: ItemDefaults | null | undefined): string {
  if (!defaults) return ''
  const pinned = (entry: { mode: string; value?: unknown } | undefined) =>
    entry?.mode === 'fixed' ? entry : { mode: entry?.mode ?? 'none' }
  return JSON.stringify({
    recurrence: pinned(defaults.recurrence),
    stores: pinned(defaults.stores),
    category: pinned(defaults.category),
    labels: pinned(defaults.labels),
    quantity: pinned(defaults.quantity),
    stockMode: pinned(defaults.stockMode),
    target: pinned(defaults.target),
    reorderPoint: pinned(defaults.reorderPoint),
    shelfLifeDays: pinned(defaults.shelfLifeDays),
    fields: defaults.fields.map((f) => ({ fieldId: f.fieldId, ...pinned(f) })),
  })
}
