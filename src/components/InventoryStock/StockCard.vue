<template>
  <FieldCard :label="strings.caption" class="stock-card">
    <template #trailing>
      <div class="stock-card__modes" role="radiogroup" :aria-label="strings.modeLabel">
        <button
          v-for="mode in MODES"
          :key="mode.value"
          type="button"
          class="stock-card__mode"
          :class="{ 'stock-card__mode--active': draft.stockMode === mode.value }"
          role="radio"
          :aria-checked="draft.stockMode === mode.value"
          :title="mode.hint"
          @click="switchMode(mode.value)"
        >
          {{ mode.label }}
        </button>
      </div>
    </template>

    <p v-if="note" class="stock-card__note">{{ note }}</p>

    <div v-if="draft.stockMode === 'count'" class="stock-card__fields">
      <div class="stock-card__field stock-card__field--on-hand">
        <span class="stock-card__field-label" aria-hidden="true">{{ strings.onHand }}</span>
        <QuantityInput
          :model-value="draft.quantity ?? ''"
          :label="strings.onHand"
          :placeholder="strings.onHandPlaceholder"
          :min="0"
          :show-hint="false"
          :error="onHandMissing"
          @update:model-value="set({ quantity: $event })"
        />
      </div>
      <div class="stock-card__field">
        <span class="stock-card__field-label" aria-hidden="true">{{ strings.target }}</span>
        <NcTextField
          :model-value="draft.target ?? ''"
          :label="strings.target"
          label-outside
          :placeholder="strings.targetPlaceholder"
          autocomplete="off"
          @update:model-value="set({ target: String($event) })"
        />
      </div>
      <div class="stock-card__field stock-card__field--narrow">
        <span class="stock-card__field-label" aria-hidden="true">{{ strings.reorderPoint }}</span>
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

    <div v-else class="stock-card__fields">
      <div class="stock-card__field stock-card__field--level">
        <span class="stock-card__field-label">{{ strings.level }}</span>
        <StockLevelControl
          :model-value="draft.stockLevel ?? 'full'"
          @update:model-value="set({ stockLevel: $event })"
        />
      </div>
      <div class="stock-card__field">
        <span class="stock-card__field-label" aria-hidden="true">{{ strings.restock }}</span>
        <NcTextField
          :model-value="draft.quantity ?? ''"
          :label="strings.restock"
          label-outside
          :placeholder="strings.restockPlaceholder"
          autocomplete="off"
          @update:model-value="set({ quantity: String($event) })"
        />
      </div>
    </div>

    <p class="stock-card__status" :class="`stock-card__status--${tone}`" role="status">
      {{ statusLine }}
    </p>
  </FieldCard>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FieldCard from '@/components/FieldCard'
import QuantityInput from '@/components/QuantityInput'
import type { StockMode } from '@/api/types'
import { convertStock, evaluateStock, firstNumber, stockTone, type StockDraft } from '@/utils/stock'
import StockLevelControl from './StockLevelControl.vue'
import { stockLevelLabel } from './stockStrings'

const draft = defineModel<StockDraft>({ required: true })
/** True while saving would lose something the member has to fill in first. */
const blocked = defineModel<boolean>('blocked', { default: false })

const note = ref<string | null>(null)
/** A switch to count from Low with no reorder point leaves on hand for the member to enter. */
const onHandRequired = ref(false)

const onHandMissing = computed(
  () => onHandRequired.value && (draft.value.quantity ?? '').trim() === '',
)

function set(patch: Partial<StockDraft>) {
  const next = { ...draft.value, ...patch }
  draft.value = next
  blocked.value =
    next.stockMode === 'count' && onHandRequired.value && (next.quantity ?? '').trim() === ''
}

function switchMode(mode: StockMode) {
  if (mode === draft.value.stockMode) return
  const converted = convertStock(draft.value, mode)
  onHandRequired.value = mode === 'count' && converted.quantity === null
  note.value = conversionNote(converted)
  set({ ...converted, stockMode: mode })
}

function conversionNote(c: ReturnType<typeof convertStock>): string {
  if (c.stockMode === 'level') {
    const level = stockLevelLabel(c.stockLevel ?? 'full')
    return c.quantity
      ? // TRANSLATORS: Shown after switching an item from counting to Full / Low / Out. {level} is Full, Low or Out; {amount} is what to buy, e.g. "8 eggs".
        t('pantry', 'Converted: level {level}, restock amount "{amount}".', {
          level,
          amount: c.quantity,
        })
      : // TRANSLATORS: Shown after switching an item from counting to Full / Low / Out. {level} is Full, Low or Out.
        t('pantry', 'Converted: level {level}.', { level })
  }
  if (c.quantity !== null && c.target !== null) {
    // TRANSLATORS: Shown after switching an item from Full / Low / Out to counting, e.g. 'Converted: on hand "4 eggs", target "12 eggs".'
    return t('pantry', 'Converted: on hand "{onHand}", target "{target}".', {
      onHand: c.quantity,
      target: c.target,
    })
  }
  if (c.target !== null) {
    // TRANSLATORS: Shown after switching an item from Low to counting when there is no reorder point to start on hand from.
    return t('pantry', 'Converted: target "{target}". Enter the amount on hand.', {
      target: c.target,
    })
  }
  return t('pantry', 'Converted to a count. Enter the amount on hand and a target.')
}

const result = computed(() => evaluateStock(draft.value))
const tone = computed(() => stockTone(result.value.stockStatus))

const statusLine = computed(() => {
  const { stockStatus, shortfall } = result.value
  if (stockStatus === 'low' || stockStatus === 'out') {
    return shortfall
      ? // TRANSLATORS: {amount} is what the item's restock adds to the shopping list, e.g. "8 eggs".
        t('pantry', 'Needs restock: the shopping list asks for {amount}.', { amount: shortfall })
      : t('pantry', 'Needs restock.')
  }
  if (stockStatus === 'uncounted') {
    return firstNumber(draft.value.target) === null
      ? t('pantry', 'Not counted: the target has no number, so it never runs low.')
      : t('pantry', 'Not counted: on hand has no number, so it never runs low.')
  }
  return t('pantry', 'In stock.')
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
  onHand: t('pantry', 'On hand'),
  onHandPlaceholder: t('pantry', 'e.g. 4'),
  // TRANSLATORS: Noun, the amount of an item to buy back up to.
  target: t('pantry', 'Target'),
  targetPlaceholder: t('pantry', 'e.g. 12 eggs'),
  // TRANSLATORS: Noun, the amount at or below which an item counts as low.
  reorderPoint: t('pantry', 'Reorder point'),
  reorderPointPlaceholder: t('pantry', 'e.g. 4'),
  level: t('pantry', 'Stock level'),
  restock: t('pantry', 'Restock amount'),
  restockPlaceholder: t('pantry', 'e.g. 1 kg bag'),
}
</script>

<style scoped lang="scss">
.stock-card {
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

    &:hover:not(.stock-card__mode--active) {
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

  &__note {
    margin: 0;
    padding: 0.35rem 0.6rem;
    border-radius: var(--border-radius, 6px);
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text, var(--color-main-text));
    font-size: 0.85rem;
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

    &--on-hand {
      flex-basis: 14rem;
    }

    &--narrow {
      flex: 0 1 8rem;
    }

    &--level {
      flex: 0 0 auto;
    }
  }

  &__field-label {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__status {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);

    &--warning {
      color: var(--color-element-warning, var(--color-warning));
      font-weight: 600;
    }

    &--error {
      color: var(--color-element-error, var(--color-error));
      font-weight: 600;
    }
  }
}
</style>
