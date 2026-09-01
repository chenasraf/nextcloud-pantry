import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import type { House } from '@/api/types'

import HouseManageView from './HouseManageView.vue'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

const { FULL_CAPS, updateSpy, removeSpy, refreshSpy, routerReplace, routerPush } = vi.hoisted(
  () => ({
    FULL_CAPS: {
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
    },
    updateSpy: vi.fn(() => Promise.resolve()),
    removeSpy: vi.fn(() => Promise.resolve()),
    refreshSpy: vi.fn(() => Promise.resolve()),
    routerReplace: vi.fn(() => Promise.resolve()),
    routerPush: vi.fn(() => Promise.resolve()),
  }),
)

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPush, replace: routerReplace }),
  useRoute: () => ({ params: { houseId: '1' } }),
  onBeforeRouteLeave: vi.fn(),
}))

// NcDateTimePickerNative eagerly imports a .css asset Node's ESM loader rejects.
vi.mock('@nextcloud/vue/components/NcDateTimePickerNative', () => ({
  default: defineComponent({
    props: ['modelValue', 'type', 'label'],
    emits: ['update:modelValue'],
    template: '<input class="mock-datetime-picker" />',
  }),
}))

// @nextcloud/vue components eagerly import .css assets that Node's ESM loader
// rejects, so mock each one used here (same pattern as SettingsDialog.test).
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: defineComponent({
    props: ['type', 'variant', 'disabled'],
    template:
      '<button class="nc-button" :type="type" :disabled="disabled"><slot /><slot name="icon" /></button>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: defineComponent({
    props: ['modelValue', 'label', 'placeholder', 'type', 'min', 'max'],
    emits: ['update:modelValue'],
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: defineComponent({
    props: ['modelValue', 'disabled'],
    template: '<label><slot /></label>',
  }),
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: defineComponent({ template: '<span />' }),
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: defineComponent({ template: '<div><slot /><slot name="actions" /></div>' }),
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: defineComponent({
    props: ['modelValue', 'options'],
    template: '<div class="nc-select" />',
  }),
}))
vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
  default: defineComponent({ props: ['timestamp'], template: '<time />' }),
}))
vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: defineComponent({ props: ['user', 'size'], template: '<span />' }),
}))

vi.mock('@/components/PageToolbar', () => ({
  default: defineComponent({
    props: ['title'],
    template: '<header><h2>{{ title }}</h2><slot name="actions" /></header>',
  }),
}))
vi.mock('@/components/ShoppingReminders', () => ({
  ShoppingRemindersDialog: defineComponent({ template: '<div />' }),
}))
vi.mock('@/components/HouseMembersDialog', () => ({
  default: defineComponent({ template: '<div />' }),
}))
vi.mock('@/components/HouseRolesDialog', () => ({
  default: defineComponent({ template: '<div />' }),
}))

vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/ContentCopy.vue', () => createIconMock('ContentCopyIcon'))
vi.mock('@icons/BellRing.vue', () => createIconMock('BellRingIcon'))
vi.mock('@icons/AccountMultiple.vue', () => createIconMock('AccountMultipleIcon'))
vi.mock('@icons/ShieldAccount.vue', () => createIconMock('ShieldAccountIcon'))

vi.mock('@/composables/useHouses', () => ({
  useHouses: vi.fn(() => ({ update: updateSpy, remove: removeSpy })),
}))
vi.mock('@/composables/useRoles', () => ({
  useRoles: vi.fn(() => ({
    roles: ref([]),
    refresh: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  })),
}))
vi.mock('@/api/roles', () => ({
  setMemberRoles: vi.fn(() => Promise.resolve()),
  updateRole: vi.fn(() => Promise.resolve()),
}))
vi.mock('@/api/houses', () => ({
  listMembers: vi.fn(() => Promise.resolve([])),
  addMember: vi.fn(),
  removeMember: vi.fn(),
  searchUsers: vi.fn(() => Promise.resolve([])),
}))

vi.mock('@/composables/useCurrentHouse', () => ({
  NO_CAPS: FULL_CAPS,
  useCurrentHouse: vi.fn(),
}))

function makeHouse(overrides: Partial<House> = {}): House {
  return {
    id: 1,
    name: 'Test House',
    description: 'A test description',
    role: 'owner',
    ownerUid: 'me',
    createdAt: 0,
    updatedAt: 0,
    trashRetentionDays: 30,
    recurrenceTime: 480,
    fieldReminderTime: 480,
    isAdmin: true,
    permissions: FULL_CAPS,
    ...overrides,
  } as House
}

function setCurrentHouse(
  house: House | null,
  flags: { canAdmin?: boolean; isOwner?: boolean } = {},
) {
  vi.mocked(useCurrentHouse).mockReturnValue({
    house: ref(house),
    houseId: computed(() => house?.id ?? null),
    loading: ref(false),
    canEdit: computed(() => true),
    canAdmin: computed(() => flags.canAdmin ?? true),
    isAdmin: computed(() => flags.canAdmin ?? true),
    isOwner: computed(() => flags.isOwner ?? true),
    can: computed(() => FULL_CAPS),
    refresh: refreshSpy,
  })
}

function mountView() {
  return mount(HouseManageView)
}

describe('HouseManageView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setCurrentHouse(makeHouse())
  })

  it('renders the "Manage house" title and core sections', async () => {
    const wrapper = mountView()
    await flushPromises()

    const text = wrapper.text()
    expect(text).toContain('Manage house')
    expect(text).toContain('General')
    expect(text).toContain('Members & roles')
    expect(text).toContain('Manage members')
    expect(text).toContain('Manage roles')
  })

  it('disables Save while the config is pristine', async () => {
    const wrapper = mountView()
    await flushPromises()

    const save = wrapper.findAll('button.nc-button').find((b) => b.text().includes('Save changes'))
    expect(save).toBeDefined()
    expect(save!.attributes('disabled')).toBeDefined()
  })

  it('enables Save after an edit and commits all config fields at once', async () => {
    const wrapper = mountView()
    await flushPromises()

    // First text field is the house name.
    const nameField = wrapper.findAll('input.nc-text-field')[0]
    await nameField!.setValue('Renamed House')
    await flushPromises()

    const save = wrapper.findAll('button.nc-button').find((b) => b.text().includes('Save changes'))
    expect(save!.attributes('disabled')).toBeUndefined()

    await save!.trigger('click')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledWith(1, {
      name: 'Renamed House',
      description: 'A test description',
      trashRetentionDays: 30,
      recurrenceTime: 480,
      fieldReminderTime: 480,
    })
    expect(refreshSpy).toHaveBeenCalled()
  })

  it('redirects non-admins away from the page', async () => {
    setCurrentHouse(makeHouse({ role: 'member', isAdmin: false }), {
      canAdmin: false,
      isOwner: false,
    })
    mountView()
    await flushPromises()

    expect(routerReplace).toHaveBeenCalledWith({ name: 'lists', params: { houseId: '1' } })
  })

  it('shows the danger zone only for the owner', async () => {
    const owner = mountView()
    await flushPromises()
    expect(owner.text()).toContain('Danger zone')

    setCurrentHouse(makeHouse({ role: 'admin', ownerUid: 'other' }), {
      canAdmin: true,
      isOwner: false,
    })
    const admin = mountView()
    await flushPromises()
    expect(admin.text()).not.toContain('Danger zone')
  })
})
