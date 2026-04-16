// Curated palette of category icons. The key is what we persist in the DB (kept in sync
// with CategoryService::ICON_KEYS on the backend); the component is resolved at render time.

import type { Component } from 'vue'
import TagIcon from '@icons/Tag.vue'
import FoodIcon from '@icons/Food.vue'
import FruitIcon from '@icons/FoodApple.vue'
import VegetableIcon from '@icons/Carrot.vue'
import BakeryIcon from '@icons/BreadSlice.vue'
import DairyIcon from '@icons/Cheese.vue'
import MeatIcon from '@icons/FoodDrumstick.vue'
import FishIcon from '@icons/Fish.vue'
import SnacksIcon from '@icons/FoodCroissant.vue'
import CookieIcon from '@icons/Cookie.vue'
import DrinksIcon from '@icons/BottleWine.vue'
import CoffeeIcon from '@icons/Coffee.vue'
import FrozenIcon from '@icons/Snowflake.vue'
import HouseholdIcon from '@icons/Broom.vue'
import PetsIcon from '@icons/Dog.vue'
import BabyIcon from '@icons/Baby.vue'
import HomeIcon from '@icons/Home.vue'
import LeafIcon from '@icons/Leaf.vue'
import PizzaIcon from '@icons/Pizza.vue'
// Shared with the checklist icon picker — these cover general-purpose use cases
// (shopping, reminders, travel, hobbies, etc.) and make categories flexible
// beyond the food-centric default set above.
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import ClipboardListIcon from '@icons/ClipboardList.vue'
import FormatListChecksIcon from '@icons/FormatListChecks.vue'
import CartIcon from '@icons/Cart.vue'
import BasketIcon from '@icons/Basket.vue'
import StarIcon from '@icons/Star.vue'
import HeartIcon from '@icons/Heart.vue'
import CalendarIcon from '@icons/Calendar.vue'
import BellIcon from '@icons/Bell.vue'
import FlagIcon from '@icons/Flag.vue'
import BookmarkIcon from '@icons/Bookmark.vue'
import PinIcon from '@icons/Pin.vue'
import MapMarkerIcon from '@icons/MapMarker.vue'
import BriefcaseIcon from '@icons/Briefcase.vue'
import WrenchIcon from '@icons/Wrench.vue'
import SilverwareIcon from '@icons/Silverware.vue'
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

export interface CategoryIconOption {
  key: string
  label: string
  component: Component
}

/** The default fallback icon used for unknown keys. */
export const DEFAULT_CATEGORY_ICON_KEY = 'tag'

export const CATEGORY_ICONS: CategoryIconOption[] = [
  { key: 'tag', label: 'Tag', component: TagIcon },
  { key: 'food', label: 'Food', component: FoodIcon },
  { key: 'fruit', label: 'Fruit', component: FruitIcon },
  { key: 'vegetable', label: 'Vegetable', component: VegetableIcon },
  { key: 'bakery', label: 'Bakery', component: BakeryIcon },
  { key: 'dairy', label: 'Dairy', component: DairyIcon },
  { key: 'meat', label: 'Meat', component: MeatIcon },
  { key: 'fish', label: 'Fish', component: FishIcon },
  { key: 'snacks', label: 'Snacks', component: SnacksIcon },
  { key: 'cookie', label: 'Sweets', component: CookieIcon },
  { key: 'drinks', label: 'Drinks', component: DrinksIcon },
  { key: 'coffee', label: 'Coffee', component: CoffeeIcon },
  { key: 'frozen', label: 'Frozen', component: FrozenIcon },
  { key: 'household', label: 'Household', component: HouseholdIcon },
  { key: 'pets', label: 'Pets', component: PetsIcon },
  { key: 'baby', label: 'Baby', component: BabyIcon },
  { key: 'home', label: 'Home', component: HomeIcon },
  { key: 'leaf', label: 'Leaf', component: LeafIcon },
  { key: 'pizza', label: 'Pizza', component: PizzaIcon },
  { key: 'clipboard-check', label: 'Checklist', component: ClipboardCheckIcon },
  { key: 'clipboard-list', label: 'List', component: ClipboardListIcon },
  { key: 'format-list-checks', label: 'Tasks', component: FormatListChecksIcon },
  { key: 'cart', label: 'Shopping', component: CartIcon },
  { key: 'basket', label: 'Basket', component: BasketIcon },
  { key: 'star', label: 'Favorites', component: StarIcon },
  { key: 'heart', label: 'Wishlist', component: HeartIcon },
  { key: 'calendar', label: 'Calendar', component: CalendarIcon },
  { key: 'bell', label: 'Reminders', component: BellIcon },
  { key: 'flag', label: 'Goals', component: FlagIcon },
  { key: 'bookmark', label: 'Saved', component: BookmarkIcon },
  { key: 'pin', label: 'Pinned', component: PinIcon },
  { key: 'map-marker', label: 'Places', component: MapMarkerIcon },
  { key: 'briefcase', label: 'Work', component: BriefcaseIcon },
  { key: 'wrench', label: 'Repairs', component: WrenchIcon },
  { key: 'silverware', label: 'Meals', component: SilverwareIcon },
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
  { key: 'paw', label: 'Pets (paw)', component: PawIcon },
  { key: 'flower', label: 'Garden', component: FlowerIcon },
  { key: 'tree', label: 'Outdoors', component: TreeIcon },
  { key: 'broom', label: 'Cleaning', component: BroomIcon },
  { key: 'lightbulb', label: 'Ideas', component: LightbulbIcon },
  { key: 'package', label: 'Packing', component: PackageIcon },
  { key: 'car', label: 'Travel', component: CarIcon },
  { key: 'bike', label: 'Cycling', component: BikeIcon },
  { key: 'beach', label: 'Vacation', component: BeachIcon },
]

const byKey: Record<string, CategoryIconOption> = Object.fromEntries(
  CATEGORY_ICONS.map((o) => [o.key, o]),
)

export function categoryIconComponent(key: string | null | undefined): Component {
  return byKey[key ?? '']?.component ?? TagIcon
}

/** Default palette of colors shown in the inline create dialog. */
export const CATEGORY_COLORS: string[] = [
  '#ef4444', // red
  '#f97316', // orange
  '#eab308', // yellow
  '#22c55e', // green
  '#14b8a6', // teal
  '#0ea5e9', // sky
  '#6366f1', // indigo
  '#a855f7', // purple
  '#ec4899', // pink
  '#78716c', // stone
]
