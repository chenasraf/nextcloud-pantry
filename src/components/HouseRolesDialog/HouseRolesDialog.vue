<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    :close-on-click-outside="false"
    @update:open="onDialogOpen"
  >
    <p class="pantry-hint">{{ strings.hint }}</p>

    <div class="pantry-roles">
      <div
        v-for="role in displayRoles"
        :key="role.id"
        class="pantry-role"
        :class="{ 'pantry-role--deleted': role.deleted }"
      >
        <div class="pantry-role__name">
          <NcTextField
            v-model="role.name"
            :label="strings.roleNameLabel"
            :disabled="role.deleted"
            autocomplete="off"
          />
        </div>
        <div class="pantry-role__type">
          <span class="pantry-chip">{{ roleTypeLabel(role.roleType) }}</span>
        </div>
        <div class="pantry-role__actions">
          <NcButton v-if="!role.deleted" variant="secondary" @click="duplicateRole(role)">
            <template #icon><ContentCopyIcon :size="18" /></template>
            {{ strings.duplicateRole }}
          </NcButton>
          <NcButton
            v-if="role.roleType === 'normal'"
            variant="tertiary"
            :aria-label="role.deleted ? strings.undoDelete : strings.deleteRole"
            :title="role.deleted ? strings.undoDelete : strings.deleteRole"
            @click="toggleDelete(role)"
          >
            <template #icon>
              <UndoIcon v-if="role.deleted" :size="18" />
              <DeleteIcon v-else :size="18" />
            </template>
          </NcButton>
        </div>

        <template v-if="!role.deleted">
          <p v-if="role.roleType === 'admin'" class="pantry-role__hint">
            {{ strings.adminRoleHint }}
          </p>
          <template v-else>
            <button
              type="button"
              class="pantry-expander"
              :aria-expanded="isExpanded(role.id)"
              @click="toggleExpanded(role.id)"
            >
              <span class="pantry-expander__text">
                <span class="pantry-expander__title">{{ strings.customize }}</span>
                <span class="pantry-expander__summary">{{ summaryFor(role) }}</span>
              </span>
              <ChevronUpIcon v-if="isExpanded(role.id)" :size="20" />
              <ChevronDownIcon v-else :size="20" />
            </button>
            <div v-if="isExpanded(role.id)" class="pantry-role__caps">
              <div v-for="group in capGroups" :key="group.label" class="pantry-role__group">
                <div class="pantry-role__group-label">{{ group.label }}</div>
                <NcCheckboxRadioSwitch
                  v-for="cap in group.caps"
                  :key="cap.key"
                  :model-value="role.caps[cap.key]"
                  :disabled="capDisabled(role, cap.key)"
                  @update:model-value="(v: boolean) => setCap(role, cap.key, v)"
                >
                  {{ cap.label }}
                </NcCheckboxRadioSwitch>
              </div>
            </div>
          </template>
        </template>
      </div>
    </div>

    <div class="pantry-role-add">
      <NcButton variant="secondary" @click="addRole">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.addRole }}
      </NcButton>
    </div>

    <p v-if="saveError" class="pantry-form-error">{{ saveError }}</p>

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
import { t, n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import UndoIcon from '@icons/Undo.vue'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import ChevronUpIcon from '@icons/ChevronUp.vue'
import type { CapabilityKey, Role } from '@/api/types'
import { useRoles } from '@/composables/useRoles'

const props = defineProps<{ open: boolean; houseId: number }>()
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>()

const { roles, create, update, remove, refresh } = useRoles(props.houseId)

interface CapGroup {
  label: string
  caps: { key: CapabilityKey; label: string }[]
}
const capGroups = computed<CapGroup[]>(() => [
  {
    label: t('pantry', 'Checklists'),
    caps: [
      // TRANSLATORS: Verb, permission checkbox for viewing checklists.
      { key: 'canViewLists', label: t('pantry', 'View') },
      // TRANSLATORS: Verb, permission checkbox for creating checklists.
      { key: 'canCreateLists', label: t('pantry', 'Create') },
      // TRANSLATORS: Verb, permission checkbox for editing checklists.
      { key: 'canEditLists', label: t('pantry', 'Edit') },
      // TRANSLATORS: Verb, permission checkbox for deleting checklists.
      { key: 'canDeleteLists', label: t('pantry', 'Delete') },
    ],
  },
  {
    label: t('pantry', 'Checklist items'),
    caps: [
      // TRANSLATORS: Verb, permission checkbox for adding checklist items.
      { key: 'canAddItems', label: t('pantry', 'Add') },
      // TRANSLATORS: Verb, permission checkbox for marking checklist items as done.
      { key: 'canCheckItems', label: t('pantry', 'Check off') },
      // TRANSLATORS: Verb, permission checkbox for copying checklist items.
      { key: 'canCopyItems', label: t('pantry', 'Copy') },
      // TRANSLATORS: Verb, permission checkbox for moving checklist items.
      { key: 'canMoveItems', label: t('pantry', 'Move') },
      // TRANSLATORS: Verb, permission checkbox for deleting checklist items.
      { key: 'canDeleteItems', label: t('pantry', 'Delete') },
      // TRANSLATORS: Verb, permission checkbox for managing custom-field definitions.
      { key: 'canEditFields', label: t('pantry', 'Manage custom fields') },
    ],
  },
  {
    label: t('pantry', 'Photos'),
    caps: [
      // TRANSLATORS: Verb, permission checkbox for viewing photos.
      { key: 'canViewPhotos', label: t('pantry', 'View') },
      // TRANSLATORS: Verb, permission checkbox for uploading photos.
      { key: 'canUploadPhotos', label: t('pantry', 'Upload') },
      // TRANSLATORS: Verb, permission checkbox for editing photos.
      { key: 'canUpdatePhotos', label: t('pantry', 'Edit') },
      // TRANSLATORS: Verb, permission checkbox for organizing (moving) photos.
      { key: 'canMovePhotos', label: t('pantry', 'Organize') },
      // TRANSLATORS: Verb, permission checkbox for deleting photos.
      { key: 'canDeletePhotos', label: t('pantry', 'Delete') },
    ],
  },
  {
    label: t('pantry', 'Notes'),
    caps: [
      // TRANSLATORS: Verb, permission checkbox for viewing notes.
      { key: 'canViewNotes', label: t('pantry', 'View') },
      // TRANSLATORS: Verb, permission checkbox for creating notes.
      { key: 'canCreateNotes', label: t('pantry', 'Create') },
      // TRANSLATORS: Verb, permission checkbox for editing notes.
      { key: 'canUpdateNotes', label: t('pantry', 'Edit') },
      // TRANSLATORS: Verb, permission checkbox for deleting notes.
      { key: 'canDeleteNotes', label: t('pantry', 'Delete') },
    ],
  },
])

const allCapKeys = computed<CapabilityKey[]>(() =>
  capGroups.value.flatMap((g) => g.caps.map((c) => c.key)),
)

// Capability dependencies: a section's "view" capability gates the rest of that
// section. Checklist view additionally gates the checklist-item capabilities.
const CAP_PARENT: Partial<Record<CapabilityKey, CapabilityKey>> = {
  canCreateLists: 'canViewLists',
  canEditLists: 'canViewLists',
  canDeleteLists: 'canViewLists',
  canAddItems: 'canViewLists',
  canCheckItems: 'canViewLists',
  canCopyItems: 'canViewLists',
  canMoveItems: 'canViewLists',
  canDeleteItems: 'canViewLists',
  canEditFields: 'canViewLists',
  canUploadPhotos: 'canViewPhotos',
  canUpdatePhotos: 'canViewPhotos',
  canDeletePhotos: 'canViewPhotos',
  canMovePhotos: 'canViewPhotos',
  canCreateNotes: 'canViewNotes',
  canUpdateNotes: 'canViewNotes',
  canDeleteNotes: 'canViewNotes',
}
const CAP_CHILDREN: Partial<Record<CapabilityKey, CapabilityKey[]>> = Object.entries(
  CAP_PARENT,
).reduce<Partial<Record<CapabilityKey, CapabilityKey[]>>>((acc, [child, parent]) => {
  ;(acc[parent] ??= []).push(child as CapabilityKey)
  return acc
}, {})

function roleTypeLabel(roleType: Role['roleType']): string {
  switch (roleType) {
    case 'admin':
      return t('pantry', 'Built-in admin')
    case 'default':
      return t('pantry', 'Built-in member')
    default:
      // TRANSLATORS: Adjective, label marking a user-defined (non built-in) role.
      return t('pantry', 'Custom')
  }
}

// Working copy. Every edit stages locally; nothing hits the server until Save.
// Existing roles keep their real id; staged additions carry a negative temp id.
interface WorkingRole {
  id: number
  name: string
  roleType: Role['roleType']
  caps: Record<CapabilityKey, boolean>
  isNew: boolean
  deleted: boolean
}

const working = ref<WorkingRole[]>([])
const originals = new Map<number, { name: string; caps: Record<CapabilityKey, boolean> }>()
const expanded = ref<Set<number>>(new Set())
const saving = ref(false)
const saveError = ref<string | null>(null)
let tempId = -1

function capsOf(role: Role): Record<CapabilityKey, boolean> {
  const caps = {} as Record<CapabilityKey, boolean>
  for (const key of allCapKeys.value) {
    caps[key] = role[key]
  }
  return caps
}

function syncFromRoles() {
  originals.clear()
  working.value = roles.value.map((r) => {
    const caps = capsOf(r)
    originals.set(r.id, { name: r.name, caps: { ...caps } })
    return {
      id: r.id,
      name: r.name,
      roleType: r.roleType,
      caps,
      isNew: false,
      deleted: false,
    }
  })
  expanded.value = new Set()
  saveError.value = null
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) syncFromRoles()
  },
  { immediate: true },
)
// Reflect a server refresh (e.g. after Save) back into the working copy.
watch(roles, syncFromRoles)

// Presets first: built-in admin, then built-in member, then custom, then new.
const displayRoles = computed<WorkingRole[]>(() => {
  const rank = (r: WorkingRole) =>
    r.isNew ? 3 : r.roleType === 'admin' ? 0 : r.roleType === 'default' ? 1 : 2
  return [...working.value].sort((a, b) => rank(a) - rank(b) || a.id - b.id)
})

function isExpanded(id: number): boolean {
  return expanded.value.has(id)
}
function toggleExpanded(id: number) {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

function grantedCount(role: WorkingRole): number {
  return allCapKeys.value.reduce((n, key) => n + (role.caps[key] ? 1 : 0), 0)
}
function summaryFor(role: WorkingRole): string {
  if (role.roleType === 'default') return strings.defaultSummary
  return n('pantry', '%n permission granted', '%n permissions granted', grantedCount(role))
}

function setCap(role: WorkingRole, key: CapabilityKey, value: boolean) {
  role.caps[key] = value
  // Turning off a section's "view" cap forces its dependents off too.
  if (!value) {
    for (const child of CAP_CHILDREN[key] ?? []) {
      role.caps[child] = false
    }
  }
}
function capDisabled(role: WorkingRole, key: CapabilityKey): boolean {
  const parent = CAP_PARENT[key]
  return parent !== undefined && !role.caps[parent]
}

function addRole() {
  const caps = {} as Record<CapabilityKey, boolean>
  for (const key of allCapKeys.value) caps[key] = true
  const id = tempId--
  working.value = [
    ...working.value,
    { id, name: t('pantry', 'New role'), roleType: 'normal', caps, isNew: true, deleted: false },
  ]
  toggleExpanded(id)
}

function duplicateRole(role: WorkingRole) {
  const id = tempId--
  working.value = [
    ...working.value,
    {
      id,
      name: t('pantry', 'Copy of {name}', { name: role.name }),
      roleType: 'normal',
      caps: { ...role.caps },
      isNew: true,
      deleted: false,
    },
  ]
  toggleExpanded(id)
}

function toggleDelete(role: WorkingRole) {
  if (role.isNew) {
    working.value = working.value.filter((r) => r.id !== role.id)
    return
  }
  role.deleted = !role.deleted
}

// -------- Dirty tracking + Save --------
function capsEqual(a: Record<CapabilityKey, boolean>, b: Record<CapabilityKey, boolean>): boolean {
  return allCapKeys.value.every((key) => a[key] === b[key])
}
function isRoleDirty(role: WorkingRole): boolean {
  if (role.isNew || role.deleted) return true
  const orig = originals.get(role.id)
  if (!orig) return false
  return role.name.trim() !== orig.name || !capsEqual(role.caps, orig.caps)
}

const dirty = computed(() => working.value.some(isRoleDirty))

async function save() {
  if (!dirty.value || saving.value) return
  saving.value = true
  saveError.value = null
  try {
    for (const role of working.value) {
      if (role.isNew && !role.deleted) {
        await create(role.name.trim() || t('pantry', 'New role'), role.caps)
      } else if (role.deleted) {
        await remove(role.id)
      } else {
        const orig = originals.get(role.id)
        if (!orig) continue
        const patch: { name?: string; caps?: Record<CapabilityKey, boolean> } = {}
        if (role.name.trim() !== '' && role.name.trim() !== orig.name) patch.name = role.name.trim()
        if (!capsEqual(role.caps, orig.caps)) patch.caps = role.caps
        if (patch.name !== undefined || patch.caps !== undefined) {
          await update(role.id, patch)
        }
      }
    }
    emit('changed')
    emit('update:open', false)
  } catch (e) {
    saveError.value = (e as Error).message || t('pantry', 'Could not save roles.')
    await refresh()
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
  title: t('pantry', 'Roles'),
  hint: t(
    'pantry',
    'Define what each role can do. Assign roles to members from the Members dialog. Changes apply when you save.',
  ),
  roleNameLabel: t('pantry', 'Role name'),
  adminRoleHint: t('pantry', 'The Admin role always has every permission and cannot be changed.'),
  defaultSummary: t('pantry', 'Default role for new members.'),
  customize: t('pantry', 'Customize'),
  addRole: t('pantry', 'Add role'),
  duplicateRole: t('pantry', 'Duplicate role'),
  deleteRole: t('pantry', 'Delete role'),
  undoDelete: t('pantry', 'Undo delete'),
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

  &--small {
    font-size: 0.85em;
    margin: 0.25rem 0 0;
  }
}

// One grid for the whole list so every card's type-chip column shares a single
// width (the widest translated label). Each card is a subgrid so its header
// cells align to those shared tracks — table-like, but still bordered cards.
.pantry-roles {
  display: grid;
  grid-template-columns: 1fr max-content auto;
  gap: 0.75rem;
}

.pantry-role {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: subgrid;
  align-items: center;
  gap: 0.6rem 0.75rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);

  &--deleted {
    opacity: 0.6;
  }

  &__name {
    grid-column: 1;
    min-width: 0;
  }

  &__type {
    grid-column: 2;
  }

  &__actions {
    grid-column: 3;
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  &__hint {
    grid-column: 1 / -1;
    margin: 0;
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
  }

  &__caps {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding-top: 0.25rem;
  }

  &__group-label {
    font-weight: 600;
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
    margin-bottom: 0.25rem;
  }
}

.pantry-chip {
  display: block;
  width: 100%;
  text-align: center;
  padding: 0.2rem 0.6rem;
  border-radius: 1rem;
  font-size: 0.8em;
  font-weight: 500;
  white-space: nowrap;
  background: var(--color-background-dark);
  color: var(--color-text-maxcontrast);
  border: 1px solid var(--color-border);
}

.pantry-expander {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  background: var(--color-background-hover);
  color: var(--color-main-text);
  cursor: pointer;
  text-align: start;

  &:hover,
  &:focus-visible {
    background: var(--color-background-dark);
  }

  &__text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
  }

  &__title {
    font-weight: 500;
  }

  &__summary {
    font-size: 0.85em;
    color: var(--color-text-maxcontrast);
  }
}

.pantry-role-add {
  margin-top: 1rem;
}

.pantry-form-error {
  color: var(--color-error);
  margin: 0.5rem 0 0;
}
</style>
