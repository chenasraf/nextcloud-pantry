import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({ getFilePickerBuilder: vi.fn() }))
vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))
vi.mock('@icons/Logout.vue', () => createIconMock('LogoutIcon'))
vi.mock('@/api/houses', () => ({ leaveHouse: vi.fn().mockResolvedValue(undefined) }))
vi.mock('@/api/prefs', () => ({
  getImageFolder: vi.fn(),
  setImageFolder: vi.fn(),
  getNotificationPrefs: vi.fn(),
  setNotificationPrefs: vi.fn(),
  getRowClickAction: vi.fn().mockResolvedValue('none'),
  setRowClickAction: vi.fn().mockResolvedValue('none'),
  getReuseExistingItems: vi.fn().mockResolvedValue('ask'),
  setReuseExistingItems: vi.fn().mockResolvedValue('ask'),
  getSuggestArchivedItems: vi.fn().mockResolvedValue(false),
  setSuggestArchivedItems: vi.fn().mockResolvedValue(false),
  getHousePrefs: vi.fn().mockResolvedValue({ showAddedBy: false }),
  setHousePrefs: vi.fn().mockResolvedValue({ showAddedBy: false }),
}))

// Mock Nextcloud Vue components that pull in CSS
vi.mock('@nextcloud/vue/components/NcAppSettingsDialog', () => ({
  default: {
    name: 'NcAppSettingsDialog',
    template: '<div class="nc-app-settings-dialog"><slot /></div>',
    props: ['open', 'name', 'showNavigation'],
  },
}))
vi.mock('@nextcloud/vue/components/NcAppSettingsSection', () => ({
  default: {
    name: 'NcAppSettingsSection',
    template: '<div class="nc-app-settings-section" :id="id"><slot /></div>',
    props: ['id', 'name'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'placeholder'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button class="nc-button"><slot /><slot name="icon" /></button>',
    props: ['variant', 'disabled', 'type'],
  },
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template: '<div class="nc-select"></div>',
    props: ['modelValue', 'options', 'clearable', 'searchable', 'inputLabel'],
    emits: ['update:modelValue'],
  },
}))

import {
  getImageFolder,
  setImageFolder,
  getNotificationPrefs,
  setNotificationPrefs,
} from '@/api/prefs'
import { leaveHouse } from '@/api/houses'
import SettingsDialog from './SettingsDialog.vue'

const NcAppSettingsDialogStub = {
  template: '<div class="nc-app-settings-dialog"><slot /></div>',
  props: ['open', 'name', 'showNavigation'],
}

const NcAppSettingsSectionStub = {
  template: '<div class="nc-app-settings-section" :id="id"><slot /></div>',
  props: ['id', 'name'],
}

const NcTextFieldStub = {
  template:
    '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  props: ['modelValue', 'label', 'placeholder'],
  emits: ['update:modelValue'],
}

const NcButtonStub = {
  template:
    '<button class="nc-button" :type="type" :disabled="disabled"><slot /><slot name="icon" /></button>',
  props: ['type', 'variant', 'disabled'],
}

function mountComponent(
  props: { open: boolean; houseId: number | null; isOwner?: boolean } = {
    open: true,
    houseId: 1,
    isOwner: false,
  },
) {
  return mount(SettingsDialog, {
    props: { isOwner: false, ...props },
    global: {
      stubs: {
        NcAppSettingsDialog: NcAppSettingsDialogStub,
        NcAppSettingsSection: NcAppSettingsSectionStub,
        NcTextField: NcTextFieldStub,
        NcButton: NcButtonStub,
      },
    },
  })
}

describe('SettingsDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(getImageFolder).mockResolvedValue('/Pantry')
    vi.mocked(setImageFolder).mockResolvedValue('/Pantry')
    vi.mocked(getNotificationPrefs).mockResolvedValue({
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
      notifyItemAdd: true,
      notifyItemRecur: true,
      notifyItemDone: true,
    })
    vi.mocked(setNotificationPrefs).mockResolvedValue({
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
      notifyItemAdd: true,
      notifyItemRecur: true,
      notifyItemDone: true,
    })
  })

  describe('rendering', () => {
    it('renders when open=true', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      expect(wrapper.find('.nc-app-settings-dialog').exists()).toBe(true)
    })

    it('shows the "Personal settings" title', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const dialog = wrapper.findComponent(NcAppSettingsDialogStub)
      expect(dialog.props('name')).toBe('Personal settings')
    })

    it('has Interface, Notifications and Files sections', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const ids = wrapper.findAll('.nc-app-settings-section').map((s) => s.attributes('id'))
      expect(ids).toContain('pantry-interface')
      expect(ids).toContain('pantry-notifications')
      expect(ids).toContain('pantry-files')
    })

    it('shows the upload folder text input', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const textField = wrapper.findComponent(NcTextFieldStub)
      expect(textField.exists()).toBe(true)
      expect(textField.props('label')).toBe('Upload folder')
    })

    it('has no Save button — the folder auto-saves', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const buttons = wrapper.findAllComponents(NcButtonStub)
      const saveButton = buttons.find((b) => b.props('type') === 'submit')
      expect(saveButton).toBeUndefined()
    })
  })

  describe('leave house', () => {
    it('shows a Leave section for non-owners', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1, isOwner: false })
      await flushPromises()

      const ids = wrapper.findAll('.nc-app-settings-section').map((s) => s.attributes('id'))
      expect(ids).toContain('pantry-leave')
    })

    it('hides the Leave section for the owner', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1, isOwner: true })
      await flushPromises()

      const ids = wrapper.findAll('.nc-app-settings-section').map((s) => s.attributes('id'))
      expect(ids).not.toContain('pantry-leave')
    })

    it('leaves the house and emits left', async () => {
      const wrapper = mountComponent({ open: true, houseId: 9, isOwner: false })
      await flushPromises()

      const leaveSection = wrapper.find('#pantry-leave')
      await leaveSection.find('button').trigger('click')
      await flushPromises()

      expect(leaveHouse).toHaveBeenCalledWith(9)
      expect(wrapper.emitted('left')).toBeTruthy()
    })
  })

  describe('image folder', () => {
    it('loads the folder from API when opened', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('/Photos')
      const wrapper = mountComponent({ open: true, houseId: 42 })
      await flushPromises()

      expect(getImageFolder).toHaveBeenCalledWith(42)
      const textField = wrapper.findComponent(NcTextFieldStub)
      expect(textField.props('modelValue')).toBe('/Photos')
    })

    it('saves the folder on blur when it changed', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('/Pantry')
      vi.mocked(setImageFolder).mockResolvedValue('/MyFolder')

      const wrapper = mountComponent({ open: true, houseId: 5 })
      await flushPromises()

      const input = wrapper.find('#pantry-files input.nc-text-field')
      await input.setValue('/MyFolder')
      await input.trigger('blur')
      await flushPromises()

      expect(setImageFolder).toHaveBeenCalledWith(5, '/MyFolder')
    })

    it('does not save on blur when the folder is unchanged', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('/Pantry')
      const wrapper = mountComponent({ open: true, houseId: 5 })
      await flushPromises()

      const input = wrapper.find('#pantry-files input.nc-text-field')
      await input.trigger('blur')
      await flushPromises()

      expect(setImageFolder).not.toHaveBeenCalled()
    })
  })

  describe('notification preferences', () => {
    it('loads notification prefs on open', async () => {
      mountComponent({ open: true, houseId: 7 })
      await flushPromises()

      expect(getNotificationPrefs).toHaveBeenCalledWith(7)
    })

    it('renders six notification checkboxes', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const notifSection = wrapper.find('#pantry-notifications')
      const checkboxes = notifSection.findAll('.nc-checkbox')
      expect(checkboxes).toHaveLength(6)
    })

    it('calls setNotificationPrefs when a checkbox is toggled', async () => {
      vi.mocked(setNotificationPrefs).mockResolvedValue({
        notifyPhoto: false,
        notifyNoteCreate: true,
        notifyNoteEdit: true,
        notifyItemAdd: true,
        notifyItemRecur: true,
        notifyItemDone: true,
      })

      const wrapper = mountComponent({ open: true, houseId: 3 })
      await flushPromises()

      const notifSection = wrapper.find('#pantry-notifications')
      const checkbox = notifSection.find('.nc-checkbox input')
      await checkbox.setValue(false)
      await flushPromises()

      expect(setNotificationPrefs).toHaveBeenCalledWith(3, { notifyPhoto: false })
    })
  })
})
