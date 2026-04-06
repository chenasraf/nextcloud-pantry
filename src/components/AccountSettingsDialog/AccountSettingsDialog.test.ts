import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/dialogs', () => ({ getFilePickerBuilder: vi.fn() }))
vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))
vi.mock('@/api/prefs', () => ({
  getImageFolder: vi.fn(),
  setImageFolder: vi.fn(),
  getNotificationPrefs: vi.fn(),
  setNotificationPrefs: vi.fn(),
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
    template: '<div class="nc-app-settings-section"><slot /></div>',
    props: ['id', 'name'],
  },
}))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template: '<input class="nc-text-field" />',
    props: ['modelValue', 'label', 'placeholder'],
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

import {
  getImageFolder,
  setImageFolder,
  getNotificationPrefs,
  setNotificationPrefs,
} from '@/api/prefs'
import AccountSettingsDialog from './AccountSettingsDialog.vue'

const NcAppSettingsDialogStub = {
  template: '<div class="nc-app-settings-dialog"><slot /></div>',
  props: ['open', 'name', 'showNavigation'],
}

const NcAppSettingsSectionStub = {
  template: '<div class="nc-app-settings-section"><slot /></div>',
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
  props: { open: boolean; houseId: number | null } = { open: true, houseId: 1 },
) {
  return mount(AccountSettingsDialog, {
    props,
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

describe('AccountSettingsDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(getImageFolder).mockResolvedValue('/Pantry')
    vi.mocked(setImageFolder).mockResolvedValue('/Pantry')
    vi.mocked(getNotificationPrefs).mockResolvedValue({
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
    })
    vi.mocked(setNotificationPrefs).mockResolvedValue({
      notifyPhoto: true,
      notifyNoteCreate: true,
      notifyNoteEdit: true,
    })
  })

  describe('rendering', () => {
    it('renders when open=true', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      expect(wrapper.find('.nc-app-settings-dialog').exists()).toBe(true)
    })

    it('shows the "Account settings" title', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const dialog = wrapper.findComponent(NcAppSettingsDialogStub)
      expect(dialog.props('name')).toBe('Account settings')
    })

    it('has an "Images" section', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const section = wrapper.findComponent(NcAppSettingsSectionStub)
      expect(section.props('name')).toBe('Images')
    })

    it('shows the upload folder text input', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const textField = wrapper.findComponent(NcTextFieldStub)
      expect(textField.exists()).toBe(true)
      expect(textField.props('label')).toBe('Upload folder')
    })

    it('shows a Browse button', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const buttons = wrapper.findAllComponents(NcButtonStub)
      const browseButton = buttons.find((b) => b.text().includes('Browse'))
      expect(browseButton).toBeDefined()
    })

    it('shows a Save button', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const buttons = wrapper.findAllComponents(NcButtonStub)
      const saveButton = buttons.find((b) => b.text().includes('Save'))
      expect(saveButton).toBeDefined()
    })
  })

  describe('save button disabled state', () => {
    it('save button is disabled when folder is empty', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('')
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const buttons = wrapper.findAllComponents(NcButtonStub)
      const saveButton = buttons.find((b) => b.props('type') === 'submit')
      expect(saveButton!.props('disabled')).toBe(true)
    })
  })

  describe('API interactions', () => {
    it('loads the folder from API when opened', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('/Photos')
      const wrapper = mountComponent({ open: true, houseId: 42 })
      await flushPromises()

      expect(getImageFolder).toHaveBeenCalledWith(42)
      const textField = wrapper.findComponent(NcTextFieldStub)
      expect(textField.props('modelValue')).toBe('/Photos')
    })

    it('calls setImageFolder API when saving', async () => {
      vi.mocked(getImageFolder).mockResolvedValue('/MyFolder')
      vi.mocked(setImageFolder).mockResolvedValue('/MyFolder')

      const wrapper = mountComponent({ open: true, houseId: 5 })
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(setImageFolder).toHaveBeenCalledWith(5, '/MyFolder')
    })
  })

  describe('notification preferences', () => {
    it('has a Notifications section', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const sections = wrapper.findAll('.nc-app-settings-section')
      expect(sections.length).toBeGreaterThanOrEqual(2)
    })

    it('loads notification prefs on open', async () => {
      mountComponent({ open: true, houseId: 7 })
      await flushPromises()

      expect(getNotificationPrefs).toHaveBeenCalledWith(7)
    })

    it('renders three notification checkboxes', async () => {
      const wrapper = mountComponent({ open: true, houseId: 1 })
      await flushPromises()

      const checkboxes = wrapper.findAll('.nc-checkbox')
      expect(checkboxes).toHaveLength(3)
    })

    it('calls setNotificationPrefs when a checkbox is toggled', async () => {
      vi.mocked(setNotificationPrefs).mockResolvedValue({
        notifyPhoto: false,
        notifyNoteCreate: true,
        notifyNoteEdit: true,
      })

      const wrapper = mountComponent({ open: true, houseId: 3 })
      await flushPromises()

      const checkbox = wrapper.find('.nc-checkbox input')
      await checkbox.setValue(false)
      await flushPromises()

      expect(setNotificationPrefs).toHaveBeenCalledWith(3, { notifyPhoto: false })
    })
  })
})
