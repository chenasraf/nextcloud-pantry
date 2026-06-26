import { describe, expect, it, vi } from 'vitest'
import { nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

import { buildListMarkdown, parseMarkdownItems } from './markdownList'
import type { Category, ChecklistItem } from '@/api/types'

function item(partial: Partial<ChecklistItem>): ChecklistItem {
  return {
    id: 1,
    listId: 1,
    name: 'Item',
    description: null,
    categoryId: null,
    quantity: null,
    done: false,
    doneAt: null,
    doneBy: null,
    rrule: null,
    repeatFromCompletion: false,
    deleteOnDone: false,
    nextDueAt: null,
    imageFileId: null,
    imageUploadedBy: null,
    addedBy: null,
    sortOrder: 0,
    createdAt: 0,
    updatedAt: 0,
    deletedAt: null,
    ...partial,
  }
}

const produce: Category = {
  id: 10,
  houseId: 1,
  name: 'Produce',
  icon: 'food-apple',
  color: '#00ff00',
  sortOrder: 0,
  createdAt: 0,
  updatedAt: 0,
}

const categoryFor = (id: number | null) => (id === produce.id ? produce : null)

describe('buildListMarkdown', () => {
  const now = new Date(2026, 5, 26) // 2026-06-26 (month is 0-based)

  it('renders title and export date', () => {
    const md = buildListMarkdown('Groceries', [], categoryFor, now)
    expect(md).toContain('# Groceries')
    expect(md).toContain('_Exported 2026-06-26_')
  })

  it('groups items under category headings, uncategorized last', () => {
    const md = buildListMarkdown(
      'Groceries',
      [
        item({ id: 1, name: 'Milk', categoryId: null }),
        item({ id: 2, name: 'Apples', categoryId: produce.id }),
      ],
      categoryFor,
      now,
    )
    const produceIdx = md.indexOf('## Produce')
    const uncatIdx = md.indexOf('## Uncategorized')
    expect(produceIdx).toBeGreaterThan(-1)
    expect(uncatIdx).toBeGreaterThan(produceIdx)
  })

  it('renders checkbox state and inline quantity/description', () => {
    const md = buildListMarkdown(
      'Groceries',
      [
        item({ name: 'Apples', quantity: '1 kg', done: false }),
        item({ name: 'Bananas', done: true }),
        item({ name: 'Milk', quantity: '2 L', description: 'organic' }),
      ],
      categoryFor,
      now,
    )
    expect(md).toContain('- [ ] Apples — 1 kg')
    expect(md).toContain('- [x] Bananas')
    expect(md).toContain('- [ ] Milk — 2 L — organic')
  })

  it('flattens newlines in descriptions', () => {
    const md = buildListMarkdown('L', [item({ name: 'X', description: 'a\nb' })], categoryFor, now)
    expect(md).toContain('- [ ] X — a b')
  })
})

describe('parseMarkdownItems', () => {
  it('parses checkbox items with state', () => {
    expect(parseMarkdownItems('- [ ] Milk\n- [x] Bread')).toEqual([
      { name: 'Milk', done: false },
      { name: 'Bread', done: true },
    ])
  })

  it('parses plain bullets and ordered lists', () => {
    expect(parseMarkdownItems('- Apples\n* Bananas\n+ Pears\n1. Grapes\n2) Plums')).toEqual([
      { name: 'Apples', done: false },
      { name: 'Bananas', done: false },
      { name: 'Pears', done: false },
      { name: 'Grapes', done: false },
      { name: 'Plums', done: false },
    ])
  })

  it('ignores headings, prose and blank lines', () => {
    const text = '# Groceries\n\n_Exported 2026-06-26_\n\n## Produce\n- Apples\n\nsome note\n'
    expect(parseMarkdownItems(text)).toEqual([{ name: 'Apples', done: false }])
  })

  it('strips the quantity/description suffix to recover the name', () => {
    expect(parseMarkdownItems('- [ ] Milk — 2 L — organic')).toEqual([
      { name: 'Milk', done: false },
    ])
  })

  it('round-trips names from buildListMarkdown', () => {
    const md = buildListMarkdown(
      'Groceries',
      [item({ name: 'Apples', quantity: '1 kg', categoryId: produce.id })],
      categoryFor,
      new Date(2026, 5, 26),
    )
    expect(parseMarkdownItems(md)).toEqual([{ name: 'Apples', done: false }])
  })

  it('skips empty checkbox lines', () => {
    expect(parseMarkdownItems('- [ ] \n-   ')).toEqual([])
  })
})
