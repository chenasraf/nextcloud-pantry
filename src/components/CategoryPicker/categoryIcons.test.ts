import { describe, expect, it, vi } from 'vitest'

vi.mock('@icons/Tag.vue', () => ({ default: { name: 'TagIcon' } }))
vi.mock('@icons/Food.vue', () => ({ default: { name: 'FoodIcon' } }))
vi.mock('@icons/FoodApple.vue', () => ({ default: { name: 'FruitIcon' } }))
vi.mock('@icons/Carrot.vue', () => ({ default: { name: 'VegetableIcon' } }))
vi.mock('@icons/BreadSlice.vue', () => ({ default: { name: 'BakeryIcon' } }))
vi.mock('@icons/Cheese.vue', () => ({ default: { name: 'DairyIcon' } }))
vi.mock('@icons/FoodDrumstick.vue', () => ({ default: { name: 'MeatIcon' } }))
vi.mock('@icons/Fish.vue', () => ({ default: { name: 'FishIcon' } }))
vi.mock('@icons/FoodCroissant.vue', () => ({ default: { name: 'SnacksIcon' } }))
vi.mock('@icons/Cookie.vue', () => ({ default: { name: 'CookieIcon' } }))
vi.mock('@icons/BottleWine.vue', () => ({ default: { name: 'DrinksIcon' } }))
vi.mock('@icons/Coffee.vue', () => ({ default: { name: 'CoffeeIcon' } }))
vi.mock('@icons/Snowflake.vue', () => ({ default: { name: 'FrozenIcon' } }))
vi.mock('@icons/Broom.vue', () => ({ default: { name: 'HouseholdIcon' } }))
vi.mock('@icons/Dog.vue', () => ({ default: { name: 'PetsIcon' } }))
vi.mock('@icons/Baby.vue', () => ({ default: { name: 'BabyIcon' } }))
vi.mock('@icons/Home.vue', () => ({ default: { name: 'HomeIcon' } }))
vi.mock('@icons/Leaf.vue', () => ({ default: { name: 'LeafIcon' } }))
vi.mock('@icons/Pizza.vue', () => ({ default: { name: 'PizzaIcon' } }))
vi.mock('@icons/ClipboardCheck.vue', () => ({ default: { name: 'ClipboardCheckIcon' } }))
vi.mock('@icons/ClipboardList.vue', () => ({ default: { name: 'ClipboardListIcon' } }))
vi.mock('@icons/FormatListChecks.vue', () => ({ default: { name: 'FormatListChecksIcon' } }))
vi.mock('@icons/Cart.vue', () => ({ default: { name: 'CartIcon' } }))
vi.mock('@icons/Basket.vue', () => ({ default: { name: 'BasketIcon' } }))
vi.mock('@icons/Star.vue', () => ({ default: { name: 'StarIcon' } }))
vi.mock('@icons/Heart.vue', () => ({ default: { name: 'HeartIcon' } }))
vi.mock('@icons/Calendar.vue', () => ({ default: { name: 'CalendarIcon' } }))
vi.mock('@icons/Bell.vue', () => ({ default: { name: 'BellIcon' } }))
vi.mock('@icons/Flag.vue', () => ({ default: { name: 'FlagIcon' } }))
vi.mock('@icons/Bookmark.vue', () => ({ default: { name: 'BookmarkIcon' } }))
vi.mock('@icons/Pin.vue', () => ({ default: { name: 'PinIcon' } }))
vi.mock('@icons/MapMarker.vue', () => ({ default: { name: 'MapMarkerIcon' } }))
vi.mock('@icons/Briefcase.vue', () => ({ default: { name: 'BriefcaseIcon' } }))
vi.mock('@icons/Wrench.vue', () => ({ default: { name: 'WrenchIcon' } }))
vi.mock('@icons/Silverware.vue', () => ({ default: { name: 'SilverwareIcon' } }))
vi.mock('@icons/Gift.vue', () => ({ default: { name: 'GiftIcon' } }))
vi.mock('@icons/Book.vue', () => ({ default: { name: 'BookIcon' } }))
vi.mock('@icons/School.vue', () => ({ default: { name: 'SchoolIcon' } }))
vi.mock('@icons/Palette.vue', () => ({ default: { name: 'PaletteIcon' } }))
vi.mock('@icons/Camera.vue', () => ({ default: { name: 'CameraIcon' } }))
vi.mock('@icons/Music.vue', () => ({ default: { name: 'MusicIcon' } }))
vi.mock('@icons/Gamepad.vue', () => ({ default: { name: 'GamepadIcon' } }))
vi.mock('@icons/Run.vue', () => ({ default: { name: 'RunIcon' } }))
vi.mock('@icons/Dumbbell.vue', () => ({ default: { name: 'DumbbellIcon' } }))
vi.mock('@icons/Pill.vue', () => ({ default: { name: 'PillIcon' } }))
vi.mock('@icons/Paw.vue', () => ({ default: { name: 'PawIcon' } }))
vi.mock('@icons/Flower.vue', () => ({ default: { name: 'FlowerIcon' } }))
vi.mock('@icons/Tree.vue', () => ({ default: { name: 'TreeIcon' } }))
vi.mock('@icons/Lightbulb.vue', () => ({ default: { name: 'LightbulbIcon' } }))
vi.mock('@icons/Package.vue', () => ({ default: { name: 'PackageIcon' } }))
vi.mock('@icons/Car.vue', () => ({ default: { name: 'CarIcon' } }))
vi.mock('@icons/Bike.vue', () => ({ default: { name: 'BikeIcon' } }))
vi.mock('@icons/Beach.vue', () => ({ default: { name: 'BeachIcon' } }))

import {
  CATEGORY_COLORS,
  CATEGORY_ICONS,
  DEFAULT_CATEGORY_ICON_KEY,
  categoryIconComponent,
} from './categoryIcons'

describe('CATEGORY_ICONS', () => {
  it('has 54 entries', () => {
    expect(CATEGORY_ICONS).toHaveLength(54)
  })

  it('each entry has key, label, and component', () => {
    for (const icon of CATEGORY_ICONS) {
      expect(icon).toHaveProperty('key')
      expect(icon).toHaveProperty('label')
      expect(icon).toHaveProperty('component')
      expect(typeof icon.key).toBe('string')
      expect(typeof icon.label).toBe('string')
      expect(icon.component).toBeDefined()
    }
  })

  it('has no duplicate keys', () => {
    const keys = CATEGORY_ICONS.map((i) => i.key)
    expect(new Set(keys).size).toBe(keys.length)
  })
})

describe('DEFAULT_CATEGORY_ICON_KEY', () => {
  it('is "tag"', () => {
    expect(DEFAULT_CATEGORY_ICON_KEY).toBe('tag')
  })
})

describe('categoryIconComponent', () => {
  it('returns the correct component for known keys', () => {
    const result = categoryIconComponent('food') as { name: string }
    expect(result.name).toBe('FoodIcon')
  })

  it('returns the correct component for "dairy"', () => {
    const result = categoryIconComponent('dairy') as { name: string }
    expect(result.name).toBe('DairyIcon')
  })

  it('returns the correct component for "tag"', () => {
    const result = categoryIconComponent('tag') as { name: string }
    expect(result.name).toBe('TagIcon')
  })

  it('returns the fallback TagIcon for unknown keys', () => {
    const result = categoryIconComponent('nonexistent') as { name: string }
    expect(result.name).toBe('TagIcon')
  })

  it('returns the fallback TagIcon for null', () => {
    const result = categoryIconComponent(null) as { name: string }
    expect(result.name).toBe('TagIcon')
  })

  it('returns the fallback TagIcon for undefined', () => {
    const result = categoryIconComponent(undefined) as { name: string }
    expect(result.name).toBe('TagIcon')
  })
})

describe('CATEGORY_COLORS', () => {
  it('has 10 entries', () => {
    expect(CATEGORY_COLORS).toHaveLength(10)
  })

  it('all entries are valid hex color strings', () => {
    const hexColorRegex = /^#[0-9a-fA-F]{6}$/
    for (const color of CATEGORY_COLORS) {
      expect(color).toMatch(hexColorRegex)
    }
  })
})
