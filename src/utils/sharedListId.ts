// The list every given item belongs to, or null when they span lists (or there
// are none). Bulk category/label pickers scope their options with it: the batch
// endpoints don't check scope, so an entry scoped to one list must never be
// offered for items on another.
export function sharedListId(items: readonly { listId: number }[]): number | null {
  if (items.length === 0) return null
  const first = items[0].listId
  return items.every((i) => i.listId === first) ? first : null
}
