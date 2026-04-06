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

    <NcAppSettingsSection id="house-categories" :name="strings.categoriesSection">
      <div v-if="catLoading" class="pantry-center">
        <NcLoadingIcon :size="28" />
      </div>
      <template v-else>
        <p v-if="catItems.length === 0" class="pantry-hint">
          {{ strings.noCategoriesHint }}
        </p>
        <ul v-else class="pantry-cat-list">
          <li v-for="cat in catItems" :key="cat.id" class="pantry-cat-list__item">
            <span class="pantry-cat-list__icon" :style="{ color: cat.color }">
              <component :is="categoryIconComponent(cat.icon)" :size="20" />
            </span>
            <span class="pantry-cat-list__name">{{ cat.name }}</span>
            <div class="pantry-cat-list__actions">
              <NcButton
                variant="tertiary"
                :aria-label="strings.editCategory"
                @click="startEditCat(cat)"
              >
                <template #icon><PencilIcon :size="18" /></template>
              </NcButton>
              <NcButton
                variant="tertiary"
                :aria-label="strings.deleteCategory"
                @click="confirmDeleteCat(cat)"
              >
                <template #icon><DeleteIcon :size="18" /></template>
              </NcButton>
            </div>
          </li>
        </ul>
        <div class="pantry-cat-add">
          <NcButton variant="primary" @click="openCreateCat">
            <template #icon><PlusIcon :size="20" /></template>
            {{ strings.newCategory }}
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
      <NcTextField
        v-model="newUserId"
        :label="strings.userIdLabel"
        :placeholder="strings.userIdPlaceholder"
        autocomplete="off"
      />
      <NcSelect v-model="newRoleOption" :options="roleOptions" :input-label="strings.roleLabel" />
      <p v-if="addError" class="pantry-form-error">{{ addError }}</p>
    </form>
    <template #actions>
      <NcButton @click="showAdd = false">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="!newUserId.trim()" @click="submitAdd">
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

  <NcDialog
    v-if="showCreateCat"
    :name="strings.createCategoryTitle"
    :open="showCreateCat"
    close-on-click-outside
    @update:open="showCreateCat = $event"
  >
    <form class="pantry-cat-form" autocomplete="off" @submit.prevent="submitCreateCat">
      <NcTextField
        v-model="catName"
        :label="strings.catNameLabel"
        :placeholder="strings.catNamePlaceholder"
        autocomplete="off"
      />
      <div>
        <label class="pantry-cat-form__sub">{{ strings.iconLabel }}</label>
        <div class="pantry-cat-form__icon-grid">
          <button
            v-for="opt in CATEGORY_ICONS"
            :key="opt.key"
            type="button"
            class="pantry-cat-form__icon-button"
            :class="{ 'pantry-cat-form__icon-button--active': catIcon === opt.key }"
            :title="opt.label"
            :style="{ color: catColor }"
            @click="catIcon = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
      </div>
      <div>
        <label class="pantry-cat-form__sub">{{ strings.colorLabel }}</label>
        <div class="pantry-cat-form__color-grid">
          <button
            v-for="c in CATEGORY_COLORS"
            :key="c"
            type="button"
            class="pantry-cat-form__color-swatch"
            :class="{ 'pantry-cat-form__color-swatch--active': catColor === c }"
            :style="{ backgroundColor: c }"
            :aria-label="c"
            @click="catColor = c"
          />
        </div>
      </div>
      <p v-if="catError" class="pantry-form-error">{{ catError }}</p>
    </form>
    <template #actions>
      <NcButton @click="showCreateCat = false">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="catSaving || !catName.trim()" @click="submitCreateCat">
        {{ catSaving ? strings.saving : strings.createCategory }}
      </NcButton>
    </template>
  </NcDialog>

  <NcDialog
    v-if="editingCat"
    :name="strings.editCategoryTitle"
    :open="!!editingCat"
    close-on-click-outside
    @update:open="(v) => !v && (editingCat = null)"
  >
    <form class="pantry-cat-form" autocomplete="off" @submit.prevent="submitEditCat">
      <NcTextField
        v-model="catName"
        :label="strings.catNameLabel"
        :placeholder="strings.catNamePlaceholder"
        autocomplete="off"
      />
      <div>
        <label class="pantry-cat-form__sub">{{ strings.iconLabel }}</label>
        <div class="pantry-cat-form__icon-grid">
          <button
            v-for="opt in CATEGORY_ICONS"
            :key="opt.key"
            type="button"
            class="pantry-cat-form__icon-button"
            :class="{ 'pantry-cat-form__icon-button--active': catIcon === opt.key }"
            :title="opt.label"
            :style="{ color: catColor }"
            @click="catIcon = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
      </div>
      <div>
        <label class="pantry-cat-form__sub">{{ strings.colorLabel }}</label>
        <div class="pantry-cat-form__color-grid">
          <button
            v-for="c in CATEGORY_COLORS"
            :key="c"
            type="button"
            class="pantry-cat-form__color-swatch"
            :class="{ 'pantry-cat-form__color-swatch--active': catColor === c }"
            :style="{ backgroundColor: c }"
            :aria-label="c"
            @click="catColor = c"
          />
        </div>
      </div>
      <p v-if="catError" class="pantry-form-error">{{ catError }}</p>
    </form>
    <template #actions>
      <NcButton @click="editingCat = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="catSaving || !catName.trim()" @click="submitEditCat">
        {{ catSaving ? strings.saving : strings.save }}
      </NcButton>
    </template>
  </NcDialog>

  <NcDialog
    v-if="deletingCat"
    :name="strings.deleteCategoryTitle"
    :open="!!deletingCat"
    close-on-click-outside
    @update:open="(v) => !v && (deletingCat = null)"
  >
    <p>{{ deleteCatConfirmBody }}</p>
    <template #actions>
      <NcButton @click="deletingCat = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="submitDeleteCat">{{ strings.deleteCategory }}</NcButton>
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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import PencilIcon from '@icons/Pencil.vue'
import * as houseApi from '@/api/houses'
import type { Category, HouseMember, HouseRole } from '@/api/types'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useHouses } from '@/composables/useHouses'
import { useCategories } from '@/composables/useCategories'
import {
  CATEGORY_COLORS,
  CATEGORY_ICONS,
  DEFAULT_CATEGORY_ICON_KEY,
  categoryIconComponent,
} from '@/components/CategoryPicker/categoryIcons'

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

function syncFromHouse() {
  if (house.value) {
    name.value = house.value.name
    description.value = house.value.description ?? ''
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      syncFromHouse()
      void loadMembers()
      void loadCategories()
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
      description: description.value.trim() || null,
    })
    await refresh()
  } finally {
    savingGeneral.value = false
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
const newUserId = ref('')
const newRoleOption = ref<RoleOption>(roleOptions.value[0]!)
const addError = ref<string | null>(null)

async function submitAdd() {
  const id = houseIdNum.value
  const uid = newUserId.value.trim()
  if (!uid || id === null) return
  addError.value = null
  try {
    const role: HouseRole = newRoleOption.value?.value ?? 'member'
    const member = await houseApi.addMember(id, uid, role)
    members.value = [...members.value, member]
    showAdd.value = false
    newUserId.value = ''
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

// -------- Categories --------
const catComposable = computed(() => {
  const id = houseIdNum.value
  return id !== null ? useCategories(id) : null
})
const catItems = computed(() => catComposable.value?.items.value ?? [])
const catLoading = computed(() => catComposable.value?.loading.value ?? false)

async function loadCategories() {
  await catComposable.value?.load(true)
}

const showCreateCat = ref(false)
const editingCat = ref<Category | null>(null)
const deletingCat = ref<Category | null>(null)
const catName = ref('')
const catIcon = ref(DEFAULT_CATEGORY_ICON_KEY)
const catColor = ref(CATEGORY_COLORS[3]!)
const catSaving = ref(false)
const catError = ref<string | null>(null)

function openCreateCat() {
  catName.value = ''
  catIcon.value = DEFAULT_CATEGORY_ICON_KEY
  catColor.value = CATEGORY_COLORS[3]!
  catError.value = null
  showCreateCat.value = true
}

function startEditCat(cat: Category) {
  editingCat.value = cat
  catName.value = cat.name
  catIcon.value = cat.icon
  catColor.value = cat.color
  catError.value = null
}

function confirmDeleteCat(cat: Category) {
  deletingCat.value = cat
}

const deleteCatConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the category "{name}"?', {
    name: deletingCat.value?.name ?? '',
  }),
)

async function submitCreateCat() {
  const name = catName.value.trim()
  if (!name) return
  catSaving.value = true
  catError.value = null
  try {
    await catComposable.value?.create({ name, icon: catIcon.value, color: catColor.value })
    showCreateCat.value = false
  } catch (e) {
    catError.value = (e as Error).message || t('pantry', 'Could not create category.')
  } finally {
    catSaving.value = false
  }
}

async function submitEditCat() {
  const target = editingCat.value
  if (!target) return
  const name = catName.value.trim()
  if (!name) return
  catSaving.value = true
  catError.value = null
  try {
    await catComposable.value?.update(target.id, {
      name,
      icon: catIcon.value,
      color: catColor.value,
    })
    editingCat.value = null
  } catch (e) {
    catError.value = (e as Error).message || t('pantry', 'Could not update category.')
  } finally {
    catSaving.value = false
  }
}

async function submitDeleteCat() {
  const target = deletingCat.value
  if (!target) return
  await catComposable.value?.remove(target.id)
  deletingCat.value = null
}

// -------- Danger zone --------
const confirmingDelete = ref(false)

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
  membersSection: t('pantry', 'Members'),
  addMember: t('pantry', 'Add member'),
  removeMember: t('pantry', 'Remove member'),
  leaveButton: t('pantry', 'Leave this house'),
  colUser: t('pantry', 'Account'),
  colRole: t('pantry', 'Role'),
  colJoined: t('pantry', 'Joined'),
  addDialogTitle: t('pantry', 'Add a member'),
  userIdLabel: t('pantry', 'Account ID'),
  userIdPlaceholder: t('pantry', 'The Nextcloud username'),
  roleLabel: t('pantry', 'Role'),
  categoriesSection: t('pantry', 'Categories'),
  noCategoriesHint: t('pantry', 'No categories yet. Categories help organize checklist items.'),
  newCategory: t('pantry', 'New category'),
  createCategory: t('pantry', 'Create'),
  createCategoryTitle: t('pantry', 'New category'),
  editCategory: t('pantry', 'Edit'),
  editCategoryTitle: t('pantry', 'Edit category'),
  deleteCategory: t('pantry', 'Delete'),
  deleteCategoryTitle: t('pantry', 'Delete category'),
  catNameLabel: t('pantry', 'Name'),
  catNamePlaceholder: t('pantry', 'e.g. Produce, Dairy'),
  iconLabel: t('pantry', 'Icon:'),
  colorLabel: t('pantry', 'Color:'),
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
}

.pantry-cat-list {
  list-style: none;
  padding: 0;
  margin: 0;

  &__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border);
  }

  &__icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__actions {
    display: flex;
    gap: 0;
    flex-shrink: 0;
  }
}

.pantry-cat-add {
  margin-top: 1rem;
}

.pantry-cat-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
  min-width: 340px;

  &__sub {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    margin-bottom: 0.35rem;
  }

  &__icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 0.35rem;
  }

  &__icon-button {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
      background: var(--color-background-hover);
    }

    &--active {
      border-color: currentColor;
      box-shadow: 0 0 0 2px currentColor;
    }
  }

  &__color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease;

    &:hover {
      transform: scale(1.08);
    }

    &--active {
      border-color: var(--color-main-text);
      transform: scale(1.1);
    }
  }
}

@media (max-width: 500px) {
  .pantry-cat-form {
    min-width: 0;
  }
}
</style>
