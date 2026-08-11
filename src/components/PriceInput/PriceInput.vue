<template>
  <div class="price-input">
    <div class="price-input__type" role="radiogroup" :aria-label="strings.typeLabel">
      <button
        type="button"
        class="price-input__type-btn"
        :class="{ 'price-input__type-btn--active': localType === 'set' }"
        role="radio"
        :aria-checked="localType === 'set'"
        @click="setType('set')"
      >
        {{ strings.set }}
      </button>
      <span class="price-input__type-divider" aria-hidden="true" />
      <button
        type="button"
        class="price-input__type-btn"
        :class="{ 'price-input__type-btn--active': localType === 'range' }"
        role="radio"
        :aria-checked="localType === 'range'"
        @click="setType('range')"
      >
        {{ strings.range }}
      </button>
    </div>

    <div class="price-input__fields">
      <NcTextField
        v-model="minText"
        class="price-input__amount"
        type="number"
        inputmode="decimal"
        :label="localType === 'range' ? strings.min : strings.price"
        :placeholder="strings.amountPlaceholder"
        min="0"
        step="0.01"
        autocomplete="off"
        @update:model-value="emitValue"
      />
      <NcTextField
        v-if="localType === 'range'"
        v-model="maxText"
        class="price-input__amount"
        type="number"
        inputmode="decimal"
        :label="strings.max"
        :placeholder="strings.amountPlaceholder"
        min="0"
        step="0.01"
        autocomplete="off"
        @update:model-value="emitValue"
      />
      <NcSelect
        class="price-input__currency"
        :model-value="selectedCurrencyOption"
        :options="currencyOptions"
        :clearable="false"
        :searchable="true"
        label="label"
        :input-label="strings.currency"
        :aria-label="strings.currency"
        @update:model-value="onCurrencySelected"
      />
      <NcButton
        v-if="hasAmount"
        variant="tertiary"
        type="button"
        class="price-input__clear"
        @click="clearPrice"
      >
        <template #icon>
          <CloseIcon :size="18" />
        </template>
        {{ strings.clear }}
      </NcButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import CloseIcon from '@icons/Close.vue'
import { CURRENCIES, DEFAULT_CURRENCY } from '@/utils/currencies'
import type { PriceValue } from '@/utils/price'

const props = withDefaults(
  defineProps<{
    modelValue: PriceValue
    /** Currency preselected when the item has none yet (house's last-used). */
    defaultCurrency?: string
  }>(),
  { defaultCurrency: DEFAULT_CURRENCY },
)

const emit = defineEmits<{
  'update:modelValue': [value: PriceValue]
}>()

interface CurrencyOption {
  id: string
  label: string
}

const currencyOptions = computed<CurrencyOption[]>(() =>
  CURRENCIES.map((c) => ({ id: c.code, label: `${c.code} (${c.symbol})` })),
)

const localType = ref<'set' | 'range'>(props.modelValue.priceType ?? 'set')
const minText = ref(props.modelValue.priceMin != null ? String(props.modelValue.priceMin) : '')
const maxText = ref(props.modelValue.priceMax != null ? String(props.modelValue.priceMax) : '')
const currency = ref(props.modelValue.priceCurrency ?? props.defaultCurrency ?? DEFAULT_CURRENCY)

const selectedCurrencyOption = computed<CurrencyOption | null>(
  () => currencyOptions.value.find((o) => o.id === currency.value) ?? null,
)

const hasAmount = computed(() => minText.value.trim() !== '' || maxText.value.trim() !== '')

// The value we last emitted upward. Used to ignore the parent echoing our own
// update back through v-model — re-syncing on that echo would fight the user
// (reverting the Set/Range toggle whenever there is no amount yet, and dropping
// a trailing "." mid-decimal). Only genuine external swaps re-seed local state.
let lastEmitted: PriceValue | null = null

function samePrice(a: PriceValue, b: PriceValue): boolean {
  return (
    a.priceType === b.priceType &&
    a.priceMin === b.priceMin &&
    a.priceMax === b.priceMax &&
    a.priceCurrency === b.priceCurrency
  )
}

// Re-seed when the parent swaps in a different value (e.g. edit dialog reopens).
watch(
  () => props.modelValue,
  (v) => {
    if (lastEmitted && (v === lastEmitted || samePrice(v, lastEmitted))) return
    // Keep the chosen type when the incoming value has none yet, so toggling to
    // Range before entering an amount is not undone.
    if (v.priceType) localType.value = v.priceType
    minText.value = v.priceMin != null ? String(v.priceMin) : ''
    maxText.value = v.priceMax != null ? String(v.priceMax) : ''
    currency.value = v.priceCurrency ?? props.defaultCurrency ?? DEFAULT_CURRENCY
  },
)

function parseAmount(text: string): number | null {
  const trimmed = text.trim()
  if (trimmed === '') return null
  const n = Number(trimmed)
  return Number.isFinite(n) && n >= 0 ? n : null
}

function buildValue(): PriceValue {
  const min = parseAmount(minText.value)
  // No min amount means "no price" — but keep the currency so it can still be
  // remembered as the house default.
  if (min == null) {
    return { priceType: null, priceMin: null, priceMax: null, priceCurrency: currency.value }
  }
  if (localType.value === 'range') {
    const max = parseAmount(maxText.value)
    return { priceType: 'range', priceMin: min, priceMax: max, priceCurrency: currency.value }
  }
  return { priceType: 'set', priceMin: min, priceMax: null, priceCurrency: currency.value }
}

function emitValue() {
  const v = buildValue()
  lastEmitted = v
  emit('update:modelValue', v)
}

function setType(type: 'set' | 'range') {
  localType.value = type
  emitValue()
}

function onCurrencySelected(option: CurrencyOption | null) {
  currency.value = option?.id ?? DEFAULT_CURRENCY
  emitValue()
}

function clearPrice() {
  minText.value = ''
  maxText.value = ''
  emitValue()
}

const strings = {
  typeLabel: t('pantry', 'Price type'),
  // TRANSLATORS: Price type — a single fixed amount (as opposed to a range).
  set: t('pantry', 'Set'),
  // TRANSLATORS: Price type — a range between a minimum and a maximum amount.
  range: t('pantry', 'Range'),
  price: t('pantry', 'Price'),
  min: t('pantry', 'Min'),
  max: t('pantry', 'Max'),
  currency: t('pantry', 'Currency'),
  amountPlaceholder: t('pantry', 'e.g. 9.99'),
  clear: t('pantry', 'Clear price'),
}
</script>

<style scoped lang="scss">
.price-input {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;

  &__type {
    display: inline-flex;
    align-items: stretch;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, var(--border-radius, 6px));
    overflow: hidden;
    background: var(--color-main-background);
    width: fit-content;
  }

  &__type-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.9rem;
    border: 0;
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    font: inherit;
    font-size: 0.9rem;
    line-height: 1.2;

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &:hover:not(.price-input__type-btn--active) {
      background: var(--color-background-hover);
    }

    &--active,
    &--active:hover,
    &--active:focus,
    &--active:active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
      outline: none;
    }
  }

  &__type-divider {
    width: 1px;
    background: var(--color-border);
    align-self: stretch;
  }

  &__fields {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  &__amount {
    flex: 1 1 6rem;
    min-width: 0;
  }

  &__currency {
    flex: 0 0 9rem;
    min-width: 8rem;
  }

  &__clear {
    flex: 0 0 auto;
    // Sit at the right end of the fields row, aligned with the input bottoms
    // (the row is bottom-aligned to leave space for the field labels above).
    align-self: flex-end;
    white-space: nowrap;
  }
}
</style>
