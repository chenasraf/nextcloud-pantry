import { describe, expect, it, vi } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'

vi.mock('@nextcloud/router', () => ({ generateUrl: (path: string) => path }))

import router from './router'

describe('router', () => {
  it('opens the "All inventories" view rather than an inventory named "all"', () => {
    const route = router.resolve('/houses/3/inventories/all')

    expect(route.name).toBe('all-inventories')
    expect(route.meta.listKind).toBe('inventory')
  })

  it('still opens a single inventory by id', () => {
    const route = router.resolve('/houses/3/inventories/12')

    expect(route.name).toBe('inventory-detail')
    expect(route.params.listId).toBe('12')
  })

  it('passes the meta list id and the inventory kind to the list view', () => {
    const route = router.resolve('/houses/3/inventories/all')
    const props = route.matched[route.matched.length - 1].props.default

    // A resolved route is what the router hands the props function once navigated.
    const normalized = route as unknown as RouteLocationNormalized
    expect(typeof props === 'function' ? props(normalized) : props).toEqual({
      houseId: '3',
      listId: 'all',
      kind: 'inventory',
    })
  })
})
