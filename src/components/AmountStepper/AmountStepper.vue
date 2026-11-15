<template>
  <div class="amount-stepper" :class="{ 'amount-stepper--bare': bare }" @click.stop>
    <button
      type="button"
      class="amount-stepper__step"
      :disabled="disabled || !canDecrease"
      :aria-label="decreaseLabel"
      :title="decreaseLabel"
      @click="$emit('step', -1)"
    >
      <MinusIcon :size="18" />
    </button>
    <span v-if="amount !== null" class="amount-stepper__amount">{{ amount }}</span>
    <button
      type="button"
      class="amount-stepper__step"
      :disabled="disabled || !canIncrease"
      :aria-label="increaseLabel"
      :title="increaseLabel"
      @click="$emit('step', 1)"
    >
      <PlusIcon :size="18" />
    </button>
  </div>
</template>

<script setup lang="ts">
import MinusIcon from '@icons/Minus.vue'
import PlusIcon from '@icons/Plus.vue'

withDefaults(
  defineProps<{
    /** The amount shown between the buttons; null shows the buttons alone. */
    amount?: string | null
    decreaseLabel: string
    increaseLabel: string
    canDecrease?: boolean
    canIncrease?: boolean
    disabled?: boolean
    /** Borderless, with each button outlined instead, for narrow rows. */
    bare?: boolean
  }>(),
  { amount: null, canDecrease: true, canIncrease: true, disabled: false, bare: false },
)

defineEmits<{
  step: [direction: number]
}>()
</script>

<style scoped lang="scss">
.amount-stepper {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-element, var(--border-radius, 6px));
  background: var(--color-main-background);

  &--bare {
    border-color: transparent;
    background: transparent;
    gap: 0.25rem;
  }

  &__step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 34px;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: var(--border-radius-element, var(--border-radius, 6px));
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;

    &:hover:not(:disabled) {
      background: var(--color-background-hover);
    }

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &:disabled {
      cursor: default;
      opacity: 0.4;
    }
  }

  &--bare &__step {
    border: 1px solid var(--color-border);
  }

  &__amount {
    min-width: 3.5rem;
    padding: 0 0.25rem;
    text-align: center;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
}
</style>
