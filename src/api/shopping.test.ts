import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('@/axios', () => ({ ocs: { post } }))

import { abortSession, closeSession } from './shopping'

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

describe('abortSession', () => {
  beforeEach(() => {
    post.mockReset()
  })

  it('reports a cancelled trip', async () => {
    post.mockResolvedValue({ status: 200, data: { id: 5 } })

    const result = await abortSession(1, 5)

    expect(post).toHaveBeenCalledWith('/houses/1/shopping/sessions/5/abort', {}, expect.any(Object))
    expect(result.status).toBe('aborted')
  })

  it('reports a trip that had already ended', async () => {
    post.mockResolvedValue({ status: 409, data: { id: 5 } })

    expect((await abortSession(1, 5)).status).toBe('conflict')
  })
})
