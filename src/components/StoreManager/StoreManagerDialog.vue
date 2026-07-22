<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="storeLoading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p v-if="storeItems.length === 0" class="pantry-store-hint">
        {{ strings.noStoresHint }}
      </p>
      <ul v-else class="pantry-store-list">
        <li v-for="store in storeItems" :key="store.id" class="pantry-store-list__item">
          <button type="button" class="pantry-store-list__main" @click="viewStore(store)">
            <span class="pantry-store-list__icon" :style="{ color: store.color }">
              <component :is="storeIconComponent(store.icon)" :size="20" />
            </span>
            <span class="pantry-store-list__name">{{ store.name }}</span>
          </button>
          <div class="pantry-store-list__actions">
            <NcButton variant="tertiary" :aria-label="strings.editStore" @click="startEdit(store)">
              <template #icon><PencilIcon :size="18" /></template>
            </NcButton>
            <NcButton
              variant="tertiary"
              :aria-label="strings.deleteStore"
              @click="confirmDelete(store)"
            >
              <template #icon><DeleteIcon :size="18" /></template>
            </NcButton>
          </div>
        </li>
      </ul>
    </template>
    <template #actions>
      <NcButton variant="primary" @click="openCreate">
        <template #icon><PlusIcon :size="20" /></template>
        {{ strings.newStore }}
      </NcButton>
    </template>
  </NcDialog>

  <!-- Store details -->
  <StoreViewDialog
    v-if="viewingStore"
    :open="!!viewingStore"
    :store="viewingStore"
    @update:open="(v) => !v && (viewingStore = null)"
    @edit="editFromView"
  />

  <!-- Create/edit form -->
  <StoreFormDialog
    :open="showForm"
    :store="editingStore"
    :saving="storeSaving"
    :error="storeError"
    @update:open="closeForm"
    @save="submitForm"
  />

  <!-- Delete store confirm -->
  <NcDialog
    v-if="deletingStore"
    :name="strings.deleteStoreTitle"
    :open="!!deletingStore"
    close-on-click-outside
    @update:open="(v) => !v && (deletingStore = null)"
  >
    <p>{{ deleteConfirmBody }}</p>
    <template #actions>
      <NcButton @click="deletingStore = null">{{ strings.cancel }}</NcButton>
      <NcButton variant="error" @click="submitDelete">{{ strings.delete }}</NcButton>
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
import type { Store } from '@/api/types'
import type { StoreInput } from '@/api/stores'
import { useStores } from '@/composables/useStores'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import StoreFormDialog from './StoreFormDialog.vue'
import StoreViewDialog from './StoreViewDialog.vue'

const props = defineProps<{
  open: boolean
  houseId: number
  /** When set while the dialog opens, jump straight into editing this store. */
  editStore?: Store | null
}>()
defineEmits<{
  'update:open': [value: boolean]
}>()

const stores = useStores(props.houseId)
const storeItems = computed(() => stores.items.value)
const storeLoading = computed(() => stores.loading.value)

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      await stores.load()
    }
  },
  { immediate: true },
)

// Jump straight into the edit form when the parent opens us with a target store.
watch(
  [() => props.open, () => props.editStore],
  ([isOpen, store]) => {
    if (isOpen && store) {
      startEdit(store)
    }
  },
  { immediate: true },
)

// -------- Form state --------
const showForm = ref(false)
const editingStore = ref<Store | null>(null)
const viewingStore = ref<Store | null>(null)
const deletingStore = ref<Store | null>(null)
const storeSaving = ref(false)
const storeError = ref<string | null>(null)

function openCreate() {
  editingStore.value = null
  storeError.value = null
  showForm.value = true
}

function viewStore(store: Store) {
  viewingStore.value = store
}

function startEdit(store: Store) {
  editingStore.value = store
  storeError.value = null
  showForm.value = true
}

function editFromView(store: Store) {
  viewingStore.value = null
  startEdit(store)
}

function closeForm(v: boolean) {
  if (!v) {
    showForm.value = false
    editingStore.value = null
  }
}

function confirmDelete(store: Store) {
  deletingStore.value = store
}

const deleteConfirmBody = computed(() =>
  t('pantry', 'Are you sure you want to delete the store "{name}"?', {
    name: deletingStore.value?.name ?? '',
  }),
)

async function submitForm(data: StoreInput) {
  storeSaving.value = true
  storeError.value = null
  try {
    if (editingStore.value) {
      await stores.update(editingStore.value.id, data)
    } else {
      await stores.create(data)
    }
    showForm.value = false
    editingStore.value = null
  } catch (e) {
    storeError.value =
      (e as Error).message ||
      (editingStore.value
        ? t('pantry', 'Could not update store.')
        : t('pantry', 'Could not create store.'))
  } finally {
    storeSaving.value = false
  }
}

async function submitDelete() {
  const target = deletingStore.value
  if (!target) return
  await stores.remove(target.id)
  deletingStore.value = null
}

const strings = {
  // TRANSLATORS: Noun (plural), shops where items are bought. Dialog title.
  title: t('pantry', 'Manage stores'),
  noStoresHint: t('pantry', 'No stores yet. Stores let you mark where each item can be bought.'),
  // TRANSLATORS: Noun, a shop where items are bought. Button label.
  newStore: t('pantry', 'New store'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  editStore: t('pantry', 'Edit'),
  deleteStore: t('pantry', 'Delete'),
  // TRANSLATORS: Noun, a shop where items are bought. Dialog title.
  deleteStoreTitle: t('pantry', 'Delete store'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.pantry-store-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
}

.pantry-store-list {
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

  &__main {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 6px 8px;
    margin: -6px 0;
    border: none;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: start;
    cursor: pointer;
    border-radius: var(--border-radius, 8px);

    &:hover {
      background: var(--color-background-hover);
    }
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
