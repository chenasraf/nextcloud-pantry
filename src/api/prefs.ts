import { ocs } from '@/axios'

export async function getLastHouse(): Promise<number | null> {
  const resp = await ocs.get<{ houseId: number | null }>('/prefs/last-house')
  return resp.data?.houseId ?? null
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await ocs.put('/prefs/last-house', { houseId })
}

export async function getImageFolder(houseId: number): Promise<string> {
  const resp = await ocs.get<{ folder: string }>(`/houses/${houseId}/prefs/image-folder`)
  return resp.data?.folder ?? '/Pantry'
}

export async function setImageFolder(houseId: number, folder: string): Promise<string> {
  const resp = await ocs.put<{ folder: string }>(`/houses/${houseId}/prefs/image-folder`, {
    folder,
  })
  return resp.data?.folder ?? folder
}

export interface NotificationPrefs {
  notifyPhoto: boolean
  notifyNoteCreate: boolean
  notifyNoteEdit: boolean
}

export async function getNotificationPrefs(houseId: number): Promise<NotificationPrefs> {
  const resp = await ocs.get<NotificationPrefs>(`/houses/${houseId}/prefs/notifications`)
  return resp.data ?? { notifyPhoto: true, notifyNoteCreate: true, notifyNoteEdit: true }
}

export async function setNotificationPrefs(
  houseId: number,
  prefs: Partial<NotificationPrefs>,
): Promise<NotificationPrefs> {
  const resp = await ocs.put<NotificationPrefs>(`/houses/${houseId}/prefs/notifications`, prefs)
  return resp.data ?? { notifyPhoto: true, notifyNoteCreate: true, notifyNoteEdit: true }
}
