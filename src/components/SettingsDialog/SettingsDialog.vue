<template>
  <NcAppSettingsDialog
    :open="open"
    :name="strings.title"
    :show-navigation="true"
    @update:open="(v) => emit('update:open', v)"
  >
    <NcAppSettingsSection id="pantry-interface" :name="strings.interfaceSection">
      <div class="settings__field">
        <label class="settings__label">{{ strings.rowClickActionLabel }}</label>
        <p class="settings__hint">{{ strings.rowClickActionHint }}</p>
        <NcSelect
          :model-value="selectedRowClickActionOption"
          :options="rowClickActionOptions"
          :clearable="false"
          :searchable="false"
          input-label=""
          @update:model-value="updateRowClickAction"
        />
      </div>
      <div class="settings__field">
        <label class="settings__label">{{ strings.reuseExistingItemsLabel }}</label>
        <p class="settings__hint">{{ strings.reuseExistingItemsHint }}</p>
        <NcSelect
          :model-value="selectedReuseExistingItemsOption"
          :options="reuseExistingItemsOptions"
          :clearable="false"
          :searchable="false"
          input-label=""
          @update:model-value="updateReuseExistingItems"
        />
      </div>
      <div class="settings__field">
        <NcCheckboxRadioSwitch
          :model-value="suggestArchivedItems"
          @update:model-value="updateSuggestArchivedItems($event)"
        >
          {{ strings.suggestArchivedItemsLabel }}
        </NcCheckboxRadioSwitch>
        <p class="settings__hint settings__hint--inline">{{ strings.suggestArchivedItemsHint }}</p>
      </div>
      <div class="settings__field">
        <NcCheckboxRadioSwitch
          :model-value="showAddedBy"
          @update:model-value="updateShowAddedBy($event)"
        >
          {{ strings.showAddedByLabel }}
        </NcCheckboxRadioSwitch>
        <p class="settings__hint settings__hint--inline">{{ strings.showAddedByHint }}</p>
      </div>
    </NcAppSettingsSection>

    <NcAppSettingsSection id="pantry-notifications" :name="strings.notificationsSection">
      <p class="settings__hint">{{ strings.notificationsHint }}</p>
      <div class="settings__checks">
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyPhoto"
          @update:model-value="updateNotifPref('notifyPhoto', $event)"
        >
          {{ strings.notifyPhoto }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyNoteCreate"
          @update:model-value="updateNotifPref('notifyNoteCreate', $event)"
        >
          {{ strings.notifyNoteCreate }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyNoteEdit"
          @update:model-value="updateNotifPref('notifyNoteEdit', $event)"
        >
          {{ strings.notifyNoteEdit }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyItemAdd"
          @update:model-value="updateNotifPref('notifyItemAdd', $event)"
        >
          {{ strings.notifyItemAdd }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyItemRecur"
          @update:model-value="updateNotifPref('notifyItemRecur', $event)"
        >
          {{ strings.notifyItemRecur }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :model-value="notifPrefs.notifyItemDone"
          @update:model-value="updateNotifPref('notifyItemDone', $event)"
        >
          {{ strings.notifyItemDone }}
        </NcCheckboxRadioSwitch>
      </div>
    </NcAppSettingsSection>

    <NcAppSettingsSection id="pantry-files" :name="strings.filesSection">
      <p class="settings__hint">{{ strings.filesHint }}</p>
      <form class="settings__form" autocomplete="off" @submit.prevent="saveFolder">
        <div class="settings__folder-row">
          <NcTextField
            v-model="folder"
            :label="strings.folderLabel"
            placeholder="/Pantry"
            autocomplete="off"
            @blur="saveFolder"
          />
          <NcButton type="button" variant="secondary" @click="browseFolder">
            <template #icon>
              <FolderIcon :size="20" />
            </template>
            {{ strings.browse }}
          </NcButton>
        </div>
        <p
          class="settings__saved"
          :class="{ 'settings__saved--show': folderSaved }"
          aria-live="polite"
        >
          {{ strings.saved }}
        </p>
      </form>
    </NcAppSettingsSection>

    <NcAppSettingsSection v-if="!isOwner" id="pantry-leave" :name="strings.leaveSection">
      <p class="settings__hint">{{ strings.leaveHint }}</p>
      <NcButton variant="secondary" :disabled="leaving" @click="leave">
        <template #icon>
          <LogoutIcon :size="20" />
        </template>
        {{ strings.leaveButton }}
      </NcButton>
    </NcAppSettingsSection>
  </NcAppSettingsDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import FolderIcon from '@icons/Folder.vue'
import LogoutIcon from '@icons/Logout.vue'
import {
  getImageFolder,
  setImageFolder,
  getNotificationPrefs,
  setNotificationPrefs,
  type NotificationPrefs,
  type ReuseExistingItems,
  type RowClickAction,
} from '@/api/prefs'
import { leaveHouse } from '@/api/houses'
import { useRowClickAction } from '@/composables/useRowClickAction'
import { useReuseExistingItems } from '@/composables/useReuseExistingItems'
import { useSuggestArchivedItems } from '@/composables/useSuggestArchivedItems'
import { useShowAddedBy } from '@/composables/useShowAddedBy'

const props = defineProps<{ open: boolean; houseId: number | null; isOwner: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean]; left: [] }>()

// ----- Images (per-house upload folder) -----

const folder = ref('/Pantry')
// The last value persisted to the server; used to skip no-op saves on blur.
const savedFolder = ref('/Pantry')
const folderSaved = ref(false)

async function loadFolder() {
  if (props.houseId === null) return
  try {
    folder.value = await getImageFolder(props.houseId)
    savedFolder.value = folder.value
  } catch {
    // Keep default.
  }
}

async function saveFolder() {
  const value = folder.value.trim()
  if (!value || props.houseId === null || value === savedFolder.value) return
  try {
    const persisted = await setImageFolder(props.houseId, value)
    folder.value = persisted
    savedFolder.value = persisted
    flashSaved()
  } catch {
    // Leave the field as typed so the user can retry.
  }
}

let savedTimer: ReturnType<typeof setTimeout> | null = null
function flashSaved() {
  folderSaved.value = true
  if (savedTimer) clearTimeout(savedTimer)
  savedTimer = setTimeout(() => {
    folderSaved.value = false
  }, 2000)
}

async function browseFolder() {
  const picker = getFilePickerBuilder(strings.pickerTitle)
    .setMultiSelect(false)
    .setMimeTypeFilter([])
    .allowDirectories(true)
    .setType(1) // Choose
    .startAt(folder.value || '/')
    .build()
  try {
    const picked = await picker.pick()
    const path = Array.isArray(picked) ? picked[0] : picked
    if (typeof path === 'string' && path.length > 0) {
      folder.value = path
      await saveFolder()
    }
  } catch {
    // User cancelled — no-op.
  }
}

// ----- Notification prefs (per-house) -----

const notifPrefs = reactive<NotificationPrefs>({
  notifyPhoto: true,
  notifyNoteCreate: true,
  notifyNoteEdit: true,
  notifyItemAdd: true,
  notifyItemRecur: true,
  notifyItemDone: true,
})

async function loadNotifPrefs() {
  if (props.houseId === null) return
  try {
    const prefs = await getNotificationPrefs(props.houseId)
    Object.assign(notifPrefs, prefs)
  } catch {
    // Keep defaults.
  }
}

async function updateNotifPref(key: keyof NotificationPrefs, value: boolean) {
  if (props.houseId === null) return
  notifPrefs[key] = value
  try {
    const updated = await setNotificationPrefs(props.houseId, { [key]: value })
    Object.assign(notifPrefs, updated)
  } catch {
    notifPrefs[key] = !value
  }
}

// ----- Interface prefs (global) -----

const { rowClickAction, set: setRowClickActionPref } = useRowClickAction()

interface RowClickActionOption {
  value: RowClickAction
  label: string
}

const rowClickActionOptions = computed<RowClickActionOption[]>(() => [
  { value: 'done', label: strings.rowClickActionDone },
  { value: 'view', label: strings.rowClickActionView },
  { value: 'edit', label: strings.rowClickActionEdit },
  { value: 'none', label: strings.rowClickActionNone },
])

const selectedRowClickActionOption = computed<RowClickActionOption>(
  () =>
    rowClickActionOptions.value.find((o) => o.value === rowClickAction.value) ??
    rowClickActionOptions.value[3],
)

async function updateRowClickAction(option: RowClickActionOption | null) {
  if (!option) return
  try {
    await setRowClickActionPref(option.value)
  } catch {
    // Composable already reverted the optimistic update.
  }
}

const { reuseExistingItems, set: setReuseExistingItemsPref } = useReuseExistingItems()

interface ReuseExistingItemsOption {
  value: ReuseExistingItems
  label: string
}

const reuseExistingItemsOptions = computed<ReuseExistingItemsOption[]>(() => [
  { value: 'ask', label: strings.reuseExistingItemsAsk },
  { value: 'reuse', label: strings.reuseExistingItemsReuse },
  { value: 'never', label: strings.reuseExistingItemsNever },
])

const selectedReuseExistingItemsOption = computed<ReuseExistingItemsOption>(
  () =>
    reuseExistingItemsOptions.value.find((o) => o.value === reuseExistingItems.value) ??
    reuseExistingItemsOptions.value[0],
)

async function updateReuseExistingItems(option: ReuseExistingItemsOption | null) {
  if (!option) return
  try {
    await setReuseExistingItemsPref(option.value)
  } catch {
    // Composable already reverted the optimistic update.
  }
}

const { suggestArchivedItems, set: setSuggestArchivedItemsPref } = useSuggestArchivedItems()

async function updateSuggestArchivedItems(value: boolean) {
  try {
    await setSuggestArchivedItemsPref(value)
  } catch {
    // Composable already reverted the optimistic update.
  }
}

// ----- Display: show added-by (per-house) -----

const showAddedBy = computed(() => {
  if (props.houseId === null) return false
  return useShowAddedBy(props.houseId).showAddedBy.value
})

async function updateShowAddedBy(value: boolean) {
  if (props.houseId === null) return
  try {
    await useShowAddedBy(props.houseId).set(value)
  } catch {
    // Composable already reverted the optimistic update.
  }
}

// ----- Leave house -----

const leaving = ref(false)

async function leave() {
  if (props.houseId === null || leaving.value) return
  leaving.value = true
  try {
    await leaveHouse(props.houseId)
    emit('update:open', false)
    emit('left')
  } finally {
    leaving.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      folderSaved.value = false
      void loadFolder()
      void loadNotifPrefs()
    }
  },
  { immediate: true },
)

const strings = {
  title: t('pantry', 'Personal settings'),
  interfaceSection: t('pantry', 'Interface'),
  rowClickActionLabel: t('pantry', 'Default item click action'),
  rowClickActionHint: t('pantry', 'What happens when you click an item row.'),
  // TRANSLATORS: Option for the item click action: mark the item as done.
  rowClickActionDone: t('pantry', 'Mark as done'),
  // TRANSLATORS: Option for the item click action: open the item's details view.
  rowClickActionView: t('pantry', 'View'),
  // TRANSLATORS: Option for the item click action: open the item for editing.
  rowClickActionEdit: t('pantry', 'Edit'),
  // TRANSLATORS: Option for the item click action: do nothing on click.
  rowClickActionNone: t('pantry', 'None'),
  reuseExistingItemsLabel: t('pantry', 'Reuse existing items when adding'),
  reuseExistingItemsHint: t(
    'pantry',
    'When you try to add an item that already exists in the list, reuse that item.',
  ),
  reuseExistingItemsAsk: t('pantry', 'Always ask'),
  reuseExistingItemsReuse: t('pantry', 'Always reuse'),
  reuseExistingItemsNever: t('pantry', 'Never reuse'),
  suggestArchivedItemsLabel: t('pantry', 'Suggest archived items'),
  suggestArchivedItemsHint: t(
    'pantry',
    'When adding an item, also search archived items for reuse suggestions. Reusing an archived item unarchives it.',
  ),
  notificationsSection: t('pantry', 'Notifications'),
  notificationsHint: t(
    'pantry',
    'Choose which notifications you want to receive from this household.',
  ),
  notifyPhoto: t('pantry', 'Photo uploads'),
  notifyNoteCreate: t('pantry', 'New notes'),
  notifyNoteEdit: t('pantry', 'Note edits'),
  notifyItemAdd: t('pantry', 'Checklist items added'),
  notifyItemRecur: t('pantry', 'Recurring items reappearing'),
  notifyItemDone: t('pantry', 'Checklist items completed'),
  filesSection: t('pantry', 'Files'),
  filesHint: t(
    'pantry',
    'Pick the base folder where Pantry will store uploaded files. Checklist item images go into a "Checklist items" subfolder inside it, created automatically.',
  ),
  folderLabel: t('pantry', 'Upload folder'),
  browse: t('pantry', 'Browse …'),
  pickerTitle: t('pantry', 'Pick an upload folder'),
  saved: t('pantry', 'Saved.'),
  showAddedByLabel: t('pantry', 'Show who added each item'),
  showAddedByHint: t(
    'pantry',
    'Display the avatar of the person who added each checklist item on the right of the row.',
  ),
  leaveSection: t('pantry', 'Leave house'),
  leaveHint: t(
    'pantry',
    'You will lose access to this house. An administrator can add you back later.',
  ),
  leaveButton: t('pantry', 'Leave this house'),
}
</script>

<style scoped lang="scss">
.settings__hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
  font-size: 0.9rem;

  &--inline {
    margin: 0 0 0 1.85rem;
  }
}

.settings__form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.settings__folder-row {
  display: flex;
  align-items: end;
  gap: 0.5rem;

  :deep(.input-field) {
    flex: 1;
    min-width: 0;
  }
}

.settings__saved {
  color: var(--color-success);
  font-size: 0.85rem;
  margin: 0;
  opacity: 0;
  transition: opacity 0.15s ease;

  &--show {
    opacity: 1;
  }
}

.settings__checks {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.settings__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-top: 1rem;

  &:first-child {
    margin-top: 0;
  }
}

.settings__label {
  font-weight: 600;
}
</style>
