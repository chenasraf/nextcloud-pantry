<template>
  <div class="label-chip-list">
    <PantryChip
      v-for="label in visibleItems"
      :key="label.id"
      :variant="isSelected(label.id) ? 'primary' : 'secondary'"
      class="label-chip-list__chip"
      @click="toggle(label.id)"
    >
      <template #icon>
        <component :is="iconFor(label.icon)" :size="16" :style="{ color: label.color }" />
      </template>
      {{ label.name }}
    </PantryChip>
    <PantryChip variant="tertiary" class="label-chip-list__chip" @click="openCreate">
      <template #icon>
        <PlusIcon :size="16" />
      </template>
      {{ strings.create }}
    </PantryChip>

    <LabelFormDialog
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
import { useLabels } from '@/composables/useLabels'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import LabelFormDialog from '@/components/LabelManager/LabelFormDialog.vue'

const props = defineProps<{
  houseId: number
  /** Currently-attached label ids. Labels are many-to-many, so this is a set. */
  modelValue: number[]
  /** List in context; only this list's labels plus globals are offered. */
  listId?: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const { items, load, create, labelsForList } = useLabels(props.houseId)

// Keep any currently-selected label visible even if it falls outside the list
// scope (e.g. it was attached before the list binding), so its chip still
// reflects the selection.
const visibleItems = computed(() => {
  const scoped = labelsForList(props.listId ?? null)
  const extras = props.modelValue
    .filter((id) => !scoped.some((l) => l.id === id))
    .map((id) => items.value.find((l) => l.id === id))
    .filter((l): l is NonNullable<typeof l> => l != null)
  return [...scoped, ...extras]
})

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    void useLabels(props.houseId).load()
  },
)

function isSelected(id: number): boolean {
  return props.modelValue.includes(id)
}

function toggle(id: number) {
  if (isSelected(id)) {
    emit(
      'update:modelValue',
      props.modelValue.filter((v) => v !== id),
    )
  } else {
    emit('update:modelValue', [...props.modelValue, id])
  }
}

function iconFor(key: string) {
  return labelIconComponent(key)
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
    emit('update:modelValue', [...props.modelValue, created.id])
    showCreate.value = false
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create label.')
  } finally {
    saving.value = false
  }
}

const strings = {
  create: t('pantry', 'Create label …'),
}
</script>

<style scoped lang="scss">
.label-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;

  &__chip {
    flex: 0 0 auto;
  }
}
</style>
