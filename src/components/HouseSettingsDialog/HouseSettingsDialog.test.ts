import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, ref } from 'vue'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

import HouseSettingsDialog from './HouseSettingsDialog.vue'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)

// Mock icon components
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ params: { houseId: '1' } }),
}))

// Mock composables
vi.mock('@/composables/useCurrentHouse', () => ({
  useCurrentHouse: vi.fn(() => ({
    house: ref({
      id: 1,
      name: 'Test House',
      description: 'A test description',
      role: 'owner',
      ownerUid: 'me',
      createdAt: 0,
      updatedAt: 0,
    }),
    houseId: computed(() => 1),
    loading: ref(false),
    canEdit: computed(() => true),
    canAdmin: computed(() => true),
    isOwner: computed(() => true),
    refresh: vi.fn(),
  })),
}))

vi.mock('@/composables/useHouses', () => ({
  useHouses: vi.fn(() => ({
    update: vi.fn(),
    remove: vi.fn(),
  })),
}))

vi.mock('@/api/houses', () => ({
  listMembers: vi.fn(() => Promise.resolve([])),
  addMember: vi.fn(),
  updateMemberRole: vi.fn(),
  removeMember: vi.fn(),
  leaveHouse: vi.fn(),
  searchUsers: vi.fn(() => Promise.resolve([])),
}))

// Mock Nextcloud Vue components that pull in CSS
vi.mock('@nextcloud/vue/components/NcAppSettingsDialog', () => ({
  default: {
    name: 'NcAppSettingsDialog',
    template: '<div><slot /></div>',
    props: { open: Boolean, name: String, showNavigation: Boolean },
  },
}))
vi.mock('@nextcloud/vue/components/NcAppSettingsSection', () => ({
  default: {
    name: 'NcAppSettingsSection',
    template: '<div><slot /></div>',
    props: { id: String, name: String },
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button><slot /><slot name="icon" /></button>',
    props: { variant: String, disabled: Boolean, type: String, ariaLabel: String },
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template: '<input />',
    props: { modelValue: String, label: String, placeholder: String },
  },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: { name: 'NcLoadingIcon', template: '<span />', props: { size: Number } },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div><slot /><slot name="actions" /></div>',
    props: { name: String, open: Boolean },
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<div />',
    props: { modelValue: [String, Object], options: Array, inputLabel: String },
  },
}))
vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
  default: { name: 'NcDateTime', template: '<span />', props: { timestamp: Number } },
}))
vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: {
    name: 'NcAvatar',
    template: '<span />',
    props: { user: String, size: Number, showUserStatus: Boolean },
  },
}))

// Stub for Nextcloud Vue components
function createStub(name: string, opts?: { slots?: boolean; props?: string[] }) {
  return defineComponent({
    name,
    props: opts?.props ?? {
      name: String,
      open: Boolean,
      showNavigation: Boolean,
      label: String,
      placeholder: String,
      disabled: Boolean,
      variant: String,
      type: String,
      ariaLabel: String,
      options: Array,
      inputLabel: String,
      timestamp: Number,
      modelValue: [String, Object, Number, Boolean],
    },
    emits: ['update:open', 'update:modelValue'],
    template:
      opts?.slots === false
        ? `<div class="stub-${name}"><slot /></div>`
        : `<div class="stub-${name}"><slot /><slot name="icon" /><slot name="actions" /></div>`,
  })
}

const stubs = {
  NcAppSettingsDialog: createStub('NcAppSettingsDialog'),
  NcAppSettingsSection: createStub('NcAppSettingsSection'),
  NcButton: createStub('NcButton'),
  NcTextField: createStub('NcTextField', { slots: false }),
  NcLoadingIcon: createStub('NcLoadingIcon', { slots: false }),
  NcDialog: createStub('NcDialog'),
  NcSelect: createStub('NcSelect', { slots: false }),
  NcDateTime: createStub('NcDateTime', { slots: false }),
  NcAvatar: createStub('NcAvatar', { slots: false }),
}

function mountDialog(props: { open: boolean } = { open: true }) {
  return mount(HouseSettingsDialog, {
    props,
    global: { stubs },
  })
}

describe('HouseSettingsDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    // Reset to owner mock by default
    vi.mocked(useCurrentHouse).mockReturnValue({
      house: ref({
        id: 1,
        name: 'Test House',
        description: 'A test description',
        role: 'owner',
        ownerUid: 'me',
        createdAt: 0,
        updatedAt: 0,
      }),
      houseId: computed(() => 1),
      loading: ref(false),
      canEdit: computed(() => true),
      canAdmin: computed(() => true),
      isOwner: computed(() => true),
      refresh: vi.fn(),
    })
  })

  describe('rendering', () => {
    it('renders when open is true with the house settings title', () => {
      const wrapper = mountDialog({ open: true })
      expect(wrapper.exists()).toBe(true)

      const dialog = wrapper.findComponent({ name: 'NcAppSettingsDialog' })
      expect(dialog.exists()).toBe(true)
      expect(dialog.props('name')).toBe('House settings')
      expect(dialog.props('open')).toBe(true)
    })

    it('passes open=false to the dialog when prop is false', () => {
      const wrapper = mountDialog({ open: false })
      const dialog = wrapper.findComponent({ name: 'NcAppSettingsDialog' })
      expect(dialog.props('open')).toBe(false)
    })
  })

  describe('general section', () => {
    it('shows name and description inputs', () => {
      const wrapper = mountDialog()
      const sections = wrapper.findAllComponents({ name: 'NcAppSettingsSection' })
      const generalSection = sections.find((s) => s.props('name') === 'General')

      expect(generalSection).toBeDefined()

      const textFields = wrapper.findAllComponents({ name: 'NcTextField' })
      const nameField = textFields.find((f) => f.props('label') === 'Name')
      const descField = textFields.find((f) => f.props('label') === 'Description')

      expect(nameField).toBeDefined()
      expect(descField).toBeDefined()
    })

    it('shows a save button', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Save changes')
    })
  })

  describe('members section', () => {
    it('renders member table headers', async () => {
      const { listMembers } = await import('@/api/houses')
      vi.mocked(listMembers).mockResolvedValueOnce([
        { id: 1, houseId: 1, userId: 'alice', displayName: 'Alice', role: 'owner', joinedAt: 1000 },
      ])
      const wrapper = mountDialog({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      const text = wrapper.text()
      expect(text).toContain('Account')
      expect(text).toContain('Role')
      expect(text).toContain('Joined')
    })

    it('has add member button when user can admin', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Add member')
    })
  })

  describe('owner view', () => {
    it('shows danger zone section', () => {
      const wrapper = mountDialog()
      const sections = wrapper.findAllComponents({ name: 'NcAppSettingsSection' })
      const dangerSection = sections.find((s) => s.props('name') === 'Danger zone')

      expect(dangerSection).toBeDefined()
    })

    it('shows delete house button in danger zone', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Delete house')
    })

    it('does not show leave button for owner', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).not.toContain('Leave this house')
    })
  })

  describe('non-owner view', () => {
    beforeEach(() => {
      vi.mocked(useCurrentHouse).mockReturnValue({
        house: ref({
          id: 1,
          name: 'Test',
          description: null,
          role: 'member',
          ownerUid: 'other',
          createdAt: 0,
          updatedAt: 0,
        }),
        houseId: computed(() => 1),
        loading: ref(false),
        canEdit: computed(() => true),
        canAdmin: computed(() => false),
        isOwner: computed(() => false),
        refresh: vi.fn(),
      })
    })

    it('hides danger zone when user is not owner', () => {
      const wrapper = mountDialog()
      const sections = wrapper.findAllComponents({ name: 'NcAppSettingsSection' })
      const dangerSection = sections.find((s) => s.props('name') === 'Danger zone')

      expect(dangerSection).toBeUndefined()
    })

    it('does not show delete house button', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).not.toContain('Delete house')
    })

    it('shows leave this house button when not owner', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Leave this house')
    })

    it('does not show add member button when user cannot admin', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).not.toContain('Add member')
    })
  })

  describe('non-owner admin view', () => {
    beforeEach(() => {
      vi.mocked(useCurrentHouse).mockReturnValue({
        house: ref({
          id: 1,
          name: 'Test',
          description: null,
          role: 'admin',
          ownerUid: 'other',
          createdAt: 0,
          updatedAt: 0,
        }),
        houseId: computed(() => 1),
        loading: ref(false),
        canEdit: computed(() => true),
        canAdmin: computed(() => true),
        isOwner: computed(() => false),
        refresh: vi.fn(),
      })
    })

    it('shows add member button for admin', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Add member')
    })

    it('shows leave button for admin who is not owner', () => {
      const wrapper = mountDialog()
      expect(wrapper.text()).toContain('Leave this house')
    })

    it('hides danger zone for admin who is not owner', () => {
      const wrapper = mountDialog()
      const sections = wrapper.findAllComponents({ name: 'NcAppSettingsSection' })
      const dangerSection = sections.find((s) => s.props('name') === 'Danger zone')

      expect(dangerSection).toBeUndefined()
    })
  })
})
