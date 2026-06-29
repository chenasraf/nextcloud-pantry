import { computed, watch, ref, type ComputedRef, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useHouses } from './useHouses'
import * as api from '@/api/houses'
import type { Capabilities, CapabilityKey, House } from '@/api/types'

const NO_CAPS: Capabilities = {
  canViewLists: false,
  canCreateLists: false,
  canEditLists: false,
  canDeleteLists: false,
  canAddItems: false,
  canDeleteItems: false,
  canCopyItems: false,
  canMoveItems: false,
  canCheckItems: false,
  canViewPhotos: false,
  canUploadPhotos: false,
  canUpdatePhotos: false,
  canDeletePhotos: false,
  canMovePhotos: false,
  canViewNotes: false,
  canCreateNotes: false,
  canUpdateNotes: false,
  canDeleteNotes: false,
}

export function useCurrentHouse(): {
  house: Ref<House | null>
  houseId: ComputedRef<number | null>
  loading: Ref<boolean>
  canEdit: ComputedRef<boolean>
  canAdmin: ComputedRef<boolean>
  isAdmin: ComputedRef<boolean>
  isOwner: ComputedRef<boolean>
  /** The current user's effective capabilities in this house. */
  can: ComputedRef<Capabilities>
  refresh: () => Promise<void>
} {
  const route = useRoute()
  const { findById, load } = useHouses()
  const house = ref<House | null>(null)
  const loading = ref(false)

  const houseId = computed<number | null>(() => {
    const raw = route.params.houseId
    if (!raw) return null
    const id = Number(Array.isArray(raw) ? raw[0] : raw)
    return Number.isFinite(id) ? id : null
  })

  async function refresh(): Promise<void> {
    const id = houseId.value
    if (id === null) {
      house.value = null
      return
    }
    loading.value = true
    try {
      // Fast path: use cached list if present.
      await load()
      const cached = findById(id)
      if (cached) {
        house.value = cached
      } else {
        house.value = await api.getHouse(id)
      }
    } finally {
      loading.value = false
    }
  }

  watch(houseId, refresh, { immediate: true })

  const can = computed<Capabilities>(() => house.value?.permissions ?? NO_CAPS)
  const isAdmin = computed(() => house.value?.isAdmin === true)

  return {
    house,
    houseId,
    loading,
    canEdit: computed(() => house.value !== null),
    // Admin status now comes from the resolved role permissions, falling back to
    // the legacy role string for older payloads.
    canAdmin: computed(
      () => isAdmin.value || house.value?.role === 'owner' || house.value?.role === 'admin',
    ),
    isAdmin,
    isOwner: computed(() => house.value?.role === 'owner'),
    can,
    refresh,
  }
}

export { NO_CAPS }
export type { CapabilityKey }
