<template>
  <NcSelect
    class="pantry-filter-dropdown"
    :model-value="selectedOptions"
    :options="allOptions"
    :multiple="true"
    :keep-open="true"
    :clearable="false"
    :searchable="false"
    :placeholder="label"
    :input-label="label"
    label="label"
    @update:model-value="onUpdate"
  >
    <template #option="option">
      <div class="pantry-filter-option">
        <span
          v-if="option.icon"
          class="pantry-filter-option__icon"
          :style="option.color ? { color: option.color } : undefined"
        >
          <component :is="option.icon" :size="16" />
        </span>
        <span class="pantry-filter-option__name">{{ option.label }}</span>
        <NcCounterBubble v-if="option.count != null" :count="option.count" />
      </div>
    </template>

    <template #selected-option="option">
      <div class="pantry-filter-option">
        <span
          v-if="option.icon"
          class="pantry-filter-option__icon"
          :style="option.color ? { color: option.color } : undefined"
        >
          <component :is="option.icon" :size="14" />
        </span>
        <span class="pantry-filter-option__name">{{ option.label }}</span>
      </div>
    </template>
  </NcSelect>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

export interface FilterOption {
  id: number
  label: string
  count?: number
  icon?: Component
  color?: string
}

interface InternalOption extends Partial<FilterOption> {
  label: string
  all?: boolean
}

const props = defineProps<{
  /** Facet name shown as the field label / placeholder (e.g. "Categories"). */
  label: string
  /** Label of the synthetic "All" entry that clears the facet (e.g. "All"). */
  allLabel: string
  /** Selected ids; an empty array means "no filter for this facet". */
  modelValue: number[]
  /** Selectable options for this facet. */
  options: FilterOption[]
  /** Total count shown next to the "All" entry. */
  totalCount?: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number[]): void
}>()

const allOptions = computed<InternalOption[]>(() => [
  { all: true, label: props.allLabel, count: props.totalCount },
  ...props.options,
])

const selectedOptions = computed<InternalOption[]>(() =>
  props.modelValue
    .map((id) => props.options.find((o) => o.id === id))
    .filter((o): o is FilterOption => o != null),
)

function onUpdate(opts: InternalOption[] | null): void {
  const list = opts ?? []
  // Picking the synthetic "All" entry clears the whole facet.
  if (list.some((o) => o.all)) {
    emit('update:modelValue', [])
    return
  }
  emit(
    'update:modelValue',
    list.filter((o) => o.id != null).map((o) => o.id!),
  )
}
</script>

<style scoped lang="scss">
.pantry-filter-dropdown {
  // Fill the grid cell; min-width:0 lets the selected-chip area wrap instead of
  // forcing the column (and the whole bar) to expand.
  width: 100%;
  min-width: 0;

  :deep(.v-select) {
    min-width: 0;
  }
}

.pantry-filter-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;

  &__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &__name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
</style>
