<template>
  <div class="pantry-lists">
    <PageToolbar :title="strings.title">
      <template #actions>
        <NcButton variant="primary" @click="showCreate = true">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-lists__body">
      <div v-if="loading" class="pantry-center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="lists.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <ClipboardCheckIcon />
        </template>
        <template #action>
          <NcButton variant="primary" @click="showCreate = true">
            {{ strings.newList }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <ul v-else class="pantry-lists__grid">
        <li v-for="list in lists" :key="list.id" class="pantry-list-card-wrap">
          <router-link
            :to="{
              name: 'list-detail',
              params: { houseId: String(houseIdNum), listId: String(list.id) },
            }"
            class="pantry-list-card"
          >
            <component
              :is="checklistIconComponent(list.icon)"
              :size="28"
              class="pantry-list-card__icon"
            />
            <div class="pantry-list-card__body">
              <h3>{{ list.name }}</h3>
              <p v-if="list.description">{{ list.description }}</p>
            </div>
          </router-link>
          <NcActions class="pantry-list-card__actions" :aria-label="strings.listMenu">
            <NcActionButton @click="startEdit(list)">
              <template #icon><PencilIcon :size="20" /></template>
              {{ strings.edit }}
            </NcActionButton>
            <NcActionButton @click="confirmDelete(list)">
              <template #icon><DeleteIcon :size="20" /></template>
              {{ strings.delete }}
            </NcActionButton>
          </NcActions>
        </li>
      </ul>
    </div>

    <NcDialog
      v-if="showCreate"
      :name="strings.createDialogTitle"
      :open="showCreate"
      close-on-click-outside
      @update:open="showCreate = $event"
    >
      <form
        id="pantry-create-list-form"
        class="pantry-form"
        autocomplete="off"
        @submit.prevent="submitCreate"
      >
        <NcTextField
          v-model="newName"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
        <NcTextField
          v-model="newDescription"
          :label="strings.descriptionLabel"
          :placeholder="strings.descriptionPlaceholder"
          autocomplete="off"
        />
        <div>
          <label class="pantry-icon-picker__label">{{ strings.iconLabel }}</label>
          <div class="pantry-icon-picker__grid">
            <button
              v-for="opt in CHECKLIST_ICONS"
              :key="opt.key"
              type="button"
              class="pantry-icon-picker__button"
              :class="{ 'pantry-icon-picker__button--active': newIcon === opt.key }"
              :title="opt.label"
              @click="newIcon = opt.key"
            >
              <component :is="opt.component" :size="20" />
            </button>
          </div>
        </div>
      </form>
      <template #actions>
        <NcButton @click="showCreate = false">{{ strings.cancel }}</NcButton>
        <NcButton
          form="pantry-create-list-form"
          type="submit"
          variant="primary"
          :disabled="!newName.trim()"
        >
          {{ strings.create }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="editing"
      :name="strings.editDialogTitle"
      :open="!!editing"
      close-on-click-outside
      @update:open="(v) => !v && (editing = null)"
    >
      <form class="pantry-form" autocomplete="off" @submit.prevent="submitEdit">
        <NcTextField
          v-model="editName"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
          autocomplete="off"
        />
        <NcTextField
          v-model="editDescription"
          :label="strings.descriptionLabel"
          :placeholder="strings.descriptionPlaceholder"
          autocomplete="off"
        />
        <div>
          <label class="pantry-icon-picker__label">{{ strings.iconLabel }}</label>
          <div class="pantry-icon-picker__grid">
            <button
              v-for="opt in CHECKLIST_ICONS"
              :key="opt.key"
              type="button"
              class="pantry-icon-picker__button"
              :class="{ 'pantry-icon-picker__button--active': editIcon === opt.key }"
              :title="opt.label"
              @click="editIcon = opt.key"
            >
              <component :is="opt.component" :size="20" />
            </button>
          </div>
        </div>
      </form>
      <template #actions>
        <NcButton @click="editing = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="!editName.trim()" @click="submitEdit">
          {{ strings.save }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="deleting"
      :name="strings.deleteDialogTitle"
      :open="!!deleting"
      close-on-click-outside
      @update:open="(v) => !v && (deleting = null)"
    >
      <p>{{ deleteConfirmBody }}</p>
      <template #actions>
        <NcButton @click="deleting = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitDelete">{{ strings.delete }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PageToolbar from '@/components/PageToolbar'
import PlusIcon from '@icons/Plus.vue'
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import type { Checklist } from '@/api/types'
import { useChecklists } from '@/composables/useChecklist'
import {
  CHECKLIST_ICONS,
  DEFAULT_CHECKLIST_ICON_KEY,
  checklistIconComponent,
} from '@/components/ChecklistIconPicker'

const props = defineProps<{ houseId: string }>()
const router = useRouter()

const houseIdNum = computed(() => Number(props.houseId))
const { lists, loading, load, create, update, remove } = useChecklists(houseIdNum.value)

onMounted(load)
watch(
  () => props.houseId,
  () => load(),
)

const showCreate = ref(false)
const newName = ref('')
const newDescription = ref('')
const newIcon = ref(DEFAULT_CHECKLIST_ICON_KEY)

async function submitCreate() {
  const name = newName.value.trim()
  if (!name) return
  const list = await create(name, newDescription.value.trim() || null, newIcon.value)
  showCreate.value = false
  newName.value = ''
  newDescription.value = ''
  newIcon.value = DEFAULT_CHECKLIST_ICON_KEY
  await router.push({
    name: 'list-detail',
    params: { houseId: String(houseIdNum.value), listId: String(list.id) },
  })
}

const editing = ref<Checklist | null>(null)
const editName = ref('')
const editDescription = ref('')
const editIcon = ref(DEFAULT_CHECKLIST_ICON_KEY)

function startEdit(list: Checklist) {
  editing.value = list
  editName.value = list.name
  editDescription.value = list.description ?? ''
  editIcon.value = list.icon || DEFAULT_CHECKLIST_ICON_KEY
}

async function submitEdit() {
  const target = editing.value
  if (!target) return
  const name = editName.value.trim()
  if (!name) return
  await update(target.id, {
    name,
    description: editDescription.value.trim() || null,
    icon: editIcon.value,
  })
  editing.value = null
}

const deleting = ref<Checklist | null>(null)
const deleteConfirmBody = computed(() =>
  t(
    'pantry',
    'Are you sure you want to delete {name}? All items in this list will also be removed.',
    { name: deleting.value?.name ?? '' },
  ),
)

function confirmDelete(list: Checklist) {
  deleting.value = list
}

async function submitDelete() {
  const target = deleting.value
  if (!target) return
  await remove(target.id)
  deleting.value = null
}

const strings = {
  title: t('pantry', 'Checklists'),
  newList: t('pantry', 'New list'),
  create: t('pantry', 'Create'),
  save: t('pantry', 'Save'),
  cancel: t('pantry', 'Cancel'),
  edit: t('pantry', 'Edit'),
  delete: t('pantry', 'Delete'),
  listMenu: t('pantry', 'List actions'),
  createDialogTitle: t('pantry', 'Create a checklist'),
  editDialogTitle: t('pantry', 'Edit checklist'),
  deleteDialogTitle: t('pantry', 'Delete checklist'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Weekly groceries'),
  descriptionLabel: t('pantry', 'Description (optional)'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  iconLabel: t('pantry', 'Icon:'),
  emptyTitle: t('pantry', 'No lists yet'),
  emptyBody: t('pantry', 'Create your first checklist to start adding items.'),
}
</script>

<style scoped lang="scss">
.pantry-lists {
  &__body {
    max-width: 1100px;
    margin: 0 auto;
  }

  &__grid {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }
}

.pantry-list-card-wrap {
  position: relative;

  &__actions,
  .pantry-list-card__actions {
    position: absolute;
    top: 0.5rem;
    inset-inline-end: 0.5rem;
    z-index: 1;
  }
}

.pantry-list-card {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  padding-inline-end: 3rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  background: var(--color-main-background);
  color: inherit;
  text-decoration: none;
  transition: background-color 0.15s ease;

  &:hover,
  &:focus-visible {
    background: var(--color-background-hover);
  }

  &__icon {
    color: var(--color-primary-element);
  }

  &__body {
    flex: 1;
    min-width: 0;

    h3 {
      margin: 0 0 4px 0;
      font-size: 1.05rem;
    }

    p {
      margin: 0;
      color: var(--color-text-maxcontrast);
      font-size: 0.9rem;
    }
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

.pantry-icon-picker {
  &__label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.35rem;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 0.35rem;
  }

  &__button {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid transparent;
    border-radius: var(--border-radius, 4px);
    background: var(--color-background-dark);
    color: var(--color-main-text);
    cursor: pointer;
    transition:
      border-color 0.15s,
      background 0.15s;

    &:hover {
      background: var(--color-background-hover);
    }

    &--active {
      border-color: var(--color-primary-element);
      background: var(--color-primary-element-light);
    }
  }
}
</style>
