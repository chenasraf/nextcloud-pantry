import { describe, expect, it } from 'vitest'

import type { ItemDefaults } from '@/api/types'
import { draftFromDefaults, fromFieldValue, patchFromDraft, toFieldValue } from './draft'

function defaults(overrides: Partial<ItemDefaults> = {}): ItemDefaults {
  return {
    recurrence: {
      mode: 'remember',
      value: { kind: 'none', rrule: null, repeatFromCompletion: false },
    },
    stores: { mode: 'none' },
    category: { mode: 'none' },
    labels: { mode: 'none' },
    quantity: { mode: 'none' },
    fields: [],
    ...overrides,
  }
}

describe('item defaults draft', () => {
  it('seeds every key, starting empty keys from nothing', () => {
    const draft = draftFromDefaults(defaults({ stores: { mode: 'fixed', value: [2] } }))
    expect(draft.modes).toEqual({
      recurrence: 'remember',
      stores: 'fixed',
      category: 'none',
      labels: 'none',
      quantity: 'none',
    })
    expect(draft.storeIds).toEqual([2])
    expect(draft.categoryId).toBeNull()
  })

  it('seeds the editor from a remembered value', () => {
    const draft = draftFromDefaults(defaults({ category: { mode: 'remember', value: 12 } }))
    expect(draft.categoryId).toBe(12)
  })

  it('sends nothing when nothing changed', () => {
    const original = defaults({ stores: { mode: 'fixed', value: [2] } })
    expect(patchFromDraft(original, draftFromDefaults(original))).toEqual({})
  })

  it('sends a pinned value only when it changed', () => {
    const original = defaults({ stores: { mode: 'fixed', value: [2] } })
    const draft = draftFromDefaults(original)
    draft.storeIds = [2, 3]
    expect(patchFromDraft(original, draft)).toEqual({ stores: { mode: 'fixed', value: [2, 3] } })
  })

  it('never resends a remembered key, so what the list learned is kept', () => {
    const original = defaults({ category: { mode: 'remember', value: 12 } })
    const draft = draftFromDefaults(original)
    draft.categoryId = 99
    expect(patchFromDraft(original, draft)).toEqual({})
  })

  it('switches a key to remember without a value', () => {
    const original = defaults()
    const draft = draftFromDefaults(original)
    draft.modes.labels = 'remember'
    expect(patchFromDraft(original, draft)).toEqual({ labels: { mode: 'remember' } })
  })

  it('clears a key back to not set', () => {
    const original = defaults({ quantity: { mode: 'fixed', value: '1' } })
    const draft = draftFromDefaults(original)
    draft.modes.quantity = 'none'
    expect(patchFromDraft(original, draft)).toEqual({ quantity: { mode: 'none' } })
  })

  it('pins a recurrence', () => {
    const original = defaults()
    const draft = draftFromDefaults(original)
    draft.modes.recurrence = 'fixed'
    draft.recurrence = { kind: 'once', rrule: null, repeatFromCompletion: false }
    expect(patchFromDraft(original, draft)).toEqual({
      recurrence: {
        mode: 'fixed',
        value: { kind: 'once', rrule: null, repeatFromCompletion: false },
      },
    })
  })

  it('sends only the custom fields that changed, and returns a field to its own default', () => {
    const original = defaults({
      fields: [
        { fieldId: 7, mode: 'fixed', value: { valueText: 'a' } },
        { fieldId: 8, mode: 'remember', value: { valueOptionId: 4 } },
      ],
    })
    const draft = draftFromDefaults(original)
    draft.fields[7] = { mode: 'none', value: null }
    draft.fields[9] = { mode: 'fixed', value: { offsetDays: 3 } }
    expect(patchFromDraft(original, draft)).toEqual({
      fields: [
        { fieldId: 7, mode: 'none' },
        { fieldId: 9, mode: 'fixed', value: { offsetDays: 3 } },
      ],
    })
  })

  it('round-trips a field value through the editor shape, dropping per-item settings', () => {
    const value = toFieldValue(7, { valueOptionId: 4 })
    expect(value.valueOptionId).toBe(4)
    expect(fromFieldValue({ ...value, notifyEnabled: true, notifyLeadDays: 2 })).toEqual({
      valueOptionId: 4,
    })
  })
})
