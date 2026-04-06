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

import {
  CATEGORY_COLORS,
  CATEGORY_ICONS,
  DEFAULT_CATEGORY_ICON_KEY,
  categoryIconComponent,
} from './categoryIcons'

describe('CATEGORY_ICONS', () => {
  it('has 19 entries', () => {
    expect(CATEGORY_ICONS).toHaveLength(19)
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
