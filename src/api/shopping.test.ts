import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('@/axios', () => ({ ocs: { post } }))

import { closeSession } from './shopping'

describe('closeSession', () => {
  beforeEach(() => {
    post.mockReset()
    post.mockResolvedValue({ data: { id: 5 } })
  })

  it('finishes with an empty body when nothing is put away elsewhere', async () => {
    await closeSession(1, 5)

    expect(post).toHaveBeenCalledWith('/houses/1/shopping/sessions/5/close', {}, expect.any(Object))
  })

  it('sends where ticked items were put away', async () => {
    const putAway = [{ itemId: 10, storages: [{ listId: 20, amount: 6 }] }]

    await closeSession(1, 5, putAway)

    expect(post).toHaveBeenCalledWith(
      '/houses/1/shopping/sessions/5/close',
      { putAway },
      expect.any(Object),
    )
  })
})
