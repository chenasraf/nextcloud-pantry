<template>
  <NcChip class="pantry-chip" :variant="variant" no-close @click="$emit('click')">
    <template v-if="$slots.icon" #icon>
      <slot name="icon" />
    </template>
    <slot />
  </NcChip>
</template>

<script setup lang="ts">
import NcChip from '@nextcloud/vue/components/NcChip'

withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'tertiary'
  }>(),
  { variant: 'tertiary' },
)

defineEmits<{
  click: []
}>()
</script>

<style scoped lang="scss">
.pantry-chip {
  // NcChip's interior elements default to text cursor — make the whole chip
  // signal that it is clickable.
  :deep(*) {
    cursor: pointer;
  }
}

.pantry-chip.pantry-chip {
  transition: background-color 0.15s ease;

  &:hover {
    background-color: color-mix(
      in srgb,
      var(--color-primary-element) 50%,
      var(--color-background-hover)
    );
  }
}
</style>
