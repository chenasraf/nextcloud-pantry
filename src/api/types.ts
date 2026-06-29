export interface House {
  id: number
  name: string
  description: string | null
  ownerUid: string
  createdAt: number
  updatedAt: number
  trashRetentionDays: number
  role: HouseRole
  /** Whether the current user holds an admin role in this house. */
  isAdmin: boolean
  /** The current user's effective capabilities in this house. */
  permissions: Capabilities
}

export type HouseRole = 'owner' | 'admin' | 'member'

export type RoleType = 'admin' | 'default' | 'normal'

/** Capability keys shared with the backend (Role::CAPABILITIES). */
export type CapabilityKey =
  | 'canViewLists'
  | 'canCreateLists'
  | 'canEditLists'
  | 'canDeleteLists'
  | 'canAddItems'
  | 'canDeleteItems'
  | 'canCopyItems'
  | 'canMoveItems'
  | 'canCheckItems'
  | 'canViewPhotos'
  | 'canUploadPhotos'
  | 'canUpdatePhotos'
  | 'canDeletePhotos'
  | 'canMovePhotos'
  | 'canViewNotes'
  | 'canCreateNotes'
  | 'canUpdateNotes'
  | 'canDeleteNotes'

export type Capabilities = Record<CapabilityKey, boolean>

export interface Role extends Capabilities {
  id: number
  houseId: number
  name: string
  roleType: RoleType
}

export interface HouseMember {
  id: number
  houseId: number
  userId: string
  displayName: string
  role: HouseRole
  roleIds: number[]
  joinedAt: number
}

export interface Checklist {
  id: number
  houseId: number
  name: string
  description: string | null
  icon: string
  color: string | null
  sortOrder: number
  deleteOnDoneDefault: boolean
  createdAt: number
  updatedAt: number
  deletedAt: number | null
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
  description: string | null
  categoryId: number | null
  quantity: string | null
  done: boolean
  doneAt: number | null
  doneBy: string | null
  rrule: string | null
  repeatFromCompletion: boolean
  deleteOnDone: boolean
  nextDueAt: number | null
  imageFileId: number | null
  imageUploadedBy: string | null
  addedBy: string | null
  sortOrder: number
  createdAt: number
  updatedAt: number
  deletedAt: number | null
}

export interface Note {
  id: number
  houseId: number
  title: string
  content: string | null
  color: string | null
  createdBy: string
  sortOrder: number
  isPinned: boolean
  createdAt: number
  updatedAt: number
  deletedAt: number | null
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
  deletedAt: number | null
}
