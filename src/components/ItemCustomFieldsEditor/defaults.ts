import type { FieldDefinition, ItemCustomFieldValue } from '@/api/types'

function emptyValue(fieldId: number): ItemCustomFieldValue {
  return {
    fieldId,
    valueText: null,
    valueNumber: null,
    valueBool: false,
    valueDate: null,
    valueOptionId: null,
    offsetDays: null,
    notifyOverride: false,
    notifyEnabled: false,
    notifyLeadDays: null,
  }
}

/** Epoch seconds at local midnight, `offset` days from today. */
function anchorEpoch(offset: number): number {
  const day = new Date()
  day.setHours(0, 0, 0, 0)
  day.setDate(day.getDate() + offset)
  return Math.floor(day.getTime() / 1000)
}

/**
 * The initial custom-field values for a new item on the given list: each
 * applicable field that carries a default contributes a value. A relative date
 * field's default offset is materialized to today + offset; absolute date fields
 * have no default, and fields without one are left unset.
 */
export function defaultCustomFieldValues(
  fields: FieldDefinition[],
  listId: number | null,
): ItemCustomFieldValue[] {
  const out: ItemCustomFieldValue[] = []
  for (const field of fields) {
    if (field.listId != null && field.listId !== listId) continue
    const v = emptyValue(field.id)
    switch (field.type) {
      case 'text':
        if (!field.defaultText) continue
        v.valueText = field.defaultText
        break
      case 'number':
        if (field.defaultNumber == null) continue
        v.valueNumber = field.defaultNumber
        break
      case 'checkbox':
        if (!field.defaultBool) continue
        v.valueBool = true
        break
      case 'select':
        if (field.defaultOptionId == null) continue
        v.valueOptionId = field.defaultOptionId
        break
      case 'date':
        if (field.dateMode !== 'relative' || field.defaultOffsetDays == null) continue
        v.offsetDays = field.defaultOffsetDays
        v.valueDate = anchorEpoch(field.defaultOffsetDays)
        break
      default:
        continue
    }
    out.push(v)
  }
  return out
}
