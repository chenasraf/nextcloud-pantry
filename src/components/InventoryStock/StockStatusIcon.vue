<template>
  <span
    class="stock-status-icon"
    :class="`stock-status-icon--${tone}`"
    role="img"
    :aria-label="label"
    :title="label"
  >
    <component :is="icon" :size="size" />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CheckCircleOutlineIcon from '@icons/CheckCircleOutline.vue'
import AlertCircleOutlineIcon from '@icons/AlertCircleOutline.vue'
import InformationOutlineIcon from '@icons/InformationOutline.vue'
import HelpCircleOutlineIcon from '@icons/HelpCircleOutline.vue'
import type { StockTone } from '@/utils/stock'

const props = withDefaults(
  defineProps<{
    /** Red for out or expired, amber for low or expiring. */
    tone: StockTone
    /** Spells out the status chips, for the tooltip and screen readers. */
    label: string
    size?: number
  }>(),
  { size: 24 },
)

const icon = computed(() => {
  switch (props.tone) {
    case 'error':
      return InformationOutlineIcon
    case 'warning':
      return AlertCircleOutlineIcon
    case 'muted':
      return HelpCircleOutlineIcon
    default:
      return CheckCircleOutlineIcon
  }
})
</script>

<style scoped lang="scss">
.stock-status-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &--success {
    color: var(--color-element-success, var(--color-success));
  }

  &--warning {
    color: var(--color-element-warning, var(--color-warning));
  }

  &--error {
    color: var(--color-element-error, var(--color-error));
  }

  &--muted {
    color: var(--color-text-maxcontrast);
  }
}
</style>
