<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="catLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p v-if="catItems.length === 0" class="pantry-cat-hint">
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
    </template>
    <template #actions>
      <NcButton variant="primary" @click="openCreateCat">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.newCategory }}
      </NcButton>
    </template>
  </NcDialog>

  <!-- Create/edit form -->
  <CategoryFormDialog
    :open="showForm"
    :category="editingCat"
    :saving="catSaving"
    :error="catError"
    @update:open="closeForm"
    @save="submitForm"
  />

  <!-- Delete category confirm -->
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
      <NcButton variant="error" @click="submitDeleteCat">{{ strings.delete }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import PencilIcon from '@icons/Pencil.vue'
import type { Category } from '@/api/types'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import CategoryFormDialog from './CategoryFormDialog.vue'

const props = defineProps<{ open: boolean; houseId: number }>()
defineEmits<{ 'update:open': [value: boolean] }>()

const categories = useCategories(props.houseId)
const catItems = computed(() => categories.items.value)
const catLoading = computed(() => categories.loading.value)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) categories.load()
  },
  { immediate: true },
)

// -------- Form state --------
const showForm = ref(false)
const editingCat = ref<Category | null>(null)
const deletingCat = ref<Category | null>(null)
const catSaving = ref(false)
const catError = ref<string | null>(null)

function openCreateCat() {
  editingCat.value = null
  catError.value = null
  showForm.value = true
}

function startEditCat(cat: Category) {
  editingCat.value = cat
  catError.value = null
  showForm.value = true
}

function closeForm(v: boolean) {
  if (!v) {
    showForm.value = false
    editingCat.value = null
  }
}

function confirmDeleteCat(cat: Category) {
  deletingCat.value = cat
}

const deleteCatConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the category "{name}"?', {
    name: deletingCat.value?.name ?? '',
  }),
)

async function submitForm(data: { name: string; icon: string; color: string }) {
  catSaving.value = true
  catError.value = null
  try {
    if (editingCat.value) {
      await categories.update(editingCat.value.id, data)
    } else {
      await categories.create(data)
    }
    showForm.value = false
    editingCat.value = null
  } catch (e) {
    catError.value =
      (e as Error).message ||
      (editingCat.value
        ? t('pantry', 'Could not update category.')
        : t('pantry', 'Could not create category.'))
  } finally {
    catSaving.value = false
  }
}

async function submitDeleteCat() {
  const target = deletingCat.value
  if (!target) return
  await categories.remove(target.id)
  deletingCat.value = null
}

const strings = {
  title: t('pantry', 'Manage categories'),
  noCategoriesHint: t('pantry', 'No categories yet. Categories help organize checklist items.'),
  newCategory: t('pantry', 'New category'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  editCategory: t('pantry', 'Edit'),
  deleteCategory: t('pantry', 'Delete'),
  deleteCategoryTitle: t('pantry', 'Delete category'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-cat-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-cat-list {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem 0;

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
</style>
