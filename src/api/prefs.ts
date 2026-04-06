import { ocs } from '@/axios'

export async function getLastHouse(): Promise<number | null> {
  const resp = await ocs.get<{ houseId: number | null }>('/prefs/last-house')
  return resp.data?.houseId ?? null
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await ocs.put('/prefs/last-house', { houseId })
}

export async function getImageFolder(): Promise<string> {
  const resp = await ocs.get<{ folder: string }>('/prefs/image-folder')
  return resp.data?.folder ?? '/Pantry'
}

export async function setImageFolder(folder: string): Promise<string> {
  const resp = await ocs.put<{ folder: string }>('/prefs/image-folder', { folder })
  return resp.data?.folder ?? folder
}
