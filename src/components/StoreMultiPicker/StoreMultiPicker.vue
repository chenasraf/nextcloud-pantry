<template>
  <div class="pantry-store-picker">
    <label v-if="label" class="pantry-store-picker__label">{{ label }}</label>
    <NcSelect
      :model-value="selectedOptions"
      :options="options"
      :multiple="true"
      :keep-open="true"
      :clearable="true"
      :placeholder="placeholder ?? strings.placeholder"
      :input-label="''"
      label="label"
      @update:model-value="onUpdate"
    >
      <template #option="option">
        <div class="pantry-store-option">
          <template v-if="option.create">
            <PlusIcon
              :size="18"
              class="pantry-store-option__icon pantry-store-option__icon--create"
            />
            <span class="pantry-store-option__name">{{ option.label }}</span>
          </template>
          <template v-else-if="option.store">
            <span class="pantry-store-option__icon" :style="{ color: option.store.color }">
              <component :is="iconFor(option.store.icon)" :size="18" />
            </span>
            <span class="pantry-store-option__name">{{ option.store.name }}</span>
          </template>
          <span v-else>{{ option.label }}</span>
        </div>
      </template>

      <template #selected-option="option">
        <div class="pantry-store-option">
          <span
            v-if="option.store"
            class="pantry-store-option__icon"
            :style="{ color: option.store.color }"
          >
            <component :is="iconFor(option.store.icon)" :size="16" />
          </span>
          <span>{{ option.label }}</span>
        </div>
      </template>
    </NcSelect>

    <StoreFormDialog
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
import { useStores } from '@/composables/useStores'
import { storeIconComponent } from './storeIcons'
import { StoreFormDialog } from '@/components/StoreManager'
import type { Store } from '@/api/types'

const props = defineProps<{
  houseId: number
  modelValue: number[]
  label?: string
  placeholder?: string
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: number[]): void
}>()

const { items, load, create } = useStores(props.houseId)

interface SelectOption {
  label: string
  id?: number
  store?: Store
  create?: boolean
}

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    void useStores(props.houseId).load()
  },
)

const options = computed<SelectOption[]>(() => {
  const storeOptions: SelectOption[] = items.value.map((s) => ({
    label: s.name,
    id: s.id,
    store: s,
  }))
  return [...storeOptions, { label: t('pantry', 'Create …'), create: true }]
})

const selectedOptions = computed<SelectOption[]>(() =>
  props.modelValue
    .map((id): SelectOption | null => {
      const store = items.value.find((s) => s.id === id)
      return store ? { label: store.name, id: store.id, store } : null
    })
    .filter((o): o is SelectOption => o !== null),
)

function onUpdate(opts: SelectOption[] | null): void {
  const list = opts ?? []
  if (list.some((o) => o.create)) {
    // The "Create …" ghost option was picked: keep the real selections and
    // open the create dialog instead of adding it as a value.
    emit(
      'update:modelValue',
      list.filter((o) => !o.create && o.id != null).map((o) => o.id!),
    )
    openCreate()
    return
  }
  emit(
    'update:modelValue',
    list.filter((o) => o.id != null).map((o) => o.id!),
  )
}

// ----- create dialog -----
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
    emit('update:modelValue', [...props.modelValue, created.id])
    showCreate.value = false
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create store.')
  } finally {
    saving.value = false
  }
}

function iconFor(key: string) {
  return storeIconComponent(key)
}

const strings = {
  // TRANSLATORS: Verb phrase, placeholder in a multi-select of shops to attach to an item
  placeholder: t('pantry', 'Pick stores'),
}
</script>

<style scoped lang="scss">
.pantry-store-picker {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;

  &__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }
}

.pantry-store-option {
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
