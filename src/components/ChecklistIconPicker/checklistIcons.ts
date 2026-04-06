// Curated palette of checklist icons. The key is what we persist in the DB;
// the component is resolved at render time.

import type { Component } from 'vue'
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import ClipboardListIcon from '@icons/ClipboardList.vue'
import FormatListChecksIcon from '@icons/FormatListChecks.vue'
import CartIcon from '@icons/Cart.vue'
import BasketIcon from '@icons/Basket.vue'
import StarIcon from '@icons/Star.vue'
import HeartIcon from '@icons/Heart.vue'
import HomeIcon from '@icons/Home.vue'
import CalendarIcon from '@icons/Calendar.vue'
import BellIcon from '@icons/Bell.vue'
import FlagIcon from '@icons/Flag.vue'
import BookmarkIcon from '@icons/Bookmark.vue'
import PinIcon from '@icons/Pin.vue'
import MapMarkerIcon from '@icons/MapMarker.vue'
import BriefcaseIcon from '@icons/Briefcase.vue'
import WrenchIcon from '@icons/Wrench.vue'
import SilverwareIcon from '@icons/Silverware.vue'
import CoffeeIcon from '@icons/Coffee.vue'
import GiftIcon from '@icons/Gift.vue'
import BookIcon from '@icons/Book.vue'
import SchoolIcon from '@icons/School.vue'
import PaletteIcon from '@icons/Palette.vue'
import CameraIcon from '@icons/Camera.vue'
import MusicIcon from '@icons/Music.vue'
import GamepadIcon from '@icons/Gamepad.vue'
import RunIcon from '@icons/Run.vue'
import DumbbellIcon from '@icons/Dumbbell.vue'
import PillIcon from '@icons/Pill.vue'
import PawIcon from '@icons/Paw.vue'
import FlowerIcon from '@icons/Flower.vue'
import TreeIcon from '@icons/Tree.vue'
import BroomIcon from '@icons/Broom.vue'
import LightbulbIcon from '@icons/Lightbulb.vue'
import PackageIcon from '@icons/Package.vue'
import CarIcon from '@icons/Car.vue'
import BikeIcon from '@icons/Bike.vue'
import BeachIcon from '@icons/Beach.vue'
import TagIcon from '@icons/Tag.vue'

export interface ChecklistIconOption {
  key: string
  label: string
  component: Component
}

/** The default fallback icon used for unknown keys. */
export const DEFAULT_CHECKLIST_ICON_KEY = 'clipboard-check'

export const CHECKLIST_ICONS: ChecklistIconOption[] = [
  { key: 'clipboard-check', label: 'Checklist', component: ClipboardCheckIcon },
  { key: 'clipboard-list', label: 'List', component: ClipboardListIcon },
  { key: 'format-list-checks', label: 'Tasks', component: FormatListChecksIcon },
  { key: 'cart', label: 'Shopping', component: CartIcon },
  { key: 'basket', label: 'Basket', component: BasketIcon },
  { key: 'star', label: 'Favorites', component: StarIcon },
  { key: 'heart', label: 'Wishlist', component: HeartIcon },
  { key: 'home', label: 'Home', component: HomeIcon },
  { key: 'calendar', label: 'Calendar', component: CalendarIcon },
  { key: 'bell', label: 'Reminders', component: BellIcon },
  { key: 'flag', label: 'Goals', component: FlagIcon },
  { key: 'bookmark', label: 'Saved', component: BookmarkIcon },
  { key: 'pin', label: 'Pinned', component: PinIcon },
  { key: 'map-marker', label: 'Places', component: MapMarkerIcon },
  { key: 'briefcase', label: 'Work', component: BriefcaseIcon },
  { key: 'wrench', label: 'Repairs', component: WrenchIcon },
  { key: 'silverware', label: 'Meals', component: SilverwareIcon },
  { key: 'coffee', label: 'Coffee', component: CoffeeIcon },
  { key: 'gift', label: 'Gifts', component: GiftIcon },
  { key: 'book', label: 'Reading', component: BookIcon },
  { key: 'school', label: 'School', component: SchoolIcon },
  { key: 'palette', label: 'Creative', component: PaletteIcon },
  { key: 'camera', label: 'Photos', component: CameraIcon },
  { key: 'music', label: 'Music', component: MusicIcon },
  { key: 'gamepad', label: 'Games', component: GamepadIcon },
  { key: 'run', label: 'Exercise', component: RunIcon },
  { key: 'dumbbell', label: 'Fitness', component: DumbbellIcon },
  { key: 'pill', label: 'Health', component: PillIcon },
  { key: 'paw', label: 'Pets', component: PawIcon },
  { key: 'flower', label: 'Garden', component: FlowerIcon },
  { key: 'tree', label: 'Outdoors', component: TreeIcon },
  { key: 'broom', label: 'Cleaning', component: BroomIcon },
  { key: 'lightbulb', label: 'Ideas', component: LightbulbIcon },
  { key: 'package', label: 'Packing', component: PackageIcon },
  { key: 'car', label: 'Travel', component: CarIcon },
  { key: 'bike', label: 'Cycling', component: BikeIcon },
  { key: 'beach', label: 'Vacation', component: BeachIcon },
  { key: 'tag', label: 'Other', component: TagIcon },
]

const byKey: Record<string, ChecklistIconOption> = Object.fromEntries(
  CHECKLIST_ICONS.map((o) => [o.key, o]),
)

export function checklistIconComponent(key: string | null | undefined): Component {
  return byKey[key ?? '']?.component ?? ClipboardCheckIcon
}
