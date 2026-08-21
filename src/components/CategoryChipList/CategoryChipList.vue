<template>
  <div class="category-chip-list">
    <PantryChip
      v-for="cat in visibleItems"
      :key="cat.id"
      :variant="modelValue === cat.id ? 'primary' : 'secondary'"
      class="category-chip-list__chip"
      @click="toggle(cat.id)"
    >
      <template #icon>
        <component :is="iconFor(cat.icon)" :size="16" :style="{ color: cat.color }" />
      </template>
      {{ cat.name }}
    </PantryChip>
    <PantryChip variant="tertiary" class="category-chip-list__chip" @click="openCreate">
      <template #icon>
        <PlusIcon :size="16" />
      </template>
      {{ strings.create }}
    </PantryChip>

    <CategoryFormDialog
      :open="showCreate"
      :house-id="houseId"
      :default-list-id="listId ?? null"
      :saving="saving"
      :error="createError"
      @update:open="showCreate = $event"
      @save="submitCreate"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import PlusIcon from '@icons/Plus.vue'
import PantryChip from '@/components/PantryChip'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import CategoryFormDialog from '@/components/CategoryManager/CategoryFormDialog.vue'

const props = defineProps<{
  houseId: number
  modelValue: number | null
  /** List in context; only this list's categories plus globals are offered. */
  listId?: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const { items, load, create, categoriesForList } = useCategories(props.houseId)

// Keep the currently-selected category visible even if it falls outside the
// list scope (e.g. it was assigned before the list binding), so its chip still
// reflects the selection.
const visibleItems = computed(() => {
  const scoped = categoriesForList(props.listId ?? null)
  if (props.modelValue != null && !scoped.some((c) => c.id === props.modelValue)) {
    const selected = items.value.find((c) => c.id === props.modelValue)
    if (selected) return [...scoped, selected]
  }
  return scoped
})

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    void useCategories(props.houseId).load()
  },
)

function toggle(id: number) {
  emit('update:modelValue', props.modelValue === id ? null : id)
}

function iconFor(key: string) {
  return categoryIconComponent(key)
}

const showCreate = ref(false)
const saving = ref(false)
const createError = ref<string | null>(null)

function openCreate() {
  createError.value = null
  showCreate.value = true
}

async function submitCreate(data: {
  name: string
  icon: string
  color: string
  listId: number | null
}) {
  saving.value = true
  createError.value = null
  try {
    const created = await create(data)
    emit('update:modelValue', created.id)
    showCreate.value = false
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create category.')
  } finally {
    saving.value = false
  }
}

const strings = {
  create: t('pantry', 'Create category …'),
}
</script>

<style scoped lang="scss">
.category-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;

  &__chip {
    flex: 0 0 auto;
  }
}
</style>
