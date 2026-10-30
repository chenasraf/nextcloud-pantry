<template>
  <div class="stock-level-control" role="radiogroup" :aria-label="strings.label">
    <button
      v-for="level in LEVELS"
      :key="level"
      type="button"
      class="stock-level-control__button"
      :class="{ [`stock-level-control__button--${level}`]: modelValue === level }"
      role="radio"
      :aria-checked="modelValue === level"
      :disabled="disabled"
      @click="select(level)"
    >
      {{ stockLevelLabel(level) }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import type { StockLevel } from '@/api/types'
import { stockLevelLabel } from './stockStrings'

const LEVELS: StockLevel[] = ['full', 'low', 'out']

const props = withDefaults(defineProps<{ modelValue: StockLevel; disabled?: boolean }>(), {
  disabled: false,
})

const emit = defineEmits<{ 'update:modelValue': [level: StockLevel] }>()

function select(level: StockLevel) {
  if (level !== props.modelValue) emit('update:modelValue', level)
}

const strings = {
  // TRANSLATORS: Noun, accessible name of the Full / Low / Out choice.
  label: t('pantry', 'Stock level'),
}
</script>

<style scoped lang="scss">
.stock-level-control {
  display: inline-flex;
  align-items: stretch;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-element, var(--border-radius, 6px));
  overflow: hidden;
  background: var(--color-main-background);
  width: fit-content;
  flex-shrink: 0;

  &__button {
    padding: 0.35rem 0.6rem;
    border: 0;
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    font: inherit;
    font-size: 0.9rem;
    line-height: 1.2;
    white-space: nowrap;

    & + & {
      border-inline-start: 1px solid var(--color-border);
    }

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &:hover:not([aria-checked='true']):not(:disabled) {
      background: var(--color-background-hover);
    }

    &:disabled {
      cursor: default;
      opacity: 0.6;
    }

    // Pinned against :hover / :focus / :active so the global button styles
    // never repaint the selected level.
    &--full,
    &--full:hover,
    &--full:focus,
    &--full:active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }

    &--low,
    &--low:hover,
    &--low:focus,
    &--low:active {
      background: var(--color-element-warning, var(--color-warning));
      color: #000;
    }

    &--out,
    &--out:hover,
    &--out:focus,
    &--out:active {
      background: var(--color-element-error, var(--color-error));
      color: #fff;
    }
  }
}
</style>
