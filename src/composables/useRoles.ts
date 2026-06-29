import { ref, type Ref } from 'vue'
import * as api from '@/api/roles'
import type { Capabilities, Role } from '@/api/types'

// Per-house cache. Roles change rarely, so one fetch is shared across consumers.
const cache = new Map<number, Ref<Role[]>>()
const inflight = new Map<number, Promise<void>>()

function ensureRef(houseId: number): Ref<Role[]> {
  let r = cache.get(houseId)
  if (!r) {
    r = ref<Role[]>([])
    cache.set(houseId, r)
  }
  return r
}

async function load(houseId: number, force = false): Promise<void> {
  if (!force && inflight.has(houseId)) return inflight.get(houseId)
  const r = ensureRef(houseId)
  const p = (async () => {
    try {
      r.value = await api.listRoles(houseId)
    } finally {
      inflight.delete(houseId)
    }
  })()
  inflight.set(houseId, p)
  return p
}

export function useRoles(houseId: number) {
  const roles = ensureRef(houseId)
  void load(houseId)

  async function refresh(): Promise<void> {
    await load(houseId, true)
  }

  async function create(name: string, caps: Partial<Capabilities> = {}): Promise<Role> {
    const role = await api.createRole(houseId, name, caps)
    await refresh()
    return role
  }

  async function update(
    roleId: number,
    patch: { name?: string; caps?: Partial<Capabilities> },
  ): Promise<Role> {
    const role = await api.updateRole(houseId, roleId, patch)
    await refresh()
    return role
  }

  async function remove(roleId: number): Promise<void> {
    await api.deleteRole(houseId, roleId)
    await refresh()
  }

  return { roles, refresh, create, update, remove }
}
