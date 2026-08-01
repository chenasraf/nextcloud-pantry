import { ocs } from '@/axios'
import type { ChecklistItem, ShoppingSession } from './types'

export interface CreateSessionInput {
  /** Checklist ids in scope (at least one). */
  listIds: number[]
  /** Ordered store sequence (may be empty for no store narrowing). */
  storeIds: number[]
  /** Keep buy-anywhere items when narrowing by store. */
  includeUnassigned: boolean
}

/**
 * Result of a start attempt. `conflict` carries the caller's existing live
 * session (the one-live-per-user guard) so the UI can offer resume vs end.
 */
export type CreateSessionResult =
  { status: 'created'; session: ShoppingSession } | { status: 'conflict'; session: ShoppingSession }

/** The caller's live session across all houses, or null. */
export async function getCurrentSession(): Promise<ShoppingSession | null> {
  const resp = await ocs.get<ShoppingSession | null>('/shopping-sessions/current')
  const data = resp.data as unknown
  // A null `data` payload is a real answer ("no live session"), but the ocs
  // interceptor's `ocsData ?? response.data` fallback turns it back into the raw
  // { ocs: … } envelope. Accept only a genuine session object (one with a
  // numeric id); anything else — envelope, empty array, null — means no session.
  if (!data || typeof data !== 'object' || typeof (data as { id?: unknown }).id !== 'number') {
    return null
  }
  return data as ShoppingSession
}

export async function createSession(
  houseId: number,
  input: CreateSessionInput,
): Promise<CreateSessionResult> {
  const resp = await ocs.post<ShoppingSession>(`/houses/${houseId}/shopping-sessions`, input, {
    validateStatus: (s) => s === 200 || s === 409,
  })
  return { status: resp.status === 409 ? 'conflict' : 'created', session: resp.data }
}

export async function advanceStore(
  houseId: number,
  sessionId: number,
  storeId: number,
): Promise<ShoppingSession> {
  const resp = await ocs.post<ShoppingSession>(
    `/houses/${houseId}/shopping-sessions/${sessionId}/advance`,
    { storeId },
  )
  return resp.data
}

export async function closeSession(houseId: number, sessionId: number): Promise<ShoppingSession> {
  // A 409 (already closed) is treated as success — the trip is over either way.
  const resp = await ocs.post<ShoppingSession>(
    `/houses/${houseId}/shopping-sessions/${sessionId}/close`,
    {},
    { validateStatus: (s) => s === 200 || s === 409 },
  )
  return resp.data
}

export async function listSessionItems(
  houseId: number,
  sessionId: number,
): Promise<ChecklistItem[]> {
  const resp = await ocs.get<ChecklistItem[]>(
    `/houses/${houseId}/shopping-sessions/${sessionId}/items`,
  )
  return resp.data ?? []
}
