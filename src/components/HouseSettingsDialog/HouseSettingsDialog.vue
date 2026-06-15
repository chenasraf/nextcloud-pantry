<template>
  <NcAppSettingsDialog
    :open="open"
    :name="strings.title"
    :show-navigation="true"
    @update:open="(v) => emit('update:open', v)"
  >
    <NcAppSettingsSection id="house-general" :name="strings.generalSection">
      <form class="pantry-form" autocomplete="off" @submit.prevent="saveGeneral">
        <NcTextField
          v-model="name"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
        <NcTextField
          v-model="description"
          :label="strings.descriptionLabel"
          :placeholder="strings.descriptionPlaceholder"
          autocomplete="off"
        />
        <div class="pantry-form__actions">
          <NcButton type="submit" variant="primary" :disabled="savingGeneral || !name.trim()">
            {{ savingGeneral ? strings.saving : strings.save }}
          </NcButton>
        </div>
      </form>
    </NcAppSettingsSection>

    <NcAppSettingsSection
      v-if="houseIdNum !== null"
      id="house-display"
      :name="strings.displaySection"
    >
      <div class="pantry-display">
        <NcCheckboxRadioSwitch
          :model-value="showAddedBy"
          @update:model-value="updateShowAddedBy($event)"
        >
          {{ strings.showAddedByLabel }}
        </NcCheckboxRadioSwitch>
        <p class="pantry-hint pantry-hint--inline">{{ strings.showAddedByHint }}</p>
      </div>
    </NcAppSettingsSection>

    <NcAppSettingsSection
      v-if="houseIdNum !== null && canAdmin"
      id="house-trash"
      :name="strings.trashSection"
    >
      <p class="pantry-hint">{{ strings.trashRetentionHint }}</p>
      <form class="pantry-form" autocomplete="off" @submit.prevent="saveTrashRetention">
        <NcTextField
          v-model="trashRetentionInput"
          type="number"
          :label="strings.trashRetentionLabel"
          :min="0"
          :max="3650"
          autocomplete="off"
        />
        <p class="pantry-hint pantry-hint--small">{{ trashRetentionSummary }}</p>
        <div class="pantry-form__actions">
          <NcButton
            type="submit"
            variant="primary"
            :disabled="savingTrashRetention || !isTrashRetentionValid"
          >
            {{ savingTrashRetention ? strings.saving : strings.save }}
          </NcButton>
        </div>
      </form>
    </NcAppSettingsSection>

    <NcAppSettingsSection id="house-members" :name="strings.membersSection">
      <div v-if="loadingMembers" class="pantry-center">
        <NcLoadingIcon :size="28" />
      </div>
      <template v-else>
        <table v-if="members.length > 0" class="pantry-members-table">
          <thead>
            <tr>
              <th>{{ strings.colUser }}</th>
              <th>{{ strings.colRole }}</th>
              <th>{{ strings.colJoined }}</th>
              <th class="pantry-members-table__actions-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members" :key="member.id">
              <td>{{ member.displayName }}</td>
              <td>
                <NcSelect
                  v-if="canAdmin && member.role !== 'owner'"
                  :model-value="roleOptionFor(member.role)"
                  :options="roleOptions"
                  :clearable="false"
                  :searchable="false"
                  autocomplete="off"
                  @option:selected="(opt: RoleOption) => changeRole(member.id, opt.value)"
                />
                <span v-else>{{ roleLabel(member.role) }}</span>
              </td>
              <td>
                <NcDateTime :timestamp="member.joinedAt * 1000" />
              </td>
              <td class="pantry-members-table__actions">
                <NcButton
                  v-if="canAdmin && member.role !== 'owner'"
                  variant="tertiary"
                  :aria-label="strings.removeMember"
                  @click="removeMember(member.id)"
                >
                  <template #icon>
                    <DeleteIcon :size="18" />
                  </template>
                </NcButton>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="canAdmin" class="pantry-members-add">
          <NcButton variant="primary" @click="showAdd = true">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.addMember }}
          </NcButton>
        </div>

        <div v-if="!isOwner" class="pantry-members-leave">
          <NcButton variant="secondary" @click="leave">
            {{ strings.leaveButton }}
          </NcButton>
        </div>
      </template>
    </NcAppSettingsSection>

    <NcAppSettingsSection v-if="isOwner" id="house-danger" :name="strings.dangerSection">
      <p class="pantry-danger-hint">{{ strings.dangerBody }}</p>
      <NcButton variant="error" @click="confirmingDelete = true">
        {{ strings.deleteButton }}
      </NcButton>
    </NcAppSettingsSection>
  </NcAppSettingsDialog>

  <NcDialog
    v-if="showAdd"
    :name="strings.addDialogTitle"
    :open="showAdd"
    close-on-click-outside
    @update:open="showAdd = $event"
  >
    <form class="pantry-form" autocomplete="off" @submit.prevent="submitAdd">
      <NcSelect
        v-model="selectedUser"
        :options="userSearchOptions"
        :placeholder="strings.userSearchPlaceholder"
        :input-label="strings.userIdLabel"
        :loading="userSearching"
        :filterable="false"
        label="label"
        @search="handleUserSearch"
      >
        <template #option="option">
          <div class="pantry-user-option">
            <NcAvatar :user="option.id" :size="24" :show-user-status="false" />
            <span class="pantry-user-option__label">{{ option.label }}</span>
            <span class="pantry-user-option__id">@{{ option.id }}</span>
          </div>
        </template>
        <template #selected-option="option">
          <div class="pantry-user-option">
            <NcAvatar :user="option.id" :size="20" :show-user-status="false" />
            <span class="pantry-user-option__label">{{ option.label }}</span>
          </div>
        </template>
        <template #no-options>
          {{ userSearchQuery ? strings.noResults : strings.typeToSearch }}
        </template>
      </NcSelect>
      <NcSelect
        v-model="newRoleOption"
        :options="roleOptions"
        :input-label="strings.roleLabel"
        autocomplete="off"
      />
      <p v-if="addError" class="pantry-form-error">{{ addError }}</p>
    </form>
    <template #actions>
      <NcButton @click="showAdd = false">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="!selectedUser" @click="submitAdd">
        {{ strings.addMember }}
      </NcButton>
    </template>
  </NcDialog>

  <NcDialog
    v-if="confirmingDelete"
    :name="strings.deleteDialogTitle"
    :open="confirmingDelete"
    close-on-click-outside
    @update:open="confirmingDelete = $event"
  >
    <p>{{ strings.deleteConfirmBody }}</p>
    <template #actions>
      <NcButton @click="confirmingDelete = false">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="deleteHouse">{{ strings.deleteButton }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import * as houseApi from '@/api/houses'
import type { UserAutocomplete } from '@/api/houses'
import type { HouseMember, HouseRole } from '@/api/types'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useHouses } from '@/composables/useHouses'
import { useShowAddedBy } from '@/composables/useShowAddedBy'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const router = useRouter()
const { house, isOwner, canAdmin, refresh } = useCurrentHouse()
const { update, remove } = useHouses()

const houseIdNum = computed(() => house.value?.id ?? null)

// -------- General --------
const name = ref('')
const description = ref('')
const savingGeneral = ref(false)
const trashRetentionInput = ref<string>('30')
const savingTrashRetention = ref(false)

function syncFromHouse() {
  if (house.value) {
    name.value = house.value.name
    description.value = house.value.description ?? ''
    trashRetentionInput.value = String(house.value.trashRetentionDays ?? 30)
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      syncFromHouse()
      void loadMembers()
    }
  },
)

watch(house, syncFromHouse, { immediate: true })

async function saveGeneral() {
  const id = houseIdNum.value
  if (id === null) return
  savingGeneral.value = true
  try {
    await update(id, {
      name: name.value.trim(),
      description: description.value.trim(),
    })
    await refresh()
  } finally {
    savingGeneral.value = false
  }
}

const trashRetentionParsed = computed(() => {
  const raw = trashRetentionInput.value.trim()
  if (raw === '') return null
  if (!/^\d+$/.test(raw)) return null
  const n = Number(raw)
  if (!Number.isFinite(n) || n < 0 || n > 3650) return null
  return n
})

const isTrashRetentionValid = computed(() => trashRetentionParsed.value !== null)

const trashRetentionSummary = computed(() => {
  const n = trashRetentionParsed.value
  if (n === null) return strings.trashRetentionInvalid
  if (n === 0) return strings.trashRetentionDisabled
  return t('pantry', 'Items in the trash are permanently deleted after {n} day(s).', { n })
})

async function saveTrashRetention() {
  const id = houseIdNum.value
  const n = trashRetentionParsed.value
  if (id === null || n === null) return
  savingTrashRetention.value = true
  try {
    await update(id, { trashRetentionDays: n })
    await refresh()
  } finally {
    savingTrashRetention.value = false
  }
}

// -------- Members --------
const members = ref<HouseMember[]>([])
const loadingMembers = ref(false)

async function loadMembers() {
  const id = houseIdNum.value
  if (id === null) return
  loadingMembers.value = true
  try {
    members.value = await houseApi.listMembers(id)
  } finally {
    loadingMembers.value = false
  }
}

interface RoleOption {
  label: string
  value: HouseRole
}
const roleOptions = computed<RoleOption[]>(() => [
  { label: t('pantry', 'Member'), value: 'member' },
  { label: t('pantry', 'Administrator'), value: 'admin' },
])

const showAdd = ref(false)
const selectedUser = ref<UserAutocomplete | null>(null)
const newRoleOption = ref<RoleOption>(roleOptions.value[0]!)
const addError = ref<string | null>(null)

const userSearchOptions = ref<UserAutocomplete[]>([])
const userSearching = ref(false)
const userSearchQuery = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

function handleUserSearch(query: string) {
  userSearchQuery.value = query
  if (searchTimeout) clearTimeout(searchTimeout)
  if (!query) {
    userSearchOptions.value = []
    return
  }
  searchTimeout = setTimeout(() => {
    void fetchUsers(query)
  }, 300)
}

async function fetchUsers(query: string) {
  try {
    userSearching.value = true
    userSearchOptions.value = await houseApi.searchUsers(query, 10)
  } catch {
    userSearchOptions.value = []
  } finally {
    userSearching.value = false
  }
}

async function submitAdd() {
  const id = houseIdNum.value
  if (!selectedUser.value || id === null) return
  addError.value = null
  try {
    const role: HouseRole = newRoleOption.value?.value ?? 'member'
    const member = await houseApi.addMember(id, selectedUser.value.id, role)
    members.value = [...members.value, member]
    showAdd.value = false
    selectedUser.value = null
    userSearchOptions.value = []
    userSearchQuery.value = ''
  } catch (e) {
    addError.value = (e as Error).message || t('pantry', 'Could not add member.')
  }
}

async function changeRole(memberId: number, role: string) {
  const id = houseIdNum.value
  if (id === null || (role !== 'admin' && role !== 'member')) return
  const updated = await houseApi.updateMemberRole(id, memberId, role)
  members.value = members.value.map((m) => (m.id === memberId ? updated : m))
}

async function removeMember(memberId: number) {
  const id = houseIdNum.value
  if (id === null) return
  await houseApi.removeMember(id, memberId)
  members.value = members.value.filter((m) => m.id !== memberId)
}

async function leave() {
  const id = houseIdNum.value
  if (id === null) return
  await houseApi.leaveHouse(id)
  emit('update:open', false)
  await router.push({ name: 'home' })
}

// -------- Danger zone --------
const confirmingDelete = ref(false)

// -------- Display --------
const showAddedBy = computed(() => {
  const id = houseIdNum.value
  if (id === null) return false
  return useShowAddedBy(id).showAddedBy.value
})

async function updateShowAddedBy(value: boolean) {
  const id = houseIdNum.value
  if (id === null) return
  try {
    await useShowAddedBy(id).set(value)
  } catch {
    // Composable already reverted the optimistic update.
  }
}

async function deleteHouse() {
  const id = houseIdNum.value
  if (id === null) return
  await remove(id)
  confirmingDelete.value = false
  emit('update:open', false)
  await router.push({ name: 'home' })
}

function roleLabel(role: HouseRole): string {
  switch (role) {
    case 'owner':
      return t('pantry', 'Owner')
    case 'admin':
      return t('pantry', 'Administrator')
    default:
      return t('pantry', 'Member')
  }
}

function roleOptionFor(role: HouseRole): RoleOption {
  return roleOptions.value.find((o) => o.value === role) ?? roleOptions.value[0]!
}

const strings = {
  title: t('pantry', 'House settings'),
  generalSection: t('pantry', 'General'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'House name'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  save: t('pantry', 'Save changes'),
  saving: t('pantry', 'Saving …'),
  saved: t('pantry', 'Saved.'),
  imagesSection: t('pantry', 'Images'),
  imagesHint: t(
    'pantry',
    'Pick the base folder where Pantry will store uploaded images for this house. Checklist item images go into a "Checklist items" subfolder inside it, created automatically.',
  ),
  folderLabel: t('pantry', 'Upload folder'),
  browse: t('pantry', 'Browse …'),
  pickerTitle: t('pantry', 'Pick an upload folder'),
  displaySection: t('pantry', 'Display'),
  showAddedByLabel: t('pantry', 'Show who added each item'),
  showAddedByHint: t(
    'pantry',
    'Display the avatar of the person who added each checklist item on the right of the row.',
  ),
  trashSection: t('pantry', 'Trash'),
  trashRetentionLabel: t('pantry', 'Days to keep items in the trash'),
  trashRetentionHint: t(
    'pantry',
    'A daily background job permanently deletes checklists, items, notes and photos whose deleted-at timestamp is older than this many days. Set to 0 to never auto-delete — items stay in the trash until removed manually.',
  ),
  trashRetentionDisabled: t(
    'pantry',
    'Auto-delete disabled. Items stay in the trash until removed manually.',
  ),
  trashRetentionInvalid: t('pantry', 'Enter a whole number between 0 and 3650.'),
  membersSection: t('pantry', 'Members'),
  addMember: t('pantry', 'Add member'),
  removeMember: t('pantry', 'Remove member'),
  leaveButton: t('pantry', 'Leave this house'),
  colUser: t('pantry', 'Account'),
  colRole: t('pantry', 'Role'),
  colJoined: t('pantry', 'Joined'),
  addDialogTitle: t('pantry', 'Add a member'),
  userIdLabel: t('pantry', 'Account'),
  userSearchPlaceholder: t('pantry', 'Search for an account …'),
  noResults: t('pantry', 'No accounts found'),
  typeToSearch: t('pantry', 'Type to search for an account'),
  roleLabel: t('pantry', 'Role'),
  dangerSection: t('pantry', 'Danger zone'),
  dangerBody: t(
    'pantry',
    'Deleting a house permanently removes all of its lists, items, and membership records. This cannot be undone.',
  ),
  deleteButton: t('pantry', 'Delete house'),
  deleteDialogTitle: t('pantry', 'Delete this house?'),
  deleteConfirmBody: t(
    'pantry',
    'All lists, items and member records for this house will be permanently deleted.',
  ),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  &__actions {
    display: flex;
    justify-content: flex-end;
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-members-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
  }

  &__actions-col {
    width: 44px;
  }

  &__actions {
    text-align: right;
  }
}

.pantry-members-add {
  margin-top: 1rem;
}

.pantry-members-leave {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
}

.pantry-danger-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-form-error {
  color: var(--color-error);
  margin: 0;
}

.pantry-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;

  &--inline {
    margin: 0 0 0 1.85rem;
  }

  &--small {
    font-size: 0.85em;
    margin: 0;
  }
}

.pantry-display {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.pantry-user-option {
  display: flex;
  align-items: center;
  gap: 8px;

  &__id {
    color: var(--color-text-maxcontrast);
    font-size: 0.85em;
  }
}
</style>
