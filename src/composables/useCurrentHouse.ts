import { computed, watch, ref, type ComputedRef, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useHouses } from './useHouses'
import * as api from '@/api/houses'
import type { House } from '@/api/types'

export function useCurrentHouse(): {
  house: Ref<House | null>
  houseId: ComputedRef<number | null>
  loading: Ref<boolean>
  canEdit: ComputedRef<boolean>
  canAdmin: ComputedRef<boolean>
  isOwner: ComputedRef<boolean>
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

  return {
    house,
    houseId,
    loading,
    canEdit: computed(() => house.value !== null),
    canAdmin: computed(() => {
      const role = house.value?.role
      return role === 'owner' || role === 'admin'
    }),
    isOwner: computed(() => house.value?.role === 'owner'),
    refresh,
  }
}
