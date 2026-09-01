<template>
  <NcPopover
    class="filter-chip"
    popover-base-class="filter-chip__popover"
    :shown="open"
    @update:shown="open = $event"
  >
    <template #trigger>
      <PantryChip class="filter-chip__trigger" :filled="active">
        <template #icon>
          <component :is="icon" :size="16" />
        </template>
        {{ triggerLabel }}
        <template #trailing>
          <MenuDownIcon
            :size="16"
            class="filter-chip__chevron"
            :class="{ 'filter-chip__chevron--open': open }"
          />
        </template>
      </PantryChip>
    </template>

    <div class="filter-chip__panel" role="menu">
      <button
        type="button"
        class="filter-chip__row"
        role="menuitemcheckbox"
        :aria-checked="!active"
        @click="clearAll"
      >
        <span class="filter-chip__row-icon filter-chip__row-icon--all">
          <CheckAllIcon :size="17" />
        </span>
        <span class="filter-chip__row-label">{{ allLabel }}</span>
        <NcCounterBubble v-if="totalCount != null" :count="totalCount" />
        <span class="filter-chip__row-check" :class="{ 'filter-chip__row-check--on': !active }">
          <CheckboxMarkedIcon v-if="!active" :size="18" />
          <CheckboxBlankOutlineIcon v-else :size="18" />
        </span>
      </button>

      <button
        v-for="opt in options"
        :key="opt.id"
        type="button"
        class="filter-chip__row"
        role="menuitemcheckbox"
        :aria-checked="isSelected(opt.id)"
        @click="toggle(opt.id)"
      >
        <span
          v-if="opt.icon"
          class="filter-chip__row-icon"
          :style="opt.color ? { color: opt.color } : undefined"
        >
          <component :is="opt.icon" :size="17" />
        </span>
        <span class="filter-chip__row-label">{{ opt.label }}</span>
        <NcCounterBubble v-if="opt.count != null" :count="opt.count" />
        <span
          class="filter-chip__row-check"
          :class="{ 'filter-chip__row-check--on': isSelected(opt.id) }"
        >
          <CheckboxMarkedIcon v-if="isSelected(opt.id)" :size="18" />
          <CheckboxBlankOutlineIcon v-else :size="18" />
        </span>
      </button>
    </div>
  </NcPopover>
</template>

<script setup lang="ts">
import { computed, ref, type Component } from 'vue'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import PantryChip from '@/components/PantryChip'
import MenuDownIcon from '@icons/MenuDown.vue'
import CheckAllIcon from '@icons/CheckAll.vue'
import CheckboxMarkedIcon from '@icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from '@icons/CheckboxBlankOutline.vue'

export interface FilterOption {
  id: number
  label: string
  count?: number
  icon?: Component
  color?: string
}

const props = defineProps<{
  /** Facet name shown on the chip and above the panel (e.g. "Categories"). */
  label: string
  /** Label of the first row, which clears the facet (e.g. "All categories"). */
  allLabel: string
  /** Leading icon shown on the chip trigger. */
  icon: Component
  /** Selected ids; an empty array means "no filter for this facet". */
  modelValue: number[]
  /** Selectable options for this facet. */
  options: FilterOption[]
  /** Total count shown next to the "All" row. */
  totalCount?: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number[]): void
}>()

const open = ref(false)

const active = computed(() => props.modelValue.length > 0)

const triggerLabel = computed(() =>
  active.value ? `${props.label} · ${props.modelValue.length}` : props.label,
)

function isSelected(id: number): boolean {
  return props.modelValue.includes(id)
}

function toggle(id: number): void {
  emit(
    'update:modelValue',
    isSelected(id) ? props.modelValue.filter((x) => x !== id) : [...props.modelValue, id],
  )
}

function clearAll(): void {
  if (active.value) emit('update:modelValue', [])
}
</script>

<style scoped lang="scss">
.filter-chip__chevron {
  transition: transform 0.2s ease;

  &--open {
    transform: rotate(180deg);
  }
}

.filter-chip__panel {
  display: flex;
  flex-direction: column;
  min-width: 210px;
  max-width: 300px;
  max-height: 340px;
  padding: 6px 0;
  overflow-x: hidden;
  overflow-y: auto;
}

.filter-chip__row {
  display: flex;
  align-items: center;
  gap: 12px;
  box-sizing: border-box;
  width: 100%;
  min-height: 0;
  height: auto;
  margin: 0;
  padding: 9px 14px;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--color-main-text);
  font: inherit;
  text-align: start;
  cursor: pointer;

  &:hover {
    background: var(--color-background-hover);
  }

  &:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: -2px;
  }
}

.filter-chip__row-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;

  &--all {
    color: var(--color-primary-element);
  }
}

.filter-chip__row-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 14px;
  font-weight: 600;
}

.filter-chip__row-check {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  color: var(--color-border-maxcontrast);

  &--on {
    color: var(--color-primary-element);
  }
}
</style>
