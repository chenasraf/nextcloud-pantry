<template>
  <div class="pantry-category-picker">
    <label v-if="label" class="pantry-category-picker__label">{{ label }}</label>
    <NcSelect
      v-model="selected"
      :options="options"
      :clearable="true"
      :placeholder="placeholder ?? strings.placeholder"
      :input-label="''"
      label="label"
      @option:selected="onSelect"
    >
      <template #option="option">
        <div class="pantry-category-option">
          <template v-if="option.create">
            <PlusIcon
              :size="18"
              class="pantry-category-option__icon pantry-category-option__icon--create"
            />
            <span class="pantry-category-option__name">{{ option.label }}</span>
          </template>
          <template v-else-if="option.category">
            <span class="pantry-category-option__icon" :style="{ color: option.category.color }">
              <component :is="iconFor(option.category.icon)" :size="18" />
            </span>
            <span class="pantry-category-option__name">{{ option.category.name }}</span>
          </template>
          <span v-else>{{ option.label }}</span>
        </div>
      </template>

      <template #selected-option="option">
        <div class="pantry-category-option">
          <span
            v-if="option.category"
            class="pantry-category-option__icon"
            :style="{ color: option.category.color }"
          >
            <component :is="iconFor(option.category.icon)" :size="18" />
          </span>
          <span>{{ option.label }}</span>
        </div>
      </template>
    </NcSelect>

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
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PlusIcon from '@icons/Plus.vue'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from './categoryIcons'
import CategoryFormDialog from '@/components/CategoryManager/CategoryFormDialog.vue'
import type { Category } from '@/api/types'

const props = defineProps<{
  houseId: number
  modelValue: number | null
  label?: string
  placeholder?: string
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: number | null): void
}>()

const { items, load, create } = useCategories(props.houseId)

interface SelectOption {
  label: string
  id?: number
  category?: Category
  create?: boolean
}

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    // different house's composable; trigger a load on the fresh one
    void useCategories(props.houseId).load()
  },
)

const options = computed<SelectOption[]>(() => {
  const categoryOptions: SelectOption[] = items.value.map((c) => ({
    label: c.name,
    id: c.id,
    category: c,
  }))
  return [...categoryOptions, { label: t('pantry', 'Create new category …'), create: true }]
})

const selected = computed<SelectOption | null>({
  get() {
    if (props.modelValue == null) return null
    const cat = items.value.find((c) => c.id === props.modelValue)
    if (!cat) return null
    return { label: cat.name, id: cat.id, category: cat }
  },
  set(v) {
    if (!v) {
      emit('update:modelValue', null)
      return
    }
    if (v.create) {
      // Handled in onSelect (v-model would flash the "create" label).
      return
    }
    emit('update:modelValue', v.id ?? null)
  },
})

function onSelect(opt: SelectOption | SelectOption[] | null): void {
  const picked = Array.isArray(opt) ? opt[0] : opt
  if (picked && picked.create) {
    openCreate()
  }
}

// ----- create dialog -----
const showCreate = ref(false)
const saving = ref(false)
const createError = ref<string | null>(null)

function openCreate() {
  // Reset the NcSelect so it doesn't stay on the "Create new …" ghost option.
  selected.value = selected.value?.category ? selected.value : null
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

function iconFor(key: string) {
  return categoryIconComponent(key)
}

const strings = {
  placeholder: t('pantry', 'Pick a category'),
}
</script>

<style scoped lang="scss">
.pantry-category-picker {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }
}

.pantry-category-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  &__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &--create {
      color: var(--color-primary-element);
    }
  }

  &__name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
</style>
