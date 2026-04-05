import { ocs } from '@/axios'

export async function getLastHouse(): Promise<number | null> {
  const resp = await ocs.get<{ houseId: number | null }>('/prefs/last-house')
  return resp.data?.houseId ?? null
}

export async function setLastHouse(houseId: number | null): Promise<void> {
  await ocs.put('/prefs/last-house', { houseId })
}
