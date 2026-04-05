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
