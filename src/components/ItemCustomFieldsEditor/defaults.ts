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

/**
 * The initial custom-field values for a new item on the given list: each
 * applicable field that carries a default contributes a value. Fields without a
 * default (and all date fields) are left unset.
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
      default:
        continue
    }
    out.push(v)
  }
  return out
}
