import { describe, expect, it } from 'vitest'

import type { FieldDefinition, ItemDefaults } from '@/api/types'
import {
  configSignature,
  defaultValueFor,
  rememberPatch,
  startValues,
  toFieldValue,
  withRemembered,
} from './itemDefaults'

function field(overrides: Partial<FieldDefinition>): FieldDefinition {
  return {
    id: 1,
    houseId: 1,
    listId: null,
    name: 'Field',
    type: 'text',
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

function defaults(overrides: Partial<ItemDefaults> = {}): ItemDefaults {
  return {
    recurrence: { mode: 'none' },
    stores: { mode: 'none' },
    category: { mode: 'none' },
    labels: { mode: 'none' },
    quantity: { mode: 'none' },
    fields: [],
    ...overrides,
  }
}

const select = field({ id: 1, type: 'select', defaultOptionId: 9 })
const relative = field({ id: 2, type: 'date', dateMode: 'relative' })

describe('startValues', () => {
  it('starts empty without defaults', () => {
    expect(startValues(null, [], 3)).toMatchObject({
      categoryId: null,
      storeIds: [],
      quantity: '',
      recurrence: { kind: 'none', rrule: null },
    })
  })

  it('uses pinned and remembered values but ignores keys that are not set', () => {
    const start = startValues(
      defaults({
        stores: { mode: 'fixed', value: [3] },
        category: { mode: 'remember', value: 5 },
        labels: { mode: 'none', value: [7] },
      }),
      [],
      3,
    )
    expect(start.storeIds).toEqual([3])
    expect(start.categoryId).toBe(5)
    expect(start.labelIds).toEqual([])
  })

  it('lets a list default replace the field definition default', () => {
    const start = startValues(
      defaults({ fields: [{ fieldId: 1, mode: 'fixed', value: { valueOptionId: 4 } }] }),
      [select],
      3,
    )
    expect(start.customFieldValues.find((v) => v.fieldId === 1)?.valueOptionId).toBe(4)
  })

  it('keeps the field definition default while nothing is remembered yet', () => {
    const start = startValues(defaults({ fields: [{ fieldId: 1, mode: 'remember' }] }), [select], 3)
    expect(start.customFieldValues.find((v) => v.fieldId === 1)?.valueOptionId).toBe(9)
  })

  it('gives a recurring default without a rule the weekly rule', () => {
    const start = startValues(
      defaults({
        recurrence: {
          mode: 'fixed',
          value: { kind: 'recurring', rrule: null, repeatFromCompletion: false },
        },
      }),
      [],
      3,
    )
    expect(start.recurrence.rrule).toBe('FREQ=WEEKLY;INTERVAL=1')
  })
})

describe('rememberPatch', () => {
  const used = {
    categoryId: 5,
    storeIds: [3],
    labelIds: [],
    recurrence: { kind: 'none' as const, rrule: null, repeatFromCompletion: false },
    customFieldValues: [{ ...toFieldValue(2, { offsetDays: 4 }), notifyEnabled: true }],
  }

  it('writes only remembered keys whose value changed', () => {
    const patch = rememberPatch(
      defaults({
        stores: { mode: 'remember', value: [3] },
        category: { mode: 'remember', value: 1 },
        labels: { mode: 'fixed', value: [8] },
      }),
      used,
      [],
    )
    expect(patch).toEqual({ category: { value: 5 } })
  })

  it('remembers a relative date as its offset only', () => {
    const patch = rememberPatch(
      defaults({ fields: [{ fieldId: 2, mode: 'remember', value: { offsetDays: 1 } }] }),
      used,
      [relative],
    )
    expect(patch).toEqual({ fields: [{ fieldId: 2, value: { offsetDays: 4 } }] })
  })
})

describe('defaultValueFor', () => {
  it('keeps only the column the field type uses', () => {
    expect(
      defaultValueFor(select, { ...toFieldValue(1, { valueOptionId: 4 }), valueText: 'x' }),
    ).toEqual({
      valueOptionId: 4,
    })
  })
})

describe('withRemembered', () => {
  it('applies a write-back without touching modes', () => {
    const next = withRemembered(defaults({ stores: { mode: 'remember', value: [3] } }), {
      stores: { value: [4] },
    })
    expect(next.stores).toEqual({ mode: 'remember', value: [4] })
  })
})

describe('configSignature', () => {
  it('ignores what remembered keys learn', () => {
    const before = defaults({ stores: { mode: 'remember', value: [3] } })
    const after = defaults({ stores: { mode: 'remember', value: [4] } })
    expect(configSignature(before)).toBe(configSignature(after))
  })

  it('changes when a pinned value changes', () => {
    const before = defaults({ stores: { mode: 'fixed', value: [3] } })
    const after = defaults({ stores: { mode: 'fixed', value: [4] } })
    expect(configSignature(before)).not.toBe(configSignature(after))
  })
})
