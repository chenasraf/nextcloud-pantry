// The current Nextcloud user, read from the global `OC` object the server
// injects on every page. Avoids pulling in @nextcloud/auth for a single field.

interface NcCurrentUser {
  uid: string
  displayName: string
}

declare global {
  interface Window {
    OC?: {
      getCurrentUser?: () => NcCurrentUser | null
      currentUser?: string | null
    }
  }
}

/** The current user's id, or null when not logged in (e.g. in tests). */
export function getCurrentUserId(): string | null {
  const oc = window.OC
  if (!oc) return null
  return oc.getCurrentUser?.()?.uid ?? oc.currentUser ?? null
}
