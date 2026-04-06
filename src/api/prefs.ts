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
