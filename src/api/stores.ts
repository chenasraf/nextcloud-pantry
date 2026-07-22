import { ocs } from '@/axios'
import type { OpeningHoursInterval, Store } from './types'

export interface StoreInput {
  name: string
  icon: string
  color: string
  location?: string | null
  openingHours?: OpeningHoursInterval[] | null
  contact?: string | null
  responsible?: string | null
  notes?: string | null
}

export async function listStores(houseId: number): Promise<Store[]> {
  const resp = await ocs.get<Store[]>(`/houses/${houseId}/stores`)
  return resp.data ?? []
}

export async function createStore(houseId: number, input: StoreInput): Promise<Store> {
  const resp = await ocs.post<Store>(`/houses/${houseId}/stores`, input)
  return resp.data
}

export async function updateStore(
  houseId: number,
  storeId: number,
  patch: Partial<StoreInput>,
): Promise<Store> {
  const resp = await ocs.patch<Store>(`/houses/${houseId}/stores/${storeId}`, patch)
  return resp.data
}

export async function deleteStore(houseId: number, storeId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/stores/${storeId}`)
}
