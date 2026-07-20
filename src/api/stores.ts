import { ocs } from '@/axios'
import type { Store } from './types'

export async function listStores(houseId: number): Promise<Store[]> {
  const resp = await ocs.get<Store[]>(`/houses/${houseId}/stores`)
  return resp.data ?? []
}

export async function createStore(
  houseId: number,
  input: { name: string; icon: string; color: string },
): Promise<Store> {
  const resp = await ocs.post<Store>(`/houses/${houseId}/stores`, input)
  return resp.data
}

export async function updateStore(
  houseId: number,
  storeId: number,
  patch: { name?: string; icon?: string; color?: string },
): Promise<Store> {
  const resp = await ocs.patch<Store>(`/houses/${houseId}/stores/${storeId}`, patch)
  return resp.data
}

export async function deleteStore(houseId: number, storeId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/stores/${storeId}`)
}
