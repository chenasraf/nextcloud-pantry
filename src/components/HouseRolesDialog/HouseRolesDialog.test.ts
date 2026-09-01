import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import type { Capabilities, Role } from '@/api/types'

import HouseRolesDialog from './HouseRolesDialog.vue'

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
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: defineComponent({
    props: ['modelValue', 'label', 'disabled'],
    emits: ['update:modelValue'],
    template:
      '<input class="nc-text-field" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: defineComponent({
    props: ['modelValue', 'disabled'],
    template: '<label><slot /></label>',
  }),
}))
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/Undo.vue', () => createIconMock('UndoIcon'))
vi.mock('@icons/ContentCopy.vue', () => createIconMock('ContentCopyIcon'))
vi.mock('@icons/ChevronDown.vue', () => createIconMock('ChevronDownIcon'))
vi.mock('@icons/ChevronUp.vue', () => createIconMock('ChevronUpIcon'))

const { rolesRef, createSpy, updateSpy, removeSpy, refreshSpy } = vi.hoisted(() => ({
  rolesRef: { value: [] as Role[] },
  createSpy: vi.fn(() => Promise.resolve()),
  updateSpy: vi.fn(() => Promise.resolve()),
  removeSpy: vi.fn(() => Promise.resolve()),
  refreshSpy: vi.fn(() => Promise.resolve()),
}))

vi.mock('@/composables/useRoles', () => ({
  useRoles: vi.fn(() => ({
    roles: rolesRef,
    create: createSpy,
    update: updateSpy,
    remove: removeSpy,
    refresh: refreshSpy,
  })),
}))

const ALL_CAPS: Capabilities = {
  canViewLists: true,
  canCreateLists: true,
  canEditLists: true,
  canDeleteLists: true,
  canAddItems: true,
  canDeleteItems: true,
  canCopyItems: true,
  canMoveItems: true,
  canCheckItems: true,
  canViewPhotos: true,
  canUploadPhotos: true,
  canUpdatePhotos: true,
  canDeletePhotos: true,
  canMovePhotos: true,
  canViewNotes: true,
  canCreateNotes: true,
  canUpdateNotes: true,
  canDeleteNotes: true,
  canEditFields: true,
}

function makeRole(id: number, name: string, roleType: Role['roleType']): Role {
  return { id, name, roleType, ...ALL_CAPS } as Role
}

function mountDialog() {
  return mount(HouseRolesDialog, { props: { open: true, houseId: 1 } })
}
function findButton(wrapper: ReturnType<typeof mountDialog>, text: string) {
  return wrapper.findAll('button.nc-button').find((b) => b.text().includes(text))
}
function findByLabel(wrapper: ReturnType<typeof mountDialog>, label: string) {
  return wrapper.findAll('button.nc-button').filter((b) => b.attributes('aria-label') === label)
}

describe('HouseRolesDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    rolesRef.value = [
      makeRole(1, 'Admin', 'admin'),
      makeRole(2, 'Member', 'default'),
      makeRole(3, 'Cook', 'normal'),
    ]
  })

  it('renders the roles, presets first', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    // Role names live in the name inputs; presets (admin, default) sort first.
    const names = wrapper
      .findAll('input.nc-text-field')
      .map((i) => (i.element as HTMLInputElement).value)
    expect(names).toEqual(['Admin', 'Member', 'Cook'])
  })

  it('keeps the capability grid behind Customize', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    // Collapsed by default — no capability checkboxes shown yet.
    expect(wrapper.findAll('label').length).toBe(0)
    // Each editable (non-admin) role offers a Customize expander.
    expect(wrapper.findAll('.pantry-expander')).toHaveLength(2)
  })

  it('disables Save until something is staged', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    expect(findButton(wrapper, 'Save changes')!.attributes('disabled')).toBeDefined()
  })

  it('stages a rename and commits it on Save', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    // Order is admin, member (default), then the custom role.
    const cookField = wrapper.findAll('input.nc-text-field')[2]
    await cookField!.setValue('Chef')
    await flushPromises()

    expect(updateSpy).not.toHaveBeenCalled()
    expect(findButton(wrapper, 'Save changes')!.attributes('disabled')).toBeUndefined()

    await findButton(wrapper, 'Save changes')!.trigger('click')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledWith(3, { name: 'Chef' })
    expect(wrapper.emitted('changed')).toBeTruthy()
  })

  it('stages a deletion without removing until Save', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    // Only the custom role is deletable.
    const del = findByLabel(wrapper, 'Delete role')
    expect(del).toHaveLength(1)
    await del[0]!.trigger('click')
    await flushPromises()

    expect(removeSpy).not.toHaveBeenCalled()

    await findButton(wrapper, 'Save changes')!.trigger('click')
    await flushPromises()

    expect(removeSpy).toHaveBeenCalledWith(3)
  })

  it('stages an added role and creates it on Save', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    await findButton(wrapper, 'Add role')!.trigger('click')
    await flushPromises()

    expect(createSpy).not.toHaveBeenCalled()

    await findButton(wrapper, 'Save changes')!.trigger('click')
    await flushPromises()

    expect(createSpy).toHaveBeenCalledTimes(1)
  })

  it('does not touch role APIs on load', async () => {
    mountDialog()
    await flushPromises()

    expect(createSpy).not.toHaveBeenCalled()
    expect(updateSpy).not.toHaveBeenCalled()
    expect(removeSpy).not.toHaveBeenCalled()
  })
})
