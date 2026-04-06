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

export interface Checklist {
  id: number
  houseId: number
  name: string
  description: string | null
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface Category {
  id: number
  houseId: number
  name: string
  icon: string
  color: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface ChecklistItem {
  id: number
  listId: number
  name: string
  categoryId: number | null
  quantity: string | null
  bought: boolean
  boughtAt: number | null
  boughtBy: string | null
  rrule: string | null
  repeatFromCompletion: boolean
  nextDueAt: number | null
  imageFileId: number | null
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface Note {
  id: number
  houseId: number
  title: string
  content: string | null
  color: string | null
  createdBy: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface PhotoFolder {
  id: number
  houseId: number
  name: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface Photo {
  id: number
  houseId: number
  folderId: number | null
  fileId: number
  caption: string | null
  uploadedBy: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}
