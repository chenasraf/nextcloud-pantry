export interface House {
  id: number
  name: string
  description: string | null
  ownerUid: string
  createdAt: number
  updatedAt: number
  trashRetentionDays: number
  /** Time of day (minutes since midnight, server timezone) at which recurring items reopen. */
  recurrenceTime: number
  /** Time of day (minutes since midnight, server timezone) at which date custom-field reminders are sent. */
  fieldReminderTime: number
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
  | 'canEditFields'

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

/** What an editor pins as a list's default recurrence, or the policy of following the last item added. */
export type RecurrenceMode = 'remember' | RecurrenceKind

/** The recurrence a new item starts with. */
export type RecurrenceKind = 'none' | 'once' | 'recurring'

/**
 * How a list pre-fills one field of a new item: leave it empty (`none`), always
 * use `value` (`fixed`), or reuse what the last item added used (`remember`).
 */
export type ItemDefaultMode = 'none' | 'fixed' | 'remember'

export interface ItemDefaultEntry<T> {
  mode: ItemDefaultMode
  value?: T | null
}

export interface RecurrenceDefaultValue {
  kind: RecurrenceKind
  rrule: string | null
  repeatFromCompletion: boolean
}

/** The value columns a custom-field default carries; only the field type's own column is set. */
export type ItemDefaultFieldValue = Partial<
  Pick<
    ItemCustomFieldValue,
    'valueText' | 'valueNumber' | 'valueBool' | 'valueDate' | 'valueOptionId' | 'offsetDays'
  >
>

/** A custom field's default on a list. A field without one inherits its own default. */
export interface ItemDefaultField {
  fieldId: number
  mode: ItemDefaultMode
  value?: ItemDefaultFieldValue | null
}

export interface ItemDefaults {
  /** Missing on inventories, whose items have no recurrence. */
  recurrence?: ItemDefaultEntry<RecurrenceDefaultValue>
  stores: ItemDefaultEntry<number[]>
  category: ItemDefaultEntry<number>
  labels: ItemDefaultEntry<number[]>
  quantity: ItemDefaultEntry<string>
  /** Inventories only: the stock mode new items start in. */
  stockMode?: ItemDefaultEntry<StockMode>
  /** Inventories only: the target new count items start with. Never remembered. */
  target?: ItemDefaultEntry<string>
  /** Inventories only: the reorder point new count items start with. Never remembered. */
  reorderPoint?: ItemDefaultEntry<string>
  /** Inventories only: the shelf life new items start with, in days. Never remembered. */
  shelfLifeDays?: ItemDefaultEntry<number>
  fields: ItemDefaultField[]
}

/** Keys of {@link ItemDefaults} holding a single entry. */
export type ItemDefaultKey = Exclude<keyof ItemDefaults, 'fields'>

export type ItemDefaultsPatch = Partial<{
  [K in ItemDefaultKey]: Partial<ItemDefaults[K]>
}> & {
  fields?: (Partial<ItemDefaultField> & { fieldId: number })[]
  /** Whether a template item drawn into the list fills what it leaves empty from these defaults. */
  fillFromDefaults?: boolean
}

/** What a list is for; fixed at creation. */
export type ListKind = 'checklist' | 'template' | 'inventory'

/** The templates a checklist or inventory draws suggestions from. */
export interface AttachedTemplates {
  /** Templates attached to this list by name. */
  templateIds: number[]
  /** Templates attached to every list, this one included. */
  everyListTemplateIds: number[]
}

/** The lists a template feeds suggestions to. */
export interface TemplateLists {
  /** Attached to every checklist and inventory in the house, including later ones. */
  everyList: boolean
  listIds: number[]
}

/** A template item offered in an add box, once per name. */
export interface TemplateSuggestion {
  item: ChecklistItem
  /** Every attached template the name is in. */
  templates: { id: number; name: string }[]
}

export interface ListSuggestions {
  /** The list's archived items; empty unless asked for. */
  archived: ChecklistItem[]
  templates: TemplateSuggestion[]
}

/**
 * A list of any kind. Templates and inventories carry the same fields minus the
 * checklist-only ones (completion and the per-column recurrence default), which
 * read as undefined on them.
 */
export interface Checklist {
  id: number
  houseId: number
  kind?: ListKind
  name: string
  description: string | null
  icon: string
  color: string | null
  sortOrder: number
  /** Values new items on this list start with. Present when the server has `list-item-defaults`. */
  itemDefaults?: ItemDefaults
  /**
   * Whether a template item drawn into the list fills the fields it leaves empty
   * from the item defaults. Missing on templates.
   */
  fillFromDefaults?: boolean
  /** True when new items default to one-time. Derived from the recurrence default. */
  deleteOnDoneDefault: boolean
  defaultRecurrenceMode: RecurrenceMode
  /** The recurrence new items start with; tracks the last item added while the mode is 'remember'. */
  defaultRecurrenceKind: RecurrenceKind
  defaultRrule: string | null
  defaultRepeatFromCompletion: boolean
  createdAt: number
  updatedAt: number
  deletedAt: number | null
  archivedAt: number | null
  /** When the list last had no open items left, stamped by the check that closed the final one. */
  lastCompletedAt: number | null
  /** Effective edit permission for the current user (role capability or an editor share). */
  canEdit?: boolean
  /**
   * The current user reaches this list only through a per-user share (no role
   * grants access). When true, a viewer share bounds all writes to read-only.
   */
  sharedOnly?: boolean
  /** Inventories only: how many items are low, out, expiring or expired. */
  needsRestock?: number
  /** Inventories only: how many days before its expiry date an item is expiring. */
  expiryLeadDays?: number
  /**
   * Inventories only: the checklist low stock goes to. Null when unset, or
   * when that checklist is archived, trashed or deleted.
   */
  restockChecklistId?: number | null
}

export interface Category {
  id: number
  houseId: number
  listId: number | null
  name: string
  icon: string
  color: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}

export interface Label {
  id: number
  houseId: number
  listId: number | null
  name: string
  icon: string
  color: string
  sortOrder: number
  createdAt: number
  updatedAt: number
}

/** The five custom-field types. */
export type FieldType = 'text' | 'number' | 'checkbox' | 'date' | 'select'

/** Entry mode for a `date` field: a picked calendar date, or an offset materialized at entry. */
export type FieldDateMode = 'absolute' | 'relative'

/** Per-item reminder policy for a `date` field. */
export type FieldOverridePolicy = 'field-only' | 'item-override'

/** One choice of a `select` field. Stored values reference an option by id. */
export interface FieldOption {
  id: number
  label: string
  sortOrder: number
  /** How many stored item values currently reference this option. */
  valueCount: number
}

/**
 * A custom-field definition, scoped to a house and optionally to a single list
 * (`listId === null` = house-wide). Type-specific config lives in the matching
 * columns; `options` is populated for `select` fields.
 */
export interface FieldDefinition {
  id: number
  houseId: number
  listId: number | null
  name: string
  type: FieldType
  sortOrder: number
  hint: string | null
  multiline: boolean
  defaultText: string | null
  defaultNumber: number | null
  defaultBool: boolean
  defaultOptionId: number | null
  dateMode: FieldDateMode | null
  defaultOffsetDays: number | null
  notifyDefault: boolean
  leadDays: number
  overridePolicy: FieldOverridePolicy | null
  stopWhenDone: boolean
  options: FieldOption[]
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
  sortOrder: number
  createdAt: number
  updatedAt: number
}

/**
 * The categories a store is arranged by, in the order its aisles are walked.
 * Lists only what the store arranges; every other category trails them in the
 * house-wide order.
 */
export interface StoreCategoryOrder {
  storeId: number
  categoryIds: number[]
}

/**
 * A price for an item, optionally attached to a store. A null store id is the
 * item's store-less (default) price; there is at most one per item and at most
 * one price per (item, store).
 */
export interface ItemPrice {
  storeId: number | null
  priceType: 'set' | 'range' | null
  priceMin: number | null
  priceMax: number | null
  priceCurrency: string | null
}

/**
 * A custom-field value on an item. The typed value lives in the field matching
 * the definition's type; `valueDate` is epoch seconds at local midnight. The
 * `notify*` fields carry a date field's per-item reminder override.
 */
export interface ItemCustomFieldValue {
  fieldId: number
  valueText: string | null
  valueNumber: number | null
  valueBool: boolean
  valueDate: number | null
  valueOptionId: number | null
  offsetDays: number | null
  notifyOverride: boolean
  notifyEnabled: boolean
  notifyLeadDays: number | null
}

/**
 * An item on a list of any kind. Template and inventory items are never checked
 * off: the server leaves out `done`, `doneAt`, `doneBy` and `nextDueAt`.
 * Inventory items have no recurrence either, so `rrule`, `repeatFromCompletion`
 * and `deleteOnDone` are left out on them too. Inventory items carry every
 * stock field; a template item carries only `stockMode` (null leaves it to the
 * inventory it is drawn into), `target`, `reorderPoint` and `shelfLifeDays`.
 */
export interface ChecklistItem extends Omit<Partial<ItemStock>, 'stockMode'> {
  stockMode?: StockMode | null
  id: number
  listId: number
  name: string
  description: string | null
  categoryId: number | null
  labelIds: number[]
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
  prices: ItemPrice[]
  customFields: ItemCustomFieldValue[]
  sortOrder: number
  createdAt: number
  updatedAt: number
  deletedAt: number | null
  archivedAt: number | null
  /** Checklist items only: the inventory items this row restocks, earliest linked first. */
  restocks?: StockLinkSource[]
  /** Checklist items only: a member set the quantity, so stock leaves it until the row is next done. */
  quantityPinned?: boolean
  /** In the response of a write only: what restocking did to checklist items because of it. */
  restockSync?: RestockSyncEvent[]
  /** Checked checklist items only: what the member's tick put back into stock, until it is unchecked. */
  restock?: RestockEntry[] | null
  /** In the response of a tick only: what it put back into stock. */
  restocked?: RestockEntry[]
  /** In the response of a member's uncheck only: what it took back out of stock. */
  restockUndone?: RestockEntry[]
}

export type StockMode = 'count' | 'level'
export type StockLevel = 'full' | 'low' | 'out'
export type StockStatus = 'ok' | 'low' | 'out' | 'uncounted'
export type ExpiryStatus = 'none' | 'ok' | 'expiring' | 'expired'

/**
 * Stock on an inventory item. A count item's `quantity` is its on-hand amount;
 * a level item's is its restock amount. `stockStatus`, `shortfall` and
 * `expiryStatus` are worked out by the server. `expiresAt` is a midnight
 * timestamp in seconds, as a date field's value.
 */
export interface ItemStock {
  stockMode: StockMode
  stockLevel: StockLevel
  target: string | null
  reorderPoint: string | null
  stockStatus: StockStatus
  shortfall: string | null
  expiresAt: number | null
  shelfLifeDays: number | null
  expiryStatus: ExpiryStatus
  /** Whether an unlinked item is linked by name to a row on the restock checklist. */
  autoLink: boolean
  /** The checklist item that restocks this item. */
  stockLink: StockLinkRow | null
  /** While unlinked and auto-linking: the rows that match by name, when there are several. */
  linkCandidates: StockLinkRow[]
}

export type StockLinkState = 'open' | 'checked' | 'archived' | 'trashed'

/** A checklist item an inventory item is, or can be, linked to. */
export interface StockLinkRow {
  itemId: number
  name: string
  quantity: string | null
  listId: number
  listName: string
  listColor: string | null
  state: StockLinkState
}

/** An inventory item a checklist item restocks. */
export interface StockLinkSource {
  itemId: number
  name: string
  listId: number
  listName: string
}

/**
 * What a tick of a checklist item put back into one inventory item: the
 * amount `added` to a count item, or the `previousLevel` of a level item it
 * took to Full.
 */
export interface RestockEntry {
  itemId: number
  listId: number
  itemName: string
  listName: string
  added: string | null
  previousLevel: StockLevel | null
}

/** What Shopping Mode's check or uncheck did to stock. */
export interface ShoppingCheckResult {
  success: true
  restock: RestockEntry[] | null
  restocked: RestockEntry[]
  restockUndone: RestockEntry[]
  restockSync: RestockSyncEvent[]
}

/**
 * What restocking did to a checklist item: `added` a new one, `revived` a
 * checked or archived one, `changed` an open one's quantity, left a `pinned`
 * quantity as set by hand, or `recovered`: ticked it off as no longer needed.
 */
export interface RestockSyncEvent {
  event: 'added' | 'revived' | 'changed' | 'pinned' | 'recovered'
  /** The inventory item whose write caused it. */
  itemId: number
  rowId: number
  rowName: string
  quantity: string | null
  listId: number
  listName: string
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
  /** Every store in `stores` is shopped from one combined list, with no active store. */
  allStores: boolean
  billedTotal: number | null
  billedCurrency: string | null
  live: boolean
  createdAt: number
  updatedAt: number
  listIds: number[]
  stores: ShoppingSessionStore[]
  /** Everyone shopping this trip: the shopper who started it, then whoever joined. */
  memberIds: string[]
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

/** A history row's grand total, collapsed to a single amount per currency. */
export interface ShoppingHistoryTotal {
  currency: string
  amount: number
}

/** One closed trip in the history list. */
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

/** The moment a reminder surfaces during a trip. */
export type ShoppingReminderMoment = 'on_start' | 'on_store_advance' | 'on_close'

/** A house-scoped, user-defined shopping reminder. */
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

/** One present shopper in a house's derived shopping presence. */
export interface ShoppingPresenceEntry {
  userId: string
  /** The trip itself, so a housemate can join it straight from the presence read. */
  sessionId: number
  /** The store the shopper is attributed to; null = live but no store chosen. */
  activeStoreId: number | null
  lastSeenAt: number
  /** Everyone already shopping this trip, starter first. */
  memberIds: string[]
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
  /** File this note mirrors, or null when it stands alone. */
  syncFileId: number | null
  /** Account whose storage holds the file; edits by any member are written as them. */
  syncOwnerUid: string | null
  /** Path of the file, resolved per request — null while the file is out of reach. */
  syncPath: string | null
  /** When the two sides last agreed. */
  syncAt: number | null
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
