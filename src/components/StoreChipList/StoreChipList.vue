<template>
  <div class="store-chip-list">
    <PantryChip
      v-for="store in items"
      :key="store.id"
      :variant="modelValue.includes(store.id) ? 'primary' : 'secondary'"
      class="store-chip-list__chip"
      @click="toggle(store.id)"
    >
      <template #icon>
        <component :is="iconFor(store.icon)" :size="16" :style="{ color: store.color }" />
      </template>
      {{ store.name }}
    </PantryChip>
    <PantryChip variant="tertiary" class="store-chip-list__chip" @click="openCreate">
      <template #icon>
        <PlusIcon :size="16" />
      </template>
      {{ strings.create }}
    </PantryChip>

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
import { onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import PlusIcon from '@icons/Plus.vue'
import PantryChip from '@/components/PantryChip'
import { useStores } from '@/composables/useStores'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { StoreFormDialog } from '@/components/StoreManager'

const props = defineProps<{
  houseId: number
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const { items, load, create } = useStores(props.houseId)

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    void useStores(props.houseId).load()
  },
)

function toggle(id: number) {
  if (props.modelValue.includes(id)) {
    emit(
      'update:modelValue',
      props.modelValue.filter((v) => v !== id),
    )
  } else {
    emit('update:modelValue', [...props.modelValue, id])
  }
}

function iconFor(key: string) {
  return storeIconComponent(key)
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
    emit('update:modelValue', [...props.modelValue, created.id])
    showCreate.value = false
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create store.')
  } finally {
    saving.value = false
  }
}

const strings = {
  // TRANSLATORS: Noun, a shop where items are bought. Chip that opens the create-store form.
  create: t('pantry', 'Create store …'),
}
</script>

<style scoped lang="scss">
.store-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;

  &__chip {
    flex: 0 0 auto;
  }
}
</style>
