import type { CreateFieldInput, UpdateFieldPatch } from '@/api/customFields'
import type { FieldDateMode, FieldDefinition, FieldOverridePolicy, FieldType } from '@/api/types'

/** A select option while editing; a null id is a not-yet-persisted option. */
export interface DraftOption {
  id: number | null
  label: string
  /** Stored values referencing this option; a persisted option in use needs
   * remap-or-clear before it can be removed. */
  valueCount: number
}

/** The editable state of one field definition, all controls as plain values. */
export interface FieldDraft {
  name: string
  type: FieldType
  listId: number | null
  hint: string
  multiline: boolean
  defaultText: string
  defaultNumber: string
  defaultBool: boolean
  defaultOptionId: number | null
  dateMode: FieldDateMode
  defaultOffsetDays: string
  notifyDefault: boolean
  leadDays: number
  overridePolicy: FieldOverridePolicy
  stopWhenDone: boolean
  options: DraftOption[]
}

export function blankDraft(listId: number | null = null): FieldDraft {
  return {
    name: '',
    type: 'text',
    listId,
    hint: '',
    multiline: false,
    defaultText: '',
    defaultNumber: '',
    defaultBool: false,
    defaultOptionId: null,
    dateMode: 'absolute',
    defaultOffsetDays: '',
    notifyDefault: false,
    leadDays: 0,
    overridePolicy: 'field-only',
    stopWhenDone: false,
    options: [],
  }
}

export function draftFromField(f: FieldDefinition): FieldDraft {
  return {
    name: f.name,
    type: f.type,
    listId: f.listId,
    hint: f.hint ?? '',
    multiline: f.multiline,
    defaultText: f.defaultText ?? '',
    defaultNumber: f.defaultNumber != null ? String(f.defaultNumber) : '',
    defaultBool: f.defaultBool,
    defaultOptionId: f.defaultOptionId,
    dateMode: f.dateMode ?? 'absolute',
    defaultOffsetDays: f.defaultOffsetDays != null ? String(f.defaultOffsetDays) : '',
    notifyDefault: f.notifyDefault,
    leadDays: f.leadDays,
    overridePolicy: f.overridePolicy ?? 'field-only',
    stopWhenDone: f.stopWhenDone,
    options: f.options.map((o) => ({ id: o.id, label: o.label, valueCount: o.valueCount })),
  }
}

function numOrNull(s: string): number | null {
  const trimmed = s.trim()
  if (trimmed === '') return null
  const n = Number(trimmed)
  return Number.isFinite(n) ? n : null
}

function cleanOptions(options: DraftOption[]): { id?: number; label: string; sortOrder: number }[] {
  return options
    .map((o) => ({ id: o.id, label: o.label.trim() }))
    .filter((o) => o.label !== '')
    .map((o, i) =>
      o.id != null ? { id: o.id, label: o.label, sortOrder: i } : { label: o.label, sortOrder: i },
    )
}

/** Only the config keys relevant to the field's type; the server ignores the rest. */
function typeConfig(d: FieldDraft): Partial<CreateFieldInput> {
  const hint = d.hint.trim() === '' ? null : d.hint.trim()
  switch (d.type) {
    case 'text':
      return { hint, multiline: d.multiline, defaultText: d.defaultText.trim() || null }
    case 'number':
      return { hint, defaultNumber: numOrNull(d.defaultNumber) }
    case 'checkbox':
      return { defaultBool: d.defaultBool }
    case 'select':
      return { hint, options: cleanOptions(d.options) }
    case 'date':
      return {
        notifyDefault: d.notifyDefault,
        leadDays: d.leadDays,
        overridePolicy: d.overridePolicy,
        stopWhenDone: d.stopWhenDone,
        dateMode: d.dateMode,
        defaultOffsetDays: d.dateMode === 'relative' ? numOrNull(d.defaultOffsetDays) : null,
      }
    default:
      return { hint }
  }
}

export function draftToCreate(d: FieldDraft): CreateFieldInput {
  return {
    name: d.name.trim(),
    type: d.type,
    listId: d.listId,
    ...typeConfig(d),
  }
}

export function draftToPatch(d: FieldDraft): UpdateFieldPatch {
  const patch: UpdateFieldPatch = {
    name: d.name.trim(),
    listId: d.listId,
    ...typeConfig(d),
  }
  // The default option references an existing option by id, so it is only
  // meaningful on update (create's options have no ids yet).
  if (d.type === 'select') {
    patch.defaultOptionId = d.defaultOptionId
  }
  return patch
}
