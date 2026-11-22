import type { SuggestionRejected } from '@/api/itemMemory'
import { parseItemInput, stripListMarkup } from './itemInput'
import { type MemoryIndex, pickCategory, pickQuantity } from './itemMemory'
import { nameKey } from './nameKey'

/** Guesses on one typed line the person turned down. */
export interface GuessDrops {
  /** Keep the line whole instead of taking an amount out of it. */
  split?: boolean
  category?: boolean
  quantity?: boolean
}

/** What a typed line becomes, and which parts of it are guesses. */
export interface LineGuess {
  name: string
  /** The amount taken out of the text, when it was split. */
  parsedQuantity: string | null
  /** A remembered category, offered only when the form has none. */
  categoryId: number | null
  /** A remembered amount, offered only when neither the text nor the form has one. */
  quantity: string | null
}

export interface GuessContext {
  locale: string
  memory: MemoryIndex
  /** The list the item lands on; without one only the text is split. */
  listId: number | null
  usableCategoryIds: ReadonlySet<number>
  /** What the form already holds, from the person, the list's defaults or a barcode. */
  formCategoryId: number | null
  formQuantity: string
}

export function guessLine(text: string, ctx: GuessContext, drops: GuessDrops = {}): LineGuess {
  const parsed = parseItemInput(text, ctx.locale)
  const split = parsed.quantity !== null && !drops.split
  const name = split ? parsed.name : stripListMarkup(text)
  const parsedQuantity = split ? parsed.quantity : null
  const key = nameKey(name)
  const remember = ctx.listId !== null && key !== ''
  const categoryId =
    remember && ctx.formCategoryId === null
      ? pickCategory(ctx.memory, key, ctx.usableCategoryIds)
      : null
  const quantity =
    remember && parsedQuantity === null && ctx.formQuantity.trim() === ''
      ? pickQuantity(ctx.memory, ctx.listId!, key)
      : null
  return { name, parsedQuantity, categoryId, quantity }
}

export interface ResolvedLine {
  name: string
  quantity: string | null
  categoryId: number | null
  suggestionRejected?: SuggestionRejected
}

/**
 * The values a line is saved with. What was typed wins, then what the form
 * holds, then a remembered guess the person kept.
 */
export function resolveLine(
  guess: LineGuess,
  drops: GuessDrops,
  form: { categoryId: number | null; quantity: string },
): ResolvedLine {
  const keptCategory = guess.categoryId !== null && !drops.category ? guess.categoryId : null
  const keptQuantity = guess.quantity !== null && !drops.quantity ? guess.quantity : null
  const rejected: SuggestionRejected = {}
  if (guess.categoryId !== null && drops.category) rejected.categoryId = guess.categoryId
  if (guess.quantity !== null && drops.quantity) rejected.quantity = true
  return {
    name: guess.name,
    quantity: guess.parsedQuantity ?? (form.quantity.trim() || null) ?? keptQuantity,
    categoryId: form.categoryId ?? keptCategory,
    ...(Object.keys(rejected).length > 0 ? { suggestionRejected: rejected } : {}),
  }
}
