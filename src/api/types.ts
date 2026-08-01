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

/** One store in a shopping session's ordered sequence. */
export interface ShoppingSessionStore {
  storeId: number
  position: number
  billedTotal: number | null
  billedCurrency: string | null
}

/** A shopper's persisted trip. `live` mirrors `closedAt === null`. */
export interface ShoppingSession {
  id: number
  houseId: number
  userId: string
  activeStoreId: number | null
  lastSeenAt: number
  closedAt: number | null
  includeUnassigned: boolean
  isPrivate: boolean
  billedTotal: number | null
  billedCurrency: string | null
  live: boolean
  createdAt: number
  updatedAt: number
  listIds: number[]
  stores: ShoppingSessionStore[]
}

/** A per-currency total, range-aware (min === max for a point value). */
export interface ShoppingEstimateEntry {
  currency: string
  min: number
  max: number
}

/** Items checked at one store during a trip, with the store's totals. */
export interface ShoppingReviewStore {
  storeId: number | null
  items: ChecklistItem[]
  estimate: ShoppingEstimateEntry[]
  noPriceCount: number
  billedTotal: number | null
  billedCurrency: string | null
}

export interface ShoppingReview {
  stores: ShoppingReviewStore[]
  grandTotal: ShoppingEstimateEntry[]
  uncheckedCount: number
}

export interface ShoppingDoneToday {
  items: ChecklistItem[]
  estimate: ShoppingEstimateEntry[]
  noPriceCount: number
  count: number
}

/** A history row's grand total, collapsed to a single amount per currency. */
export interface ShoppingHistoryTotal {
  currency: string
  amount: number
}

/** One closed trip in the history list (ADR 0008). */
export interface ShoppingHistoryRow {
  id: number
  userId: string
  createdAt: number
  closedAt: number | null
  /** Store-sequence names in order; empty for a storeless trip. */
  stores: string[]
  itemCount: number
  grandTotal: ShoppingHistoryTotal[]
}

/** The moment a reminder surfaces during a trip (ADR 0002). */
export type ShoppingReminderMoment = 'on_start' | 'on_store_advance' | 'on_close'

/** A house-scoped, user-defined shopping reminder (ADR 0002). */
export interface ShoppingReminder {
  id: number
  houseId: number
  text: string
  showOn: ShoppingReminderMoment
  position: number
  enabled: boolean
  createdAt: number
  updatedAt: number
}

/** One present shopper in a house's derived shopping presence (ADR 0004). */
export interface ShoppingPresenceEntry {
  userId: string
  /** The store the shopper is attributed to; null = live but no store chosen. */
  activeStoreId: number | null
  lastSeenAt: number
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
