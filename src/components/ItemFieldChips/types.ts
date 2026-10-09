export type ItemFieldSection =
  | 'category'
  | 'labels'
  | 'stores'
  | 'quantity'
  | 'price'
  | 'customfields'
  | 'description'
  | 'type'
  | 'image'

/**
 * `compose` shows every field of a new item. `defaults` shows only the fields a
 * list can pre-fill, for editing the list's item defaults.
 */
export type ItemFieldChipsMode = 'compose' | 'defaults'

/** Fields that only make sense on a single item, never as a list default. */
export const ITEM_ONLY_SECTIONS: readonly ItemFieldSection[] = ['price', 'description', 'image']
