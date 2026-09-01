import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { addMember, listMembers, removeMember } from '@/api/houses'
import { setMemberRoles } from '@/api/roles'
import type { HouseMember } from '@/api/types'

import HouseMembersDialog from './HouseMembersDialog.vue'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    props: ['type', 'variant', 'disabled', 'ariaLabel', 'title'],
    template:
      '<button class="nc-button" :disabled="disabled" :aria-label="ariaLabel"><slot /><slot name="icon" /></button>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: defineComponent({
    template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: defineComponent({
    props: ['modelValue', 'options'],
    template: '<div class="nc-select" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: defineComponent({ props: ['user', 'size'], template: '<span />' }),
}))
vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
  default: defineComponent({ props: ['timestamp'], template: '<time />' }),
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: defineComponent({ template: '<span />' }),
}))
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Undo.vue', () => createIconMock('UndoIcon'))

vi.mock('@/composables/useRoles', () => ({
  useRoles: vi.fn(() => ({
    roles: ref([
      { id: 10, name: 'Member' },
      { id: 11, name: 'Cook' },
    ]),
    refresh: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  })),
}))

vi.mock('@/api/houses', () => ({
  listMembers: vi.fn(),
  addMember: vi.fn(() => Promise.resolve()),
  removeMember: vi.fn(() => Promise.resolve()),
  searchUsers: vi.fn(() => Promise.resolve([])),
}))
vi.mock('@/api/roles', () => ({
  setMemberRoles: vi.fn(() => Promise.resolve()),
}))

const OWNER: HouseMember = {
  id: 1,
  houseId: 1,
  userId: 'owner',
  displayName: 'Olivia Owner',
  role: 'owner',
  roleIds: [],
  joinedAt: 0,
}
const BOB: HouseMember = {
  id: 2,
  houseId: 1,
  userId: 'bob',
  displayName: 'Bob Member',
  role: 'member',
  roleIds: [10],
  joinedAt: 100,
}

function mountDialog() {
  return mount(HouseMembersDialog, { props: { open: true, houseId: 1 } })
}

function findButton(wrapper: ReturnType<typeof mountDialog>, text: string) {
  return wrapper.findAll('button.nc-button').find((b) => b.text().includes(text))
}
function findByLabel(wrapper: ReturnType<typeof mountDialog>, label: string) {
  return wrapper.findAll('button.nc-button').filter((b) => b.attributes('aria-label') === label)
}

describe('HouseMembersDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(listMembers).mockResolvedValue([OWNER, BOB])
  })

  it('renders member rows from the server', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    expect(wrapper.text()).toContain('Olivia Owner')
    expect(wrapper.text()).toContain('Bob Member')
  })

  it('offers a remove control only for non-owner members', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    // Only Bob is removable; the owner row has no remove button.
    expect(findByLabel(wrapper, 'Remove member')).toHaveLength(1)
  })

  it('disables Save until something is staged', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    expect(findButton(wrapper, 'Save changes')!.attributes('disabled')).toBeDefined()
  })

  it('stages a removal without calling the API until Save', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    await findByLabel(wrapper, 'Remove member')[0]!.trigger('click')
    await flushPromises()

    // Nothing committed yet.
    expect(removeMember).not.toHaveBeenCalled()
    // But Save is now enabled.
    expect(findButton(wrapper, 'Save changes')!.attributes('disabled')).toBeUndefined()

    await findButton(wrapper, 'Save changes')!.trigger('click')
    await flushPromises()

    expect(removeMember).toHaveBeenCalledTimes(1)
    expect(removeMember).toHaveBeenCalledWith(1, 2)
    expect(wrapper.emitted('changed')).toBeTruthy()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('confirms before closing with unsaved changes', async () => {
    const confirmSpy = vi.fn().mockReturnValue(false)
    vi.stubGlobal('confirm', confirmSpy)
    const wrapper = mountDialog()
    await flushPromises()

    await findByLabel(wrapper, 'Remove member')[0]!.trigger('click')
    await flushPromises()

    await findButton(wrapper, 'Cancel')!.trigger('click')
    expect(confirmSpy).toHaveBeenCalled()
    // Declined the confirm — dialog stays open, nothing committed.
    expect(wrapper.emitted('update:open')).toBeFalsy()
    expect(removeMember).not.toHaveBeenCalled()

    confirmSpy.mockReturnValue(true)
    await findButton(wrapper, 'Cancel')!.trigger('click')
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])

    vi.unstubAllGlobals()
  })

  it('does not touch member APIs on load', async () => {
    mountDialog()
    await flushPromises()

    expect(removeMember).not.toHaveBeenCalled()
    expect(setMemberRoles).not.toHaveBeenCalled()
    expect(addMember).not.toHaveBeenCalled()
  })
})
