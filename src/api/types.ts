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
  /** Effective edit permission for the current user (role capability or an editor share). */
  canEdit?: boolean
  /**
   * The current user reaches this list only through a per-user share (no role
   * grants access). When true, a viewer share bounds all writes to read-only.
   */
  sharedOnly?: boolean
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

/** A single opening-hours interval. `day` is 1-7 with 1 = Monday, 7 = Sunday (ISO-8601). */
export interface OpeningHoursInterval {
  day: number
  start: string
  end: string
}

export interface Store {
  id: number
  houseId: number
  name: string
  icon: string
  color: string
  brand: string | null
  location: string | null
  openingHours: OpeningHoursInterval[] | null
  contact: string | null
  responsible: string | null
  notes: string | null
  createdAt: number
  updatedAt: number
}

export interface ChecklistItem {
  id: number
  listId: number
  name: string
  description: string | null
  categoryId: number | null
  storeIds: number[]
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
  barcode: string | null
  priceType: 'set' | 'range' | null
  priceMin: number | null
  priceMax: number | null
  priceCurrency: string | null
  sortOrder: number
  createdAt: number
  updatedAt: number
  deletedAt: number | null
  archivedAt: number | null
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
  /** Effective edit permission for the current user (role capability or an editor share). */
  canEdit?: boolean
}

export interface PhotoFolder {
  id: number
  houseId: number
  name: string
  sortOrder: number
  createdAt: number
  updatedAt: number
  /** Effective edit permission for the current user (move capability or an editor share). */
  canEdit?: boolean
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
  /** Effective edit permission for the current user (role capability or an editor share on the photo or its folder). */
  canEdit?: boolean
}

/** Polymorphic per-user share target types (Share::TYPE_* on the backend). */
export type ShareEntityType = 'checklist' | 'note' | 'photo' | 'photos-folder'

export type SharePermission = 'view' | 'edit'

export interface Share {
  id: number
  houseId: number
  entityType: ShareEntityType
  entityId: number
  sharedWithUid: string
  permission: SharePermission
  createdBy: string
  createdAt: number
  updatedAt: number
}

export interface ShareInput {
  uid: string
  permission: SharePermission
}
