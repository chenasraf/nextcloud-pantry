import { ocs } from '@/axios'
import type { ShoppingReminder, ShoppingReminderMoment } from './types'

export interface ReminderInput {
  text: string
  showOn: ShoppingReminderMoment
  enabled?: boolean
}

export async function listReminders(houseId: number): Promise<ShoppingReminder[]> {
  const resp = await ocs.get<ShoppingReminder[]>(`/houses/${houseId}/shopping/reminders`)
  return resp.data ?? []
}

export async function createReminder(
  houseId: number,
  input: ReminderInput,
): Promise<ShoppingReminder> {
  const resp = await ocs.post<ShoppingReminder>(`/houses/${houseId}/shopping/reminders`, input)
  return resp.data
}

export async function updateReminder(
  houseId: number,
  reminderId: number,
  patch: Partial<ReminderInput>,
): Promise<ShoppingReminder> {
  const resp = await ocs.patch<ShoppingReminder>(
    `/houses/${houseId}/shopping/reminders/${reminderId}`,
    patch,
  )
  return resp.data
}

export async function deleteReminder(houseId: number, reminderId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/shopping/reminders/${reminderId}`)
}

/** Batch reorder: the ordered ids become positions 0..n-1. Returns the new order. */
export async function reorderReminders(
  houseId: number,
  reminderIds: number[],
): Promise<ShoppingReminder[]> {
  const resp = await ocs.patch<ShoppingReminder[]>(
    `/houses/${houseId}/shopping/reminders/reorder`,
    { reminderIds },
  )
  return resp.data ?? []
}
