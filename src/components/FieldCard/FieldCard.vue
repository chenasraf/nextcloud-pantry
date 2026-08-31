<template>
  <div class="field-card" :class="{ 'field-card--plain': !fill }">
    <div v-if="label || $slots.trailing" class="field-card__head">
      <span v-if="label" class="field-card__label">{{ label }}</span>
      <span v-if="$slots.trailing" class="field-card__trailing">
        <slot name="trailing" />
      </span>
    </div>
    <div class="field-card__body">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Caption shown above the content; rendered uppercased. */
    label?: string
    /** Tinted panel background. Set false for a borderless-fill transparent box. */
    fill?: boolean
  }>(),
  { fill: true },
)
</script>

<style scoped lang="scss">
.field-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 8px);
  background: var(--color-background-hover);

  &--plain {
    background: transparent;
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
  }

  &__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast);
  }

  &__body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
  }
}
</style>
