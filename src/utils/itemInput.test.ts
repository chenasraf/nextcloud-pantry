import { describe, expect, it } from 'vitest'

import cases from '../../tests/fixtures/item-input-cases.json'
import { parseItemInput } from './itemInput'

describe('parseItemInput', () => {
  it.each(cases)('[$locale] $input', ({ locale, input, name, quantity }) => {
    expect(parseItemInput(input, locale)).toEqual({ name, quantity })
  })
})
