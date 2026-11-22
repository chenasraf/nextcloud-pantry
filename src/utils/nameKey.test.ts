import { describe, expect, it } from 'vitest'

import cases from '../../tests/fixtures/name-key-cases.json'
import { nameKey } from './nameKey'

describe('nameKey', () => {
  it.each(cases)('folds $name like the server', ({ name, key }) => {
    expect(nameKey(name)).toBe(key)
  })
})
