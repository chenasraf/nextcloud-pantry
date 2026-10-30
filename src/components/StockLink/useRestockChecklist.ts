import { computed, type ComputedRef } from 'vue'
import type { Checklist } from '@/api/types'
import { useChecklists } from '@/composables/useChecklist'

/** The checklist an inventory's low stock goes to, from the house's loaded lists. */
export function useRestockChecklist(
  houseId: number,
  inventoryId: () => number,
): ComputedRef<Checklist | null> {
  const inventories = useChecklists(houseId, 'inventory').lists
  const checklists = useChecklists(houseId, 'checklist').lists
  return computed(() => {
    const id = inventories.value.find((l) => l.id === inventoryId())?.restockChecklistId
    return id ? (checklists.value.find((c) => c.id === id) ?? null) : null
  })
}
