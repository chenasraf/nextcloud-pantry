import { ocs } from '@/axios'
import type { Capabilities, Role } from './types'

export async function listRoles(houseId: number): Promise<Role[]> {
  const resp = await ocs.get<Role[]>(`/houses/${houseId}/roles`)
  return resp.data ?? []
}

export async function createRole(
  houseId: number,
  name: string,
  caps: Partial<Capabilities> = {},
): Promise<Role> {
  const resp = await ocs.post<Role>(`/houses/${houseId}/roles`, { name, caps })
  return resp.data
}

export async function updateRole(
  houseId: number,
  roleId: number,
  patch: { name?: string; caps?: Partial<Capabilities> },
): Promise<Role> {
  const resp = await ocs.patch<Role>(`/houses/${houseId}/roles/${roleId}`, patch)
  return resp.data
}

export async function deleteRole(houseId: number, roleId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/roles/${roleId}`)
}

export async function setMemberRoles(
  houseId: number,
  memberId: number,
  roleIds: number[],
): Promise<void> {
  await ocs.put(`/houses/${houseId}/members/${memberId}/roles`, { roleIds })
}

export async function getListRoles(houseId: number, listId: number): Promise<number[]> {
  const resp = await ocs.get<number[]>(`/houses/${houseId}/lists/${listId}/roles`)
  return resp.data ?? []
}

export async function setListRoles(
  houseId: number,
  listId: number,
  roleIds: number[],
): Promise<void> {
  await ocs.put(`/houses/${houseId}/lists/${listId}/roles`, { roleIds })
}
