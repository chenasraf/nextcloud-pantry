<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    :close-on-click-outside="false"
    @update:open="onDialogOpen"
  >
    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p class="pantry-hint">{{ strings.hint }}</p>

      <div class="pantry-table-scroll">
        <table v-if="working.length > 0" class="pantry-members-table">
          <thead>
            <tr>
              <th>{{ strings.colUser }}</th>
              <th>{{ strings.colRole }}</th>
              <th>{{ strings.colJoined }}</th>
              <th class="pantry-members-table__actions-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in working" :key="m.id" :class="{ 'is-removed': m.removed }">
              <td>
                {{ m.displayName }}
                <span v-if="m.isNew" class="pantry-tag">{{ strings.tagNew }}</span>
              </td>
              <td>
                <NcSelect
                  v-if="!m.isNew && m.role !== 'owner' && !m.removed"
                  :model-value="memberRoleValue(m)"
                  :options="roleSelectOptions"
                  :multiple="true"
                  :close-on-select="false"
                  :clearable="false"
                  :searchable="false"
                  label="label"
                  autocomplete="off"
                  @update:model-value="(opts: RoleSelectOption[]) => setMemberRoleIds(m, opts)"
                />
                <span v-else>{{ memberRoleNames(m) }}</span>
              </td>
              <td>
                <NcDateTime v-if="!m.isNew" :timestamp="m.joinedAt * 1000" />
                <span v-else>—</span>
              </td>
              <td class="pantry-members-table__actions">
                <NcButton
                  v-if="m.role !== 'owner'"
                  variant="tertiary"
                  :aria-label="m.removed ? strings.undoRemove : strings.removeMember"
                  :title="m.removed ? strings.undoRemove : strings.removeMember"
                  @click="toggleRemove(m)"
                >
                  <template #icon>
                    <UndoIcon v-if="m.removed" :size="18" />
                    <DeleteIcon v-else :size="18" />
                  </template>
                </NcButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pantry-add">
        <div class="pantry-add__title">{{ strings.addTitle }}</div>
        <div class="pantry-add__row">
          <NcSelect
            v-model="selectedUser"
            class="pantry-add__user"
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
            class="pantry-add__role"
            :options="roleOptions"
            :input-label="strings.roleLabel"
            :clearable="false"
            :searchable="false"
            autocomplete="off"
          />
          <NcButton variant="secondary" :disabled="!selectedUser" @click="stageAdd">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.add }}
          </NcButton>
        </div>
        <p v-if="addError" class="pantry-form-error">{{ addError }}</p>
      </div>

      <p v-if="saveError" class="pantry-form-error">{{ saveError }}</p>
    </template>

    <template #actions>
      <NcButton @click="requestClose">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="!dirty || saving" @click="save">
        {{ saving ? strings.saving : strings.save }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import UndoIcon from '@icons/Undo.vue'
import * as houseApi from '@/api/houses'
import { setMemberRoles } from '@/api/roles'
import type { UserAutocomplete } from '@/api/houses'
import type { HouseMember, HouseRole } from '@/api/types'
import { useRoles } from '@/composables/useRoles'

const props = defineProps<{ open: boolean; houseId: number }>()
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>()

// Working copy of the member list. Every edit stages locally; nothing hits the
// server until Save. Existing rows carry their real member id; staged additions
// carry a negative temp id.
interface WorkingMember {
  id: number
  displayName: string
  userId: string
  role: HouseRole
  roleIds: number[]
  joinedAt: number
  isNew: boolean
  removed: boolean
}

const working = ref<WorkingMember[]>([])
const originalRoleIds = new Map<number, number[]>()
const loading = ref(false)
const saving = ref(false)
const saveError = ref<string | null>(null)

const { roles } = useRoles(props.houseId)

interface RoleSelectOption {
  id: number
  label: string
}
const roleSelectOptions = computed<RoleSelectOption[]>(() =>
  roles.value.map((r) => ({ id: r.id, label: r.name })),
)

interface RoleOption {
  label: string
  value: HouseRole
}
const roleOptions = computed<RoleOption[]>(() => [
  { label: t('pantry', 'Member'), value: 'member' },
  { label: t('pantry', 'Administrator'), value: 'admin' },
])

async function load() {
  loading.value = true
  saveError.value = null
  try {
    const members = await houseApi.listMembers(props.houseId)
    originalRoleIds.clear()
    for (const m of members) {
      originalRoleIds.set(m.id, [...(m.roleIds ?? [])])
    }
    working.value = members.map((m) => ({
      id: m.id,
      displayName: m.displayName,
      userId: m.userId,
      role: m.role,
      roleIds: [...(m.roleIds ?? [])],
      joinedAt: m.joinedAt,
      isNew: false,
      removed: false,
    }))
  } finally {
    loading.value = false
  }
}

// -------- Role editing (existing members) --------
function memberRoleValue(m: WorkingMember): RoleSelectOption[] {
  return roleSelectOptions.value.filter((o) => m.roleIds.includes(o.id))
}
function memberRoleNames(m: WorkingMember): string {
  if (m.isNew) {
    return roleOptions.value.find((o) => o.value === m.role)?.label ?? m.role
  }
  const names = memberRoleValue(m).map((o) => o.label)
  return names.length > 0 ? names.join(', ') : t('pantry', 'No roles')
}
function setMemberRoleIds(m: WorkingMember, options: RoleSelectOption[]) {
  m.roleIds = options.map((o) => o.id)
}

function toggleRemove(m: WorkingMember) {
  if (m.isNew) {
    // Discard a not-yet-saved addition outright.
    working.value = working.value.filter((w) => w.id !== m.id)
    return
  }
  m.removed = !m.removed
}

// -------- Add member (staged) --------
const selectedUser = ref<UserAutocomplete | null>(null)
const newRoleOption = ref<RoleOption>(roleOptions.value[0]!)
const addError = ref<string | null>(null)
const userSearchOptions = ref<UserAutocomplete[]>([])
const userSearching = ref(false)
const userSearchQuery = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null
let tempId = -1

function resetAddForm() {
  selectedUser.value = null
  newRoleOption.value = roleOptions.value[0]!
  addError.value = null
  userSearchOptions.value = []
  userSearchQuery.value = ''
}

// Re-stage from the server each time the modal opens. Declared here, after the
// add-form refs resetAddForm touches, so the immediate run finds them defined.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetAddForm()
      void load()
    }
  },
  { immediate: true },
)

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

function stageAdd() {
  const user = selectedUser.value
  if (!user) return
  addError.value = null
  if (working.value.some((m) => m.userId === user.id && !m.removed)) {
    addError.value = t('pantry', 'That account is already a member.')
    return
  }
  working.value = [
    ...working.value,
    {
      id: tempId--,
      displayName: user.label,
      userId: user.id,
      role: newRoleOption.value.value,
      roleIds: [],
      joinedAt: 0,
      isNew: true,
      removed: false,
    },
  ]
  selectedUser.value = null
  userSearchOptions.value = []
  userSearchQuery.value = ''
}

// -------- Dirty tracking + Save --------
function sameIds(a: number[], b: number[]): boolean {
  if (a.length !== b.length) return false
  const setB = new Set(b)
  return a.every((x) => setB.has(x))
}

const dirty = computed(() =>
  working.value.some(
    (m) => m.isNew || m.removed || !sameIds(m.roleIds, originalRoleIds.get(m.id) ?? []),
  ),
)

async function save() {
  if (!dirty.value || saving.value) return
  saving.value = true
  saveError.value = null
  const id = props.houseId
  try {
    for (const m of working.value) {
      if (m.isNew) {
        await houseApi.addMember(id, m.userId, m.role)
      } else if (m.removed) {
        await houseApi.removeMember(id, m.id)
      } else if (!sameIds(m.roleIds, originalRoleIds.get(m.id) ?? [])) {
        await setMemberRoles(id, m.id, m.roleIds)
      }
    }
    emit('changed')
    emit('update:open', false)
  } catch (e) {
    saveError.value = (e as Error).message || t('pantry', 'Could not save members.')
    // Re-sync to the server's actual state so retrying starts from the truth.
    await load()
  } finally {
    saving.value = false
  }
}

// -------- Close guard --------
function requestClose() {
  if (dirty.value && !window.confirm(strings.unsavedConfirm)) return
  emit('update:open', false)
}
function onDialogOpen(value: boolean) {
  if (value) return
  requestClose()
}

const strings = {
  title: t('pantry', 'Members'),
  hint: t('pantry', 'Add or remove members and assign their roles. Changes apply when you save.'),
  colUser: t('pantry', 'Account'),
  colRole: t('pantry', 'Role'),
  colJoined: t('pantry', 'Joined'),
  // TRANSLATORS: Small tag on a member row that has been added but not yet saved.
  tagNew: t('pantry', 'New'),
  removeMember: t('pantry', 'Remove member'),
  undoRemove: t('pantry', 'Undo remove'),
  addTitle: t('pantry', 'Add a member'),
  add: t('pantry', 'Add'),
  userIdLabel: t('pantry', 'Account'),
  userSearchPlaceholder: t('pantry', 'Search for an account …'),
  noResults: t('pantry', 'No accounts found'),
  typeToSearch: t('pantry', 'Type to search for an account'),
  roleLabel: t('pantry', 'Role'),
  save: t('pantry', 'Save changes'),
  saving: t('pantry', 'Saving …'),
  cancel: t('pantry', 'Cancel'),
  unsavedConfirm: t('pantry', 'You have unsaved changes. Close without saving?'),
}
</script>

<style scoped lang="scss">
.pantry-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-table-scroll {
  overflow-x: auto;
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

  .is-removed td {
    opacity: 0.5;
    text-decoration: line-through;
  }
}

.pantry-tag {
  margin-inline-start: 0.4rem;
  font-size: 0.75em;
  padding: 0.05rem 0.4rem;
  border-radius: 6px;
  background: var(--color-primary-element-light);
  color: var(--color-primary-element-light-text);
  vertical-align: middle;
}

.pantry-add {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-border);

  &__title {
    font-weight: 600;
    margin-bottom: 0.5rem;
  }

  &__row {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  &__user {
    flex: 1 1 220px;
    min-width: 0;
  }

  &__role {
    flex: 0 0 160px;
  }
}

.pantry-form-error {
  color: var(--color-error);
  margin: 0.5rem 0 0;
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
