// Curated palette of label icons. The key is what we persist in the DB (kept in sync
// with ConstantsService::LABEL_ICON_KEYS on the backend); the component is resolved at
// render time.

import type { Component } from 'vue'
import { entityIcon } from '@/utils/entityIcons'
import TagIcon from '@icons/Tag.vue'
import TagMultipleIcon from '@icons/TagMultiple.vue'
import TagHeartIcon from '@icons/TagHeart.vue'
import TagPlusIcon from '@icons/TagPlus.vue'
import LabelIcon from '@icons/Label.vue'
import LabelMultipleIcon from '@icons/LabelMultiple.vue'
import StarIcon from '@icons/Star.vue'
import HeartIcon from '@icons/Heart.vue'
import FireIcon from '@icons/Fire.vue'
import FlashIcon from '@icons/Flash.vue'
import LightningBoltIcon from '@icons/LightningBolt.vue'
import PriorityHighIcon from '@icons/PriorityHigh.vue'
import PriorityLowIcon from '@icons/PriorityLow.vue'
import AlertIcon from '@icons/Alert.vue'
import AlertCircleIcon from '@icons/AlertCircle.vue'
import InformationIcon from '@icons/Information.vue'
import CheckCircleIcon from '@icons/CheckCircle.vue'
import CloseCircleIcon from '@icons/CloseCircle.vue'
import ClockIcon from '@icons/Clock.vue'
import CalendarIcon from '@icons/Calendar.vue'
import BellIcon from '@icons/Bell.vue'
import FlagIcon from '@icons/Flag.vue'
import BookmarkIcon from '@icons/Bookmark.vue'
import PinIcon from '@icons/Pin.vue'
import MapMarkerIcon from '@icons/MapMarker.vue'
import GiftIcon from '@icons/Gift.vue'
import SaleIcon from '@icons/Sale.vue'
import PercentIcon from '@icons/Percent.vue'
import CurrencyUsdIcon from '@icons/CurrencyUsd.vue'
import CartIcon from '@icons/Cart.vue'
import BasketIcon from '@icons/Basket.vue'
import TruckIcon from '@icons/Truck.vue'
import TicketIcon from '@icons/Ticket.vue'
import BarcodeIcon from '@icons/Barcode.vue'
import SealIcon from '@icons/Seal.vue'
import LeafIcon from '@icons/Leaf.vue'
import SproutIcon from '@icons/Sprout.vue'
import RecycleIcon from '@icons/Recycle.vue'
import SnowflakeIcon from '@icons/Snowflake.vue'
import WaterIcon from '@icons/WaterOutline.vue'
import FoodAppleIcon from '@icons/FoodApple.vue'
import SilverwareIcon from '@icons/Silverware.vue'
import CupIcon from '@icons/Cup.vue'
import PillIcon from '@icons/Pill.vue'
import MedicalBagIcon from '@icons/MedicalBag.vue'
import PawIcon from '@icons/Paw.vue'
import BabyIcon from '@icons/Baby.vue'
import HomeIcon from '@icons/Home.vue'
import BriefcaseIcon from '@icons/Briefcase.vue'
import SchoolIcon from '@icons/School.vue'
import PaletteIcon from '@icons/Palette.vue'
import MusicIcon from '@icons/Music.vue'
import CameraIcon from '@icons/Camera.vue'
import GamepadIcon from '@icons/Gamepad.vue'
import RunIcon from '@icons/Run.vue'
import DumbbellIcon from '@icons/Dumbbell.vue'
import WrenchIcon from '@icons/Wrench.vue'
import NewBoxIcon from '@icons/NewBox.vue'
import StickerIcon from '@icons/Sticker.vue'
import ThumbUpIcon from '@icons/ThumbUp.vue'
import EyeIcon from '@icons/Eye.vue'
import LockIcon from '@icons/Lock.vue'
import KeyIcon from '@icons/Key.vue'
import ShieldIcon from '@icons/Shield.vue'
import DiamondIcon from '@icons/Diamond.vue'
import CrownIcon from '@icons/Crown.vue'
import RocketIcon from '@icons/Rocket.vue'
import BugIcon from '@icons/Bug.vue'
import PuzzleIcon from '@icons/Puzzle.vue'
import FeatherIcon from '@icons/Feather.vue'
import BullhornIcon from '@icons/Bullhorn.vue'
import CreationIcon from '@icons/Creation.vue'
import StarShootingIcon from '@icons/StarShooting.vue'

export interface LabelIconOption {
  key: string
  label: string
  component: Component
}

/** The default fallback icon used for unknown keys. */
export const DEFAULT_LABEL_ICON_KEY = 'tag'

export const LABEL_ICONS: LabelIconOption[] = [
  { key: 'tag', label: 'Tag', component: TagIcon },
  { key: 'tag-multiple', label: 'Tags', component: TagMultipleIcon },
  { key: 'tag-heart', label: 'Favorite tag', component: TagHeartIcon },
  { key: 'tag-plus', label: 'New tag', component: TagPlusIcon },
  { key: 'label', label: 'Label', component: LabelIcon },
  { key: 'label-multiple', label: 'Labels', component: LabelMultipleIcon },
  { key: 'star', label: 'Star', component: StarIcon },
  { key: 'heart', label: 'Heart', component: HeartIcon },
  { key: 'fire', label: 'Hot', component: FireIcon },
  { key: 'flash', label: 'Flash', component: FlashIcon },
  { key: 'lightning-bolt', label: 'Energy', component: LightningBoltIcon },
  { key: 'priority-high', label: 'High priority', component: PriorityHighIcon },
  { key: 'priority-low', label: 'Low priority', component: PriorityLowIcon },
  { key: 'alert', label: 'Alert', component: AlertIcon },
  { key: 'alert-circle', label: 'Warning', component: AlertCircleIcon },
  { key: 'information', label: 'Info', component: InformationIcon },
  { key: 'check-circle', label: 'Done', component: CheckCircleIcon },
  { key: 'close-circle', label: 'Blocked', component: CloseCircleIcon },
  { key: 'clock', label: 'Time', component: ClockIcon },
  { key: 'calendar', label: 'Date', component: CalendarIcon },
  { key: 'bell', label: 'Reminder', component: BellIcon },
  { key: 'flag', label: 'Flag', component: FlagIcon },
  { key: 'bookmark', label: 'Saved', component: BookmarkIcon },
  { key: 'pin', label: 'Pinned', component: PinIcon },
  { key: 'map-marker', label: 'Place', component: MapMarkerIcon },
  { key: 'gift', label: 'Gift', component: GiftIcon },
  { key: 'sale', label: 'Sale', component: SaleIcon },
  { key: 'percent', label: 'Discount', component: PercentIcon },
  { key: 'currency-usd', label: 'Price', component: CurrencyUsdIcon },
  { key: 'cart', label: 'Shopping', component: CartIcon },
  { key: 'basket', label: 'Basket', component: BasketIcon },
  { key: 'truck', label: 'Delivery', component: TruckIcon },
  { key: 'ticket', label: 'Ticket', component: TicketIcon },
  { key: 'barcode', label: 'Barcode', component: BarcodeIcon },
  { key: 'seal', label: 'Seal', component: SealIcon },
  { key: 'leaf', label: 'Organic', component: LeafIcon },
  { key: 'sprout', label: 'Fresh', component: SproutIcon },
  { key: 'recycle', label: 'Recyclable', component: RecycleIcon },
  { key: 'snowflake', label: 'Frozen', component: SnowflakeIcon },
  { key: 'water', label: 'Water', component: WaterIcon },
  { key: 'food-apple', label: 'Fruit', component: FoodAppleIcon },
  { key: 'silverware', label: 'Meals', component: SilverwareIcon },
  { key: 'cup', label: 'Drinks', component: CupIcon },
  { key: 'pill', label: 'Medicine', component: PillIcon },
  { key: 'medical-bag', label: 'Health', component: MedicalBagIcon },
  { key: 'paw', label: 'Pets', component: PawIcon },
  { key: 'baby', label: 'Baby', component: BabyIcon },
  { key: 'home', label: 'Home', component: HomeIcon },
  { key: 'briefcase', label: 'Work', component: BriefcaseIcon },
  { key: 'school', label: 'School', component: SchoolIcon },
  { key: 'palette', label: 'Creative', component: PaletteIcon },
  { key: 'music', label: 'Music', component: MusicIcon },
  { key: 'camera', label: 'Photos', component: CameraIcon },
  { key: 'gamepad', label: 'Games', component: GamepadIcon },
  { key: 'run', label: 'Exercise', component: RunIcon },
  { key: 'dumbbell', label: 'Fitness', component: DumbbellIcon },
  { key: 'wrench', label: 'Repairs', component: WrenchIcon },
  { key: 'new-box', label: 'New', component: NewBoxIcon },
  { key: 'sticker', label: 'Sticker', component: StickerIcon },
  { key: 'thumb-up', label: 'Liked', component: ThumbUpIcon },
  { key: 'eye', label: 'Watch', component: EyeIcon },
  { key: 'lock', label: 'Locked', component: LockIcon },
  { key: 'key', label: 'Key', component: KeyIcon },
  { key: 'shield', label: 'Protected', component: ShieldIcon },
  { key: 'diamond', label: 'Premium', component: DiamondIcon },
  { key: 'crown', label: 'Top', component: CrownIcon },
  { key: 'rocket', label: 'Fast', component: RocketIcon },
  { key: 'bug', label: 'Issue', component: BugIcon },
  { key: 'puzzle', label: 'Puzzle', component: PuzzleIcon },
  { key: 'feather', label: 'Light', component: FeatherIcon },
  { key: 'bullhorn', label: 'Announcement', component: BullhornIcon },
  { key: 'creation', label: 'Special', component: CreationIcon },
  { key: 'star-shooting', label: 'Trending', component: StarShootingIcon },
]

const byKey: Record<string, LabelIconOption> = Object.fromEntries(
  LABEL_ICONS.map((o) => [o.key, o]),
)

export function labelIconComponent(key: string | null | undefined): Component {
  return byKey[key ?? '']?.component ?? entityIcon.label
}

/** Default palette of colors shown in the inline create dialog. */
export const LABEL_COLORS: string[] = [
  '#ef4444', // red
  '#f97316', // orange
  '#f59e0b', // amber
  '#eab308', // yellow
  '#84cc16', // lime
  '#22c55e', // green
  '#10b981', // emerald
  '#14b8a6', // teal
  '#06b6d4', // cyan
  '#0ea5e9', // sky
  '#3b82f6', // blue
  '#6366f1', // indigo
  '#8b5cf6', // violet
  '#a855f7', // purple
  '#d946ef', // fuchsia
  '#ec4899', // pink
  '#f43f5e', // rose
  '#78716c', // stone
]
