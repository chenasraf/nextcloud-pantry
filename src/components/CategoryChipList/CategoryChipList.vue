<template>
  <div class="category-chip-list">
    <PantryChip
      v-for="cat in items"
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
      :saving="saving"
      :error="createError"
      @update:open="showCreate = $event"
      @save="submitCreate"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import PlusIcon from '@icons/Plus.vue'
import PantryChip from '@/components/PantryChip'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import CategoryFormDialog from '@/components/CategoryManager/CategoryFormDialog.vue'

const props = defineProps<{
  houseId: number
  modelValue: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const { items, load, create } = useCategories(props.houseId)

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

async function submitCreate(data: { name: string; icon: string; color: string }) {
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
