// Curated palette of store icons. The key is what we persist in the DB (kept in sync
// with ConstantsService::STORE_ICON_KEYS on the backend); the component is resolved at
// render time. Store icons are shop-type themed and intentionally separate from the
// category icon set so the two can diverge.

import type { Component } from 'vue'
import { entityIcon } from '@/utils/entityIcons'
import StoreIcon from '@icons/Store.vue'
import StorefrontIcon from '@icons/Storefront.vue'
import MarketIcon from '@icons/StorefrontOutline.vue'
import SupermarketIcon from '@icons/CashRegister.vue'
import ConvenienceIcon from '@icons/Store24Hour.vue'
import CartIcon from '@icons/Cart.vue'
import BasketIcon from '@icons/Basket.vue'
import ShoppingIcon from '@icons/ShoppingOutline.vue'
import OnlineIcon from '@icons/Web.vue'
import WarehouseIcon from '@icons/Warehouse.vue'
import PharmacyIcon from '@icons/MedicalBag.vue'
import HealthIcon from '@icons/Pill.vue'
import BakeryIcon from '@icons/BreadSlice.vue'
import ButcherIcon from '@icons/FoodDrumstick.vue'
import SeafoodIcon from '@icons/Fish.vue'
import ProduceIcon from '@icons/Carrot.vue'
import GardenIcon from '@icons/Sprout.vue'
import FloristIcon from '@icons/Flower.vue'
import HardwareIcon from '@icons/Toolbox.vue'
import ToolsIcon from '@icons/Hammer.vue'
import WrenchIcon from '@icons/Wrench.vue'
import ElectronicsIcon from '@icons/Laptop.vue'
import PhoneIcon from '@icons/Cellphone.vue'
import ClothingIcon from '@icons/TshirtCrew.vue'
import ShoesIcon from '@icons/ShoeSneaker.vue'
import FurnitureIcon from '@icons/Sofa.vue'
import HomeGoodsIcon from '@icons/SofaSingle.vue'
import HomeIcon from '@icons/HomeOutline.vue'
import BooksIcon from '@icons/BookOpenPageVariant.vue'
import ToysIcon from '@icons/TeddyBear.vue'
import PetsIcon from '@icons/Dog.vue'
import LiquorIcon from '@icons/BottleWine.vue'
import CoffeeIcon from '@icons/Coffee.vue'
import GasIcon from '@icons/GasStation.vue'
import GiftIcon from '@icons/Gift.vue'
import JewelryIcon from '@icons/Diamond.vue'
import SportsIcon from '@icons/Basketball.vue'
import BeautyIcon from '@icons/Lipstick.vue'
import OfficeIcon from '@icons/Briefcase.vue'
import BabyIcon from '@icons/BabyCarriage.vue'
import DeliIcon from '@icons/Silverware.vue'

export interface StoreIconOption {
  key: string
  label: string
  component: Component
}

/** The default fallback icon used for unknown keys. */
export const DEFAULT_STORE_ICON_KEY = 'store'

export const STORE_ICONS: StoreIconOption[] = [
  { key: 'store', label: 'Store', component: StoreIcon },
  { key: 'storefront', label: 'Storefront', component: StorefrontIcon },
  { key: 'market', label: 'Market', component: MarketIcon },
  { key: 'supermarket', label: 'Supermarket', component: SupermarketIcon },
  { key: 'convenience', label: 'Convenience', component: ConvenienceIcon },
  { key: 'cart', label: 'Shopping', component: CartIcon },
  { key: 'basket', label: 'Basket', component: BasketIcon },
  { key: 'shopping', label: 'Shopping bag', component: ShoppingIcon },
  { key: 'online', label: 'Online', component: OnlineIcon },
  { key: 'warehouse', label: 'Warehouse', component: WarehouseIcon },
  { key: 'pharmacy', label: 'Pharmacy', component: PharmacyIcon },
  { key: 'health', label: 'Health', component: HealthIcon },
  { key: 'bakery', label: 'Bakery', component: BakeryIcon },
  { key: 'butcher', label: 'Butcher', component: ButcherIcon },
  { key: 'seafood', label: 'Seafood', component: SeafoodIcon },
  { key: 'produce', label: 'Produce', component: ProduceIcon },
  { key: 'garden', label: 'Garden', component: GardenIcon },
  { key: 'florist', label: 'Florist', component: FloristIcon },
  { key: 'hardware', label: 'Hardware', component: HardwareIcon },
  { key: 'tools', label: 'Tools', component: ToolsIcon },
  { key: 'wrench', label: 'Wrench', component: WrenchIcon },
  { key: 'electronics', label: 'Electronics', component: ElectronicsIcon },
  { key: 'phone', label: 'Phone', component: PhoneIcon },
  { key: 'clothing', label: 'Clothing', component: ClothingIcon },
  { key: 'shoes', label: 'Shoes', component: ShoesIcon },
  { key: 'furniture', label: 'Furniture', component: FurnitureIcon },
  { key: 'homegoods', label: 'Home goods', component: HomeGoodsIcon },
  { key: 'home', label: 'Home', component: HomeIcon },
  { key: 'books', label: 'Books', component: BooksIcon },
  { key: 'toys', label: 'Toys', component: ToysIcon },
  { key: 'pets', label: 'Pets', component: PetsIcon },
  { key: 'liquor', label: 'Liquor', component: LiquorIcon },
  { key: 'coffee', label: 'Coffee', component: CoffeeIcon },
  { key: 'gas', label: 'Gas station', component: GasIcon },
  { key: 'gift', label: 'Gifts', component: GiftIcon },
  { key: 'jewelry', label: 'Jewelry', component: JewelryIcon },
  { key: 'sports', label: 'Sports', component: SportsIcon },
  { key: 'beauty', label: 'Beauty', component: BeautyIcon },
  { key: 'office', label: 'Office', component: OfficeIcon },
  { key: 'baby', label: 'Baby', component: BabyIcon },
  { key: 'deli', label: 'Deli', component: DeliIcon },
]

const byKey: Record<string, StoreIconOption> = Object.fromEntries(
  STORE_ICONS.map((o) => [o.key, o]),
)

export function storeIconComponent(key: string | null | undefined): Component {
  return byKey[key ?? '']?.component ?? entityIcon.store
}

/** Default palette of colors shown in the store create/edit dialog. */
export const STORE_COLORS: string[] = [
  '#e11d48', // rose
  '#ea580c', // orange
  '#ca8a04', // amber
  '#16a34a', // green
  '#0891b2', // cyan
  '#2563eb', // blue
  '#7c3aed', // violet
  '#c026d3', // fuchsia
  '#db2777', // pink
  '#57534e', // stone
]
