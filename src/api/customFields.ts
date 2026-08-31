import { ocs } from '@/axios'
import type { FieldDefinition } from './types'

/** An option in a create/update payload. A present `id` targets an existing row. */
export interface FieldOptionInput {
  id?: number
  label: string
  sortOrder?: number
}

export interface CreateFieldInput {
  name: string
  type: string
  listId?: number | null
  hint?: string | null
  multiline?: boolean
  defaultText?: string | null
  defaultNumber?: number | null
  defaultBool?: boolean
  dateMode?: string | null
  defaultOffsetDays?: number | null
  notifyDefault?: boolean
  leadDays?: number
  overridePolicy?: string | null
  stopWhenDone?: boolean
  options?: FieldOptionInput[]
}

export interface UpdateFieldPatch {
  name?: string
  listId?: number | null
  hint?: string | null
  multiline?: boolean
  defaultText?: string | null
  defaultNumber?: number | null
  defaultBool?: boolean
  defaultOptionId?: number | null
  dateMode?: string | null
  defaultOffsetDays?: number | null
  notifyDefault?: boolean
  leadDays?: number
  overridePolicy?: string | null
  stopWhenDone?: boolean
  options?: FieldOptionInput[]
}

export async function listFields(houseId: number): Promise<FieldDefinition[]> {
  const resp = await ocs.get<FieldDefinition[]>(`/houses/${houseId}/fields`)
  return resp.data ?? []
}

export async function createField(
  houseId: number,
  input: CreateFieldInput,
): Promise<FieldDefinition> {
  const resp = await ocs.post<FieldDefinition>(`/houses/${houseId}/fields`, input)
  return resp.data
}

export async function updateField(
  houseId: number,
  fieldId: number,
  patch: UpdateFieldPatch,
): Promise<FieldDefinition> {
  const resp = await ocs.patch<FieldDefinition>(`/houses/${houseId}/fields/${fieldId}`, patch)
  return resp.data
}

export async function deleteField(houseId: number, fieldId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/fields/${fieldId}`)
}

export async function reorderFields(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.patch(`/houses/${houseId}/fields/reorder`, { items })
}
