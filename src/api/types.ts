export interface House {
  id: number
  name: string
  description: string | null
  ownerUid: string
  createdAt: number
  updatedAt: number
  role: HouseRole
}

export type HouseRole = 'owner' | 'admin' | 'member'

export interface HouseMember {
  id: number
  houseId: number
  userId: string
  displayName: string
  role: HouseRole
  joinedAt: number
}

export interface ShoppingList {
  id: number
  houseId: number
  name: string
  description: string | null
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface ShoppingListItem {
  id: number
  listId: number
  name: string
  category: string | null
  quantity: string | null
  bought: boolean
  boughtAt: number | null
  boughtBy: string | null
  rrule: string | null
  nextDueAt: number | null
  sortOrder: number
  createdAt: number
  updatedAt: number
}
