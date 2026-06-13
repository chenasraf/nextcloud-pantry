<template>
  <div class="pantry-lists">
    <PageToolbar :title="strings.title">
      <template #actions>
        <NcButton variant="primary" @click="showCategoryManager = true">
          <template #icon>
            <TagIcon :size="20" />
          </template>
          {{ strings.manageCategories }}
        </NcButton>
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
            <span class="pantry-list-card__icon-wrap" :style="iconWrapStyle(list.color)">
              <component
                :is="checklistIconComponent(list.icon)"
                :size="28"
                class="pantry-list-card__icon"
              />
            </span>
            <div class="pantry-list-card__body">
              <h3>{{ list.name }}</h3>
              <p v-if="list.description">{{ list.description }}</p>
            </div>
          </router-link>
          <NcActions class="pantry-list-card__actions" :aria-label="strings.listMenu">
            <NcActionButton close-after-click @click="startEdit(list)">
              <template #icon><PencilIcon :size="20" /></template>
              {{ strings.edit }}
            </NcActionButton>
            <NcActionButton close-after-click @click="confirmDelete(list)">
              <template #icon><DeleteIcon :size="20" /></template>
              {{ strings.delete }}
            </NcActionButton>
          </NcActions>
        </li>
      </ul>
    </div>

    <ChecklistFormDialog
      :open="showCreate"
      @update:open="showCreate = $event"
      @save="submitCreate"
    />

    <ChecklistFormDialog
      :open="!!editing"
      :list="editing"
      @update:open="(v) => !v && (editing = null)"
      @save="submitEdit"
    />

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

    <CategoryManagerDialog
      :open="showCategoryManager"
      :house-id="houseIdNum"
      @update:open="showCategoryManager = $event"
    />
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
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PageToolbar from '@/components/PageToolbar'
import { CategoryManagerDialog } from '@/components/CategoryManager'
import PlusIcon from '@icons/Plus.vue'
import TagIcon from '@icons/Tag.vue'
import ClipboardCheckIcon from '@icons/ClipboardCheck.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import type { Checklist } from '@/api/types'
import { useChecklists } from '@/composables/useChecklist'
import {
  checklistIconComponent,
  ChecklistFormDialog,
  contrastColor,
} from '@/components/ChecklistIconPicker'

function iconWrapStyle(color: string | null) {
  if (!color) return undefined
  return { background: color, color: contrastColor(color) }
}

const props = defineProps<{ houseId: string }>()
const router = useRouter()

const houseIdNum = computed(() => Number(props.houseId))
const { lists, loading, load, create, update, remove } = useChecklists(houseIdNum.value)

onMounted(load)
watch(
  () => props.houseId,
  () => load(),
)

const showCategoryManager = ref(false)

const showCreate = ref(false)

async function submitCreate(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const list = await create(data.name, data.description || null, data.icon, data.color || null)
  showCreate.value = false
  await router.push({
    name: 'list-detail',
    params: { houseId: String(houseIdNum.value), listId: String(list.id) },
  })
}

const editing = ref<Checklist | null>(null)

function startEdit(list: Checklist) {
  editing.value = list
}

async function submitEdit(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const target = editing.value
  if (!target) return
  await update(target.id, {
    name: data.name,
    description: data.description,
    icon: data.icon,
    color: data.color || null,
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
  manageCategories: t('pantry', 'Manage categories'),
  cancel: t('pantry', 'Cancel'),
  edit: t('pantry', 'Edit'),
  delete: t('pantry', 'Delete'),
  listMenu: t('pantry', 'List actions'),
  deleteDialogTitle: t('pantry', 'Delete checklist'),
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

  &__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);
  }

  &__icon {
    color: inherit;
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
</style>
