import { describe, expect, it, vi } from 'vitest'
import { nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

import { LIST_KIND_ROUTES, allListsStrings } from './useListKind'

describe('meta "All" views', () => {
  it('exist for checklists and inventories, not templates', () => {
    expect(LIST_KIND_ROUTES.checklist.all).toBe('all-lists')
    expect(LIST_KIND_ROUTES.inventory.all).toBe('all-inventories')
    expect(LIST_KIND_ROUTES.template.all).toBeUndefined()
  })

  it('are named after their kind', () => {
    expect(allListsStrings('checklist').title).toBe('All checklists')
    expect(allListsStrings('inventory')).toEqual({
      title: 'All inventories',
      body: 'Items from every inventory in this house.',
    })
  })
})
