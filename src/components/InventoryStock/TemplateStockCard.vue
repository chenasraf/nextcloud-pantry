<template>
  <FieldCard :label="strings.caption" class="template-stock-card">
    <template #trailing>
      <div class="template-stock-card__modes" role="radiogroup" :aria-label="strings.modeLabel">
        <button
          v-for="mode in MODES"
          :key="mode.value"
          type="button"
          class="template-stock-card__mode"
          :class="{ 'template-stock-card__mode--active': draft.stockMode === mode.value }"
          role="radio"
          :aria-checked="draft.stockMode === mode.value"
          :title="mode.hint"
          @click="toggleMode(mode.value)"
        >
          {{ mode.label }}
        </button>
      </div>
    </template>

    <div v-if="draft.stockMode !== 'level'" class="template-stock-card__fields">
      <div class="template-stock-card__field">
        <span class="template-stock-card__field-label" aria-hidden="true">{{
          strings.target
        }}</span>
        <NcTextField
          :model-value="draft.target ?? ''"
          :label="strings.target"
          label-outside
          :placeholder="strings.targetPlaceholder"
          autocomplete="off"
          @update:model-value="set({ target: String($event) })"
        />
      </div>
      <div class="template-stock-card__field template-stock-card__field--narrow">
        <span class="template-stock-card__field-label" aria-hidden="true">{{
          strings.reorderPoint
        }}</span>
        <NcTextField
          :model-value="draft.reorderPoint ?? ''"
          :label="strings.reorderPoint"
          label-outside
          :placeholder="strings.reorderPointPlaceholder"
          inputmode="decimal"
          autocomplete="off"
          @update:model-value="set({ reorderPoint: String($event) })"
        />
      </div>
    </div>

    <p class="template-stock-card__summary" role="status">{{ summary }}</p>
    <p v-if="draft.stockMode === null" class="template-stock-card__summary">
      {{ strings.noMode }}
    </p>
  </FieldCard>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FieldCard from '@/components/FieldCard'
import type { StockMode } from '@/api/types'

/**
 * How a template item is stocked once drawn into an inventory. A template never
 * holds an amount on hand or a level, so switching mode converts nothing, and
 * no mode at all leaves it to the inventory's own default.
 */
export interface TemplateStockDraft {
  stockMode: StockMode | null
  target: string | null
  reorderPoint: string | null
}

const draft = defineModel<TemplateStockDraft>({ required: true })

const props = defineProps<{
  /** The item's quantity: what a level item restocks, and a count item's target when it has none. */
  quantity: string
}>()

function set(patch: Partial<TemplateStockDraft>) {
  draft.value = { ...draft.value, ...patch }
}

function toggleMode(mode: StockMode) {
  set({ stockMode: draft.value.stockMode === mode ? null : mode })
}

function filled(text: string | null | undefined): string | null {
  const trimmed = (text ?? '').trim()
  return trimmed === '' ? null : trimmed
}

const summary = computed(() => {
  const quantity = filled(props.quantity)
  const target = filled(draft.value.target)
  if (draft.value.stockMode === 'level') {
    const amount = quantity ?? target
    return amount
      ? // TRANSLATORS: {amount} is what the item restocks, e.g. "1 kg bag".
        t('pantry', 'Drawn into an inventory, it starts Full and restocks {amount}.', { amount })
      : t('pantry', 'Drawn into an inventory, it starts Full.')
  }
  const amount = target ?? quantity
  return amount
    ? // TRANSLATORS: {amount} is how much the item starts with on hand, e.g. "12 eggs".
      t('pantry', 'Drawn into an inventory, it starts full with {amount} on hand.', { amount })
    : t('pantry', 'Drawn into an inventory without a target, it is not counted.')
})

const MODES: { value: StockMode; label: string; hint: string }[] = [
  {
    value: 'count',
    // TRANSLATORS: Stock mode, button. The item's stock is a number on hand.
    label: t('pantry', 'Count'),
    hint: t('pantry', 'Track how many you have, e.g. 4 of 12 eggs.'),
  },
  {
    value: 'level',
    // TRANSLATORS: Stock mode, button. The item's stock is Full, Low or Out.
    label: t('pantry', 'Level'),
    hint: t('pantry', 'Track it as Full, Low or Out, e.g. flour or spices.'),
  },
]

const strings = {
  // TRANSLATORS: Noun, caption of the item dialog card for how much of the item the household has.
  caption: t('pantry', 'Stock'),
  // TRANSLATORS: Accessible name of the Count / Level choice.
  modeLabel: t('pantry', 'Stock mode'),
  // TRANSLATORS: Noun, the amount of an item to buy back up to.
  target: t('pantry', 'Target'),
  targetPlaceholder: t('pantry', 'e.g. 12 eggs'),
  // TRANSLATORS: Noun, the amount at or below which an item counts as low.
  reorderPoint: t('pantry', 'Reorder point'),
  reorderPointPlaceholder: t('pantry', 'e.g. 4'),
  noMode: t('pantry', "With no stock mode picked, it uses the inventory's default."),
}
</script>

<style scoped lang="scss">
.template-stock-card {
  &__modes {
    display: inline-flex;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, var(--border-radius, 6px));
    overflow: hidden;
    background: var(--color-main-background);
  }

  &__mode {
    padding: 0.25rem 0.6rem;
    border: 0;
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    font: inherit;
    font-size: 0.85rem;
    line-height: 1.2;

    & + & {
      border-inline-start: 1px solid var(--color-border);
    }

    &:hover:not(.template-stock-card__mode--active) {
      background: var(--color-background-hover);
    }

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &--active,
    &--active:hover,
    &--active:focus,
    &--active:focus-visible,
    &--active:active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }
  }

  &__fields {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1 1 10rem;
    min-width: 0;

    &--narrow {
      flex: 0 1 8rem;
    }
  }

  &__field-label {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__summary {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
