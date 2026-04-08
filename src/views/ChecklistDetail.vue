<template>
  <div class="pantry-detail">
    <PageToolbar :title="list?.name">
      <template #before-title>
        <NcButton
          variant="tertiary"
          :aria-label="strings.back"
          @click="$router.push({ name: 'lists', params: { houseId } })"
        >
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-detail__body">
      <ChecklistAddForm :house-id="houseIdNum" :adding="adding" @add="handleAdd" />

      <div v-if="loading" class="pantry-detail__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="strings.emptyTitle"
        :description="strings.emptyBody"
      >
        <template #icon>
          <component :is="checklistIconComponent(list?.icon)" />
        </template>
      </NcEmptyContent>

      <ul v-else class="pantry-detail__items">
        <ChecklistItemRow
          v-for="item in sortedItems"
          :key="item.id"
          :item="item"
          :category="categoryFor(item.categoryId)"
          :house-id="houseIdNum"
          @toggle="handleToggle"
          @view="openView"
          @edit="startEdit"
          @remove="handleRemove"
          @preview="openPreview"
        />
      </ul>
    </div>

    <ChecklistItemEditDialog
      v-if="editing"
      :open="!!editing"
      :item="editing"
      :house-id="houseIdNum"
      :saving="savingEdit"
      @update:open="(v) => !v && (editing = null)"
      @save="handleSaveEdit"
    />

    <ChecklistItemViewDialog
      v-if="viewing"
      :open="!!viewing"
      :item="viewing"
      :category="categoryFor(viewing.categoryId)"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (viewing = null)"
      @edit="viewToEdit"
      @preview="openPreview"
    />

    <ChecklistImagePreview
      v-if="previewing"
      :open="!!previewing"
      :item="previewing"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (previewing = null)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PageToolbar from '@/components/PageToolbar'
import { ChecklistAddForm } from '@/components/ChecklistAddForm'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistItemEditDialog } from '@/components/ChecklistItemEditDialog'
import { ChecklistItemViewDialog } from '@/components/ChecklistItemViewDialog'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { checklistIconComponent } from '@/components/ChecklistIconPicker'
import { useChecklistItems } from '@/composables/useChecklist'
import { useCategories } from '@/composables/useCategories'
import { getList } from '@/api/lists'
import type { ItemInput } from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'

const props = defineProps<{ houseId: string; listId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const listIdNum = computed(() => Number(props.listId))

const list = ref<Checklist | null>(null)
const { items, loading, load, add, update, toggle, remove, uploadImage, clearImage } =
  useChecklistItems(houseIdNum.value, listIdNum.value)
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

// ----- Loading -----

async function loadList() {
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

onMounted(async () => {
  await Promise.all([loadList(), load(), categories.load()])
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    await Promise.all([loadList(), load()])
  },
)

// ----- Sorting -----

const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => {
    if (a.done !== b.done) return a.done ? 1 : -1
    if (a.sortOrder !== b.sortOrder) return a.sortOrder - b.sortOrder
    return a.name.localeCompare(b.name)
  })
})

// ----- Add -----

const adding = ref(false)

async function handleAdd(input: ItemInput) {
  adding.value = true
  try {
    await add(input)
  } finally {
    adding.value = false
  }
}

// ----- Toggle / Remove -----

async function handleToggle(itemId: number) {
  await toggle(itemId)
}

async function handleRemove(itemId: number) {
  await remove(itemId)
}

// ----- Edit -----

const editing = ref<ChecklistItem | null>(null)
const savingEdit = ref(false)

function startEdit(item: ChecklistItem) {
  editing.value = item
}

async function handleSaveEdit(
  itemId: number,
  patch: Partial<ItemInput>,
  pendingImage: File | null,
  shouldClearImage: boolean,
) {
  savingEdit.value = true
  try {
    await update(itemId, patch)
    if (pendingImage) {
      await uploadImage(itemId, pendingImage)
    } else if (shouldClearImage) {
      await clearImage(itemId)
    }
    editing.value = null
  } finally {
    savingEdit.value = false
  }
}

// ----- View / Preview -----

const viewing = ref<ChecklistItem | null>(null)
const previewing = ref<ChecklistItem | null>(null)

function openView(item: ChecklistItem) {
  viewing.value = item
}

function viewToEdit(item: ChecklistItem) {
  viewing.value = null
  startEdit(item)
}

function openPreview(item: ChecklistItem) {
  previewing.value = item
}

const strings = {
  back: t('pantry', 'Back to lists'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
}
</script>

<style scoped lang="scss">
.pantry-detail {
  &__body {
    max-width: 900px;
    margin: 0 auto;
  }

  &__center {
    display: flex;
    justify-content: center;
    padding: 2rem;
  }

  &__items {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
}
</style>
