<template>
  <div class="pantry-share-editor">
    <label class="pantry-share-editor__label">{{ strings.title }}</label>

    <!-- Add a member -->
    <NcSelect
      v-if="canManage"
      :model-value="null"
      :options="availableOptions"
      :placeholder="strings.addPlaceholder"
      :close-on-select="true"
      label="label"
      autocomplete="off"
      @update:model-value="onAdd"
    >
      <template #option="option">
        <div class="pantry-user-option">
          <NcAvatar :user="option.id" :size="24" :show-user-status="false" />
          <span class="pantry-user-option__label">{{ option.label }}</span>
        </div>
      </template>
      <template #no-options>{{ strings.noMembers }}</template>
    </NcSelect>

    <!-- Current shares -->
    <ul v-if="rows.length" class="pantry-share-editor__list">
      <li v-for="row in rows" :key="row.uid" class="pantry-share-row">
        <NcAvatar :user="row.uid" :size="28" :show-user-status="false" />
        <span class="pantry-share-row__name">{{ row.displayName }}</span>
        <span
          v-if="row.roleGrantsEdit"
          class="pantry-share-row__badge"
          :title="strings.viaRoleHint"
        >
          {{ strings.viaRole }}
        </span>
        <NcSelect
          class="pantry-share-row__level"
          :model-value="levelOption(row)"
          :options="levelOptions"
          :disabled="!canManage"
          :clearable="false"
          :searchable="false"
          label="label"
          @update:model-value="(opt: LevelOption) => onLevelChange(row, opt)"
        />
        <NcButton
          v-if="canManage"
          variant="tertiary"
          :aria-label="strings.remove"
          @click="onRemove(row)"
        >
          <template #icon><CloseIcon :size="20" /></template>
        </NcButton>
      </li>
    </ul>
    <p v-else class="pantry-share-editor__empty">{{ strings.empty }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import CloseIcon from '@icons/Close.vue'
import { listShares, setShares } from '@/api/shares'
import type { CapabilityKey, Role, ShareEntityType, SharePermission } from '@/api/types'
import { useHouseMembers } from '@/composables/useHouseMembers'
import { useRoles } from '@/composables/useRoles'
import { getCurrentUserId } from '@/utils/currentUser'

const props = defineProps<{
  houseId: number
  entityType: ShareEntityType
  entityId: number
  canManage: boolean
}>()

interface Row {
  uid: string
  displayName: string
  permission: SharePermission
  roleGrantsEdit: boolean
}

interface LevelOption {
  id: SharePermission
  label: string
}

const { members } = useHouseMembers(props.houseId)
const { roles } = useRoles(props.houseId)

const rows = ref<Row[]>([])

const strings = {
  title: t('pantry', 'Shared with:'),
  addPlaceholder: t('pantry', 'Add a person …'),
  noMembers: t('pantry', 'No more members to add'),
  empty: t('pantry', 'Not shared with anyone yet.'),
  remove: t('pantry', 'Remove'),
  view: t('pantry', 'Viewer'),
  edit: t('pantry', 'Editor'),
  viaRole: t('pantry', 'Editor (via role)'),
  viaRoleHint: t(
    'pantry',
    "This person's role already grants edit access, which overrides the share.",
  ),
}

const levelOptions: LevelOption[] = [
  { id: 'view', label: strings.view },
  { id: 'edit', label: strings.edit },
]

function levelOption(row: Row): LevelOption {
  return levelOptions.find((o) => o.id === row.permission) ?? levelOptions[0]
}

const displayNameByUid = computed<Record<string, string>>(() => {
  const map: Record<string, string> = {}
  for (const m of members.value) map[m.userId] = m.displayName
  return map
})

// The capability that means "can edit this entity type".
const editCapability = computed<CapabilityKey>(() => {
  switch (props.entityType) {
    case 'checklist':
      return 'canEditLists'
    case 'note':
      return 'canUpdateNotes'
    default:
      return 'canUpdatePhotos'
  }
})

function roleGrantsEdit(uid: string): boolean {
  const member = members.value.find((m) => m.userId === uid)
  if (!member) return false
  const cap = editCapability.value
  return member.roleIds.some((rid) => {
    const role: Role | undefined = roles.value.find((r) => r.id === rid)
    if (!role) return false
    return role.roleType === 'admin' || role[cap] === true
  })
}

const availableOptions = computed(() =>
  members.value
    .filter((m) => m.userId !== getCurrentUserId())
    .filter((m) => !rows.value.some((r) => r.uid === m.userId))
    .map((m) => ({ id: m.userId, label: m.displayName })),
)

async function load() {
  const shares = await listShares(props.houseId, props.entityType, props.entityId)
  rows.value = shares.map((s) => ({
    uid: s.sharedWithUid,
    displayName: displayNameByUid.value[s.sharedWithUid] ?? s.sharedWithUid,
    permission: s.permission,
    roleGrantsEdit: roleGrantsEdit(s.sharedWithUid),
  }))
}

async function persist() {
  await setShares(
    props.houseId,
    props.entityType,
    props.entityId,
    rows.value.map((r) => ({ uid: r.uid, permission: r.permission })),
  )
}

async function onAdd(option: { id: string; label: string } | null) {
  if (!option) return
  if (rows.value.some((r) => r.uid === option.id)) return
  rows.value.push({
    uid: option.id,
    displayName: option.label,
    permission: 'view',
    roleGrantsEdit: roleGrantsEdit(option.id),
  })
  await persist()
}

async function onLevelChange(row: Row, option: LevelOption | null) {
  if (!option || row.permission === option.id) return
  row.permission = option.id
  await persist()
}

async function onRemove(row: Row) {
  rows.value = rows.value.filter((r) => r.uid !== row.uid)
  await persist()
}

watch(
  () => [props.entityId, props.entityType] as const,
  () => {
    if (props.entityId > 0) void load()
  },
  { immediate: true },
)

// Recompute role-override badges once members/roles finish loading.
watch([members, roles], () => {
  for (const r of rows.value) {
    r.displayName = displayNameByUid.value[r.uid] ?? r.displayName
    r.roleGrantsEdit = roleGrantsEdit(r.uid)
  }
})
</script>

<style scoped lang="scss">
.pantry-share-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;

  &__label {
    font-weight: 600;
    font-size: 0.85rem;
  }

  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__empty {
    margin: 0;
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
  }
}

.pantry-share-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;

  &__name {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__badge {
    flex: 0 1 auto;
    min-width: 0;
    font-size: 0.75em;
    color: var(--color-primary-element);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__level {
    flex: 0 0 auto;
    width: 7.5rem;
    min-width: 0;

    // NcSelect defaults to a wide min-width; let it shrink to the column.
    :deep(.v-select) {
      min-width: 0;
    }
  }
}

.pantry-user-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  &__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
