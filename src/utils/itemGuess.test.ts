import { describe, expect, it } from 'vitest'

import { type GuessContext, guessLine, resolveLine } from './itemGuess'
import { emptyMemoryIndex, mergeMemory } from './itemMemory'

const memory = mergeMemory(
  emptyMemoryIndex(),
  [
    { nameKey: 'milk', categoryId: 2, uses: 3, rejections: 0, updatedAt: 1 },
    { nameKey: '7up', categoryId: 5, uses: 1, rejections: 0, updatedAt: 1 },
  ],
  [{ listId: 1, nameKey: 'milk', quantity: '1 L', updatedAt: 1 }],
)

function ctx(overrides: Partial<GuessContext> = {}): GuessContext {
  return {
    locale: 'en',
    memory,
    listId: 1,
    usableCategoryIds: new Set([2, 5]),
    formCategoryId: null,
    formQuantity: '',
    ...overrides,
  }
}

const emptyForm = { categoryId: null, quantity: '' }

describe('guessLine', () => {
  it('splits the amount out and guesses the category', () => {
    expect(guessLine('2 L milk', ctx())).toEqual({
      name: 'milk',
      parsedQuantity: '2 L',
      categoryId: 2,
      quantity: null,
    })
  })

  it('guesses the remembered amount when none was typed', () => {
    expect(guessLine('Milk', ctx())).toEqual({
      name: 'Milk',
      parsedQuantity: null,
      categoryId: 2,
      quantity: '1 L',
    })
  })

  it('looks the whole line up once the split is dropped', () => {
    const guess = guessLine('2 milk', ctx(), { split: true })

    expect(guess.name).toBe('2 milk')
    expect(guess.parsedQuantity).toBeNull()
    expect(guess.categoryId).toBeNull()
  })

  it('guesses no category when the form already has one, from the person, defaults or a barcode', () => {
    expect(guessLine('Milk', ctx({ formCategoryId: 9 })).categoryId).toBeNull()
  })

  it('guesses no amount when the form already has one', () => {
    expect(guessLine('Milk', ctx({ formQuantity: '3' })).quantity).toBeNull()
  })

  it('only offers categories usable on the target list', () => {
    expect(guessLine('Milk', ctx({ usableCategoryIds: new Set([5]) })).categoryId).toBeNull()
  })

  it('guesses nothing without a target list, but still splits', () => {
    expect(guessLine('2 L milk', ctx({ listId: null }))).toEqual({
      name: 'milk',
      parsedQuantity: '2 L',
      categoryId: null,
      quantity: null,
    })
  })
})

describe('resolveLine', () => {
  it('saves the kept guesses', () => {
    expect(resolveLine(guessLine('Milk', ctx()), {}, emptyForm)).toEqual({
      name: 'Milk',
      quantity: '1 L',
      categoryId: 2,
    })
  })

  it('reports dropped guesses as rejected and leaves them out', () => {
    const drops = { category: true, quantity: true }

    expect(resolveLine(guessLine('Milk', ctx()), drops, emptyForm)).toEqual({
      name: 'Milk',
      quantity: null,
      categoryId: null,
      suggestionRejected: { categoryId: 2, quantity: true },
    })
  })

  it('lets the typed amount win over the form', () => {
    const form = { categoryId: null, quantity: '5' }

    expect(resolveLine(guessLine('2 L milk', ctx({ formQuantity: '5' })), {}, form).quantity).toBe(
      '2 L',
    )
  })

  it("keeps the form's values over any guess", () => {
    const form = { categoryId: 9, quantity: '3' }
    const guess = guessLine('Milk', ctx({ formCategoryId: 9, formQuantity: '3' }))

    expect(resolveLine(guess, {}, form)).toEqual({ name: 'Milk', quantity: '3', categoryId: 9 })
  })

  it('does not report a dropped split as a rejection', () => {
    expect(resolveLine(guessLine('2 eggs', ctx()), { split: true }, emptyForm)).not.toHaveProperty(
      'suggestionRejected',
    )
  })
})
