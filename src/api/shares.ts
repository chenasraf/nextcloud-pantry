import { ocs } from '@/axios'
import type { Share, ShareEntityType, ShareInput } from './types'

export async function listShares(
  houseId: number,
  entityType: ShareEntityType,
  entityId: number,
): Promise<Share[]> {
  const resp = await ocs.get<Share[]>(`/houses/${houseId}/shares/${entityType}/${entityId}`)
  return resp.data ?? []
}

export async function setShares(
  houseId: number,
  entityType: ShareEntityType,
  entityId: number,
  shares: ShareInput[],
): Promise<void> {
  await ocs.put(`/houses/${houseId}/shares/${entityType}/${entityId}`, { shares })
}
