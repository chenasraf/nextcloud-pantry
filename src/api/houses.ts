import { ocs } from '@/axios'
import type { House, HouseMember, HouseRole } from './types'

export async function listHouses(): Promise<House[]> {
  const resp = await ocs.get<House[]>('/houses')
  return resp.data ?? []
}

export async function getHouse(houseId: number): Promise<House> {
  const resp = await ocs.get<House>(`/houses/${houseId}`)
  return resp.data
}

export async function createHouse(name: string, description?: string | null): Promise<House> {
  const resp = await ocs.post<House>('/houses', { name, description: description ?? null })
  return resp.data
}

export async function updateHouse(
  houseId: number,
  patch: { name?: string; description?: string | null },
): Promise<House> {
  const resp = await ocs.patch<House>(`/houses/${houseId}`, patch)
  return resp.data
}

export async function deleteHouse(houseId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}`)
}

export async function listMembers(houseId: number): Promise<HouseMember[]> {
  const resp = await ocs.get<HouseMember[]>(`/houses/${houseId}/members`)
  return resp.data ?? []
}

export async function addMember(
  houseId: number,
  userId: string,
  role: HouseRole = 'member',
): Promise<HouseMember> {
  const resp = await ocs.post<HouseMember>(`/houses/${houseId}/members`, { userId, role })
  return resp.data
}

export async function updateMemberRole(
  houseId: number,
  memberId: number,
  role: HouseRole,
): Promise<HouseMember> {
  const resp = await ocs.patch<HouseMember>(`/houses/${houseId}/members/${memberId}`, { role })
  return resp.data
}

export async function removeMember(houseId: number, memberId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/members/${memberId}`)
}

export async function leaveHouse(houseId: number): Promise<void> {
  await ocs.post(`/houses/${houseId}/leave`)
}

export interface UserAutocomplete {
  id: string
  label: string
}

export async function searchUsers(search: string, limit = 10): Promise<UserAutocomplete[]> {
  const resp = await ocs.get<UserAutocomplete[]>('/users/autocomplete', {
    params: { search, limit },
  })
  return resp.data ?? []
}
