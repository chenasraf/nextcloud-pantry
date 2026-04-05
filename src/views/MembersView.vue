<template>
  <div class="pantry-members">
    <header class="pantry-members__header">
      <h2>{{ strings.title }}</h2>
      <NcButton v-if="canAdmin" variant="primary" @click="showAdd = true">
        <template #icon>
          <PlusIcon :size="20" />
        </template>
        {{ strings.addMember }}
      </NcButton>
    </header>

    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="36" />
    </div>

    <table v-else class="pantry-members__table">
      <thead>
        <tr>
          <th>{{ strings.colUser }}</th>
          <th>{{ strings.colRole }}</th>
          <th>{{ strings.colJoined }}</th>
          <th class="pantry-members__actions-col"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="member in members" :key="member.id">
          <td>{{ member.displayName }}</td>
          <td>
            <select
              v-if="canAdmin && member.role !== 'owner'"
              :value="member.role"
              @change="changeRole(member.id, ($event.target as HTMLSelectElement).value)"
            >
              <option value="admin">{{ roleLabel('admin') }}</option>
              <option value="member">{{ roleLabel('member') }}</option>
            </select>
            <span v-else>{{ roleLabel(member.role) }}</span>
          </td>
          <td>
            <NcDateTime :timestamp="member.joinedAt * 1000" />
          </td>
          <td class="pantry-members__actions">
            <NcButton
              v-if="canAdmin && member.role !== 'owner'"
              variant="tertiary"
              :aria-label="strings.removeMember"
              @click="removeExisting(member.id)"
            >
              <template #icon>
                <DeleteIcon :size="18" />
              </template>
            </NcButton>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="!isOwner" class="pantry-members__leave">
      <NcButton variant="secondary" @click="leave">
        {{ strings.leaveButton }}
      </NcButton>
    </div>

    <NcDialog
      v-if="showAdd"
      :name="strings.addDialogTitle"
      :open="showAdd"
      @update:open="showAdd = $event"
    >
      <form class="pantry-form" @submit.prevent="submitAdd">
        <NcTextField
          v-model="newUserId"
          :label="strings.userIdLabel"
          :placeholder="strings.userIdPlaceholder"
        />
        <NcSelect v-model="newRoleLabel" :options="roleOptions" :input-label="strings.roleLabel" />
        <p v-if="addError" class="pantry-form-error">{{ addError }}</p>
      </form>
      <template #actions>
        <NcButton @click="showAdd = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="!newUserId.trim()" @click="submitAdd">
          {{ strings.addMember }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import * as api from '@/api/houses'
import type { HouseMember, HouseRole } from '@/api/types'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

const props = defineProps<{ houseId: string }>()
const router = useRouter()
const houseIdNum = computed(() => Number(props.houseId))
const { canAdmin, isOwner } = useCurrentHouse()

const members = ref<HouseMember[]>([])
const loading = ref(false)

const showAdd = ref(false)
const newUserId = ref('')
interface RoleOption {
  label: string
  value: HouseRole
}
const roleOptions = computed<RoleOption[]>(() => [
  { label: t('pantry', 'Member'), value: 'member' },
  { label: t('pantry', 'Administrator'), value: 'admin' },
])
const newRoleLabel = ref<RoleOption>(roleOptions.value[0]!)
const addError = ref<string | null>(null)

async function load() {
  loading.value = true
  try {
    members.value = await api.listMembers(houseIdNum.value)
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submitAdd() {
  const uid = newUserId.value.trim()
  if (!uid) return
  addError.value = null
  try {
    const role: HouseRole = newRoleLabel.value?.value ?? 'member'
    const member = await api.addMember(houseIdNum.value, uid, role)
    members.value = [...members.value, member]
    showAdd.value = false
    newUserId.value = ''
  } catch (e) {
    addError.value = (e as Error).message || t('pantry', 'Could not add member.')
  }
}

async function changeRole(memberId: number, role: string) {
  if (role !== 'admin' && role !== 'member') return
  const updated = await api.updateMemberRole(houseIdNum.value, memberId, role)
  members.value = members.value.map((m) => (m.id === memberId ? updated : m))
}

async function removeExisting(memberId: number) {
  await api.removeMember(houseIdNum.value, memberId)
  members.value = members.value.filter((m) => m.id !== memberId)
}

async function leave() {
  await api.leaveHouse(houseIdNum.value)
  await router.push({ name: 'houses' })
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

const strings = {
  title: t('pantry', 'Members'),
  addMember: t('pantry', 'Add member'),
  removeMember: t('pantry', 'Remove member'),
  leaveButton: t('pantry', 'Leave this house'),
  colUser: t('pantry', 'Account'),
  colRole: t('pantry', 'Role'),
  colJoined: t('pantry', 'Joined'),
  addDialogTitle: t('pantry', 'Add a member'),
  userIdLabel: t('pantry', 'Account ID:'),
  userIdPlaceholder: t('pantry', 'The Nextcloud username'),
  roleLabel: t('pantry', 'Role:'),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-members {
  max-width: 900px;
  margin: 0 auto;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;

    h2 {
      margin: 0;
    }
  }

  &__table {
    width: 100%;
    border-collapse: collapse;

    th,
    td {
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid var(--color-border);
    }
  }

  &__actions-col {
    width: 44px;
  }

  &__actions {
    text-align: right;
  }

  &__leave {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}

.pantry-form-error {
  color: var(--color-error);
  margin: 0;
}
</style>
