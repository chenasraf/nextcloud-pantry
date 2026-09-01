// Single source of truth for the glyph that represents each domain entity.
//
// These are the generic entity icons — shown in filter facets, composer chips,
// group headers, manage/assign actions, and used as the fallback when an entity
// carries no custom icon of its own. A category's, label's or store's chosen icon
// always overrides the generic glyph; these are only the defaults.

import type { Component } from 'vue'
import LabelOutlineIcon from '@icons/LabelOutline.vue'
import LabelOffOutlineIcon from '@icons/LabelOffOutline.vue'
import TagOutlineIcon from '@icons/TagOutline.vue'
import TagOffOutlineIcon from '@icons/TagOffOutline.vue'
import StorefrontOutlineIcon from '@icons/StorefrontOutline.vue'
import StoreOffOutlineIcon from '@icons/StoreOffOutline.vue'
import CurrencyUsdIcon from '@icons/CurrencyUsd.vue'

/** Glyph representing each entity. */
export const entityIcon = {
  category: LabelOutlineIcon,
  label: TagOutlineIcon,
  store: StorefrontOutlineIcon,
  price: CurrencyUsdIcon,
} satisfies Record<string, Component>

/** Glyph representing the absence of an entity — unset filter facets, empty groups. */
export const entityNoneIcon = {
  category: LabelOffOutlineIcon,
  label: TagOffOutlineIcon,
  store: StoreOffOutlineIcon,
} satisfies Record<string, Component>
