import { describe, expect, it } from 'vitest'
import type { FieldDefinition, FieldType } from '@/api/types'
import { defaultCustomFieldValues } from './defaults'

function field(
  overrides: Partial<FieldDefinition> & { id: number; type: FieldType },
): FieldDefinition {
  return {
    houseId: 1,
    listId: null,
    name: 'F',
    sortOrder: 0,
    hint: null,
    multiline: false,
    defaultText: null,
    defaultNumber: null,
    defaultBool: false,
    defaultOptionId: null,
    dateMode: null,
    defaultOffsetDays: null,
    notifyDefault: false,
    leadDays: 0,
    overridePolicy: null,
    stopWhenDone: false,
    options: [],
    createdAt: 0,
    updatedAt: 0,
    ...overrides,
  }
}

describe('defaultCustomFieldValues', () => {
  it('emits a value only for fields that carry a default', () => {
    const fields = [
      field({ id: 1, type: 'text', defaultText: 'hi' }),
      field({ id: 2, type: 'text' }), // no default → skipped
      field({ id: 3, type: 'number', defaultNumber: 5 }),
      field({ id: 4, type: 'checkbox', defaultBool: true }),
      field({ id: 5, type: 'checkbox' }), // false default → skipped
      field({ id: 6, type: 'select', defaultOptionId: 42 }),
      field({ id: 7, type: 'date' }), // dates never default
    ]

    const out = defaultCustomFieldValues(fields, null)

    expect(out.map((v) => v.fieldId)).toEqual([1, 3, 4, 6])
    expect(out.find((v) => v.fieldId === 1)!.valueText).toBe('hi')
    expect(out.find((v) => v.fieldId === 3)!.valueNumber).toBe(5)
    expect(out.find((v) => v.fieldId === 4)!.valueBool).toBe(true)
    expect(out.find((v) => v.fieldId === 6)!.valueOptionId).toBe(42)
  })

  it('excludes fields scoped to another list', () => {
    const fields = [
      field({ id: 1, type: 'text', defaultText: 'a' }), // house-wide
      field({ id: 2, type: 'text', defaultText: 'b', listId: 8 }), // other list
      field({ id: 3, type: 'text', defaultText: 'c', listId: 7 }), // this list
    ]

    const out = defaultCustomFieldValues(fields, 7)

    expect(out.map((v) => v.fieldId)).toEqual([1, 3])
  })
})
