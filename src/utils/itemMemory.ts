import type { ItemCategoryMemory, ItemQuantityMemory } from '@/api/itemMemory'

/**
 * The house's item memory as clients keep it: category rows per name key and
 * the remembered amount per list and name key.
 */
export interface MemoryIndex {
  categories: Map<string, ItemCategoryMemory[]>
  quantities: Map<string, string>
}

export function emptyMemoryIndex(): MemoryIndex {
  return { categories: new Map(), quantities: new Map() }
}

function quantitySlot(listId: number, key: string): string {
  return `${listId}\0${key}`
}

/**
 * Fold synced rows into the index. Rows replace the ones they match; an amount
 * synced as null is forgotten.
 */
export function mergeMemory(
  index: MemoryIndex,
  categories: ItemCategoryMemory[],
  quantities: ItemQuantityMemory[],
): MemoryIndex {
  const nextCategories = new Map(index.categories)
  for (const row of categories) {
    const rows = (nextCategories.get(row.nameKey) ?? []).filter(
      (r) => r.categoryId !== row.categoryId,
    )
    nextCategories.set(row.nameKey, [...rows, row])
  }
  const nextQuantities = new Map(index.quantities)
  for (const row of quantities) {
    const slot = quantitySlot(row.listId, row.nameKey)
    if (row.quantity === null) nextQuantities.delete(slot)
    else nextQuantities.set(slot, row.quantity)
  }
  return { categories: nextCategories, quantities: nextQuantities }
}

/**
 * The category to suggest for a name: among those usable on the target list,
 * the highest uses minus rejections above zero, the most recently changed on a
 * tie. The mobile app picks the same way.
 */
export function pickCategory(
  index: MemoryIndex,
  key: string,
  usableCategoryIds: ReadonlySet<number>,
): number | null {
  let best: ItemCategoryMemory | null = null
  for (const row of index.categories.get(key) ?? []) {
    if (!usableCategoryIds.has(row.categoryId)) continue
    const score = row.uses - row.rejections
    if (score <= 0) continue
    const bestScore = best ? best.uses - best.rejections : 0
    if (!best || score > bestScore || (score === bestScore && row.updatedAt > best.updatedAt)) {
      best = row
    }
  }
  return best?.categoryId ?? null
}

export function pickQuantity(index: MemoryIndex, listId: number, key: string): string | null {
  return index.quantities.get(quantitySlot(listId, key)) ?? null
}
