<template>
  <NcPopover
    class="price-filter"
    popover-base-class="price-filter__popover"
    :shown="open"
    @update:shown="open = $event"
  >
    <template #trigger>
      <PantryChip class="price-filter__trigger-chip" :filled="isActive">
        <template #icon>
          <PriceIcon :size="16" />
        </template>
        {{ triggerLabel }}
        <template #trailing>
          <MenuDownIcon
            :size="16"
            class="price-filter__chevron"
            :class="{ 'price-filter__chevron--open': open }"
          />
        </template>
      </PantryChip>
    </template>

    <div class="price-filter__panel">
      <span class="price-filter__title">{{ strings.price }}</span>
      <div class="price-filter__row">
        <NcTextField
          v-model="minText"
          class="price-filter__amount"
          type="number"
          inputmode="decimal"
          :label="strings.min"
          min="0"
          step="0.01"
          autocomplete="off"
          @update:model-value="emitValue"
        />
        <NcTextField
          v-model="maxText"
          class="price-filter__amount"
          type="number"
          inputmode="decimal"
          :label="strings.max"
          min="0"
          step="0.01"
          autocomplete="off"
          @update:model-value="emitValue"
        />
      </div>
      <!-- append-to-body is off so the dropdown renders inside the popover
             instead of teleporting behind it; the popover's overflow clip is
             lifted for this instance via popover-base-class (see the global
             style block below). -->
      <NcSelect
        class="price-filter__currency"
        :model-value="selectedCurrencyOption"
        :options="currencyOptions"
        :append-to-body="false"
        :clearable="false"
        :searchable="true"
        label="label"
        :input-label="strings.currency"
        :aria-label="strings.currency"
        @update:model-value="onCurrencySelected"
      />
      <div class="price-filter__actions">
        <NcButton variant="tertiary" :disabled="!isActive" @click="clearAll">
          {{ strings.clear }}
        </NcButton>
      </div>
    </div>
  </NcPopover>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import PantryChip from '@/components/PantryChip'
import MenuDownIcon from '@icons/MenuDown.vue'
import { CURRENCIES, resolveCurrency } from '@/utils/currencies'
import { entityIcon } from '@/utils/entityIcons'

const PriceIcon = entityIcon.price

export interface PriceFilterValue {
  min: number | null
  max: number | null
  /** null means "any currency" — compare amounts verbatim across all. */
  currency: string | null
}

const ANY = '__any__'

const props = defineProps<{
  modelValue: PriceFilterValue
}>()

const emit = defineEmits<{
  'update:modelValue': [value: PriceFilterValue]
}>()

const open = ref(false)
const minText = ref(props.modelValue.min != null ? String(props.modelValue.min) : '')
const maxText = ref(props.modelValue.max != null ? String(props.modelValue.max) : '')
const currency = ref<string | null>(props.modelValue.currency)

// Ignore the parent echoing our own update back (which would drop a trailing "."
// while typing a decimal); only re-seed on genuine external changes.
let lastEmitted: PriceFilterValue | null = null

watch(
  () => props.modelValue,
  (v) => {
    if (
      lastEmitted &&
      (v === lastEmitted ||
        (v.min === lastEmitted.min &&
          v.max === lastEmitted.max &&
          v.currency === lastEmitted.currency))
    ) {
      return
    }
    minText.value = v.min != null ? String(v.min) : ''
    maxText.value = v.max != null ? String(v.max) : ''
    currency.value = v.currency
  },
)

interface CurrencyOption {
  id: string
  label: string
}

const currencyOptions = computed<CurrencyOption[]>(() => [
  { id: ANY, label: strings.anyCurrency },
  ...CURRENCIES.map((c) => ({ id: c.code, label: `${c.code} (${c.symbol})` })),
])

const selectedCurrencyOption = computed<CurrencyOption | null>(
  () => currencyOptions.value.find((o) => o.id === (currency.value ?? ANY)) ?? null,
)

const isActive = computed(
  () =>
    props.modelValue.min != null ||
    props.modelValue.max != null ||
    props.modelValue.currency != null,
)

const triggerLabel = computed(() => {
  if (!isActive.value) return strings.price
  const symbol = props.modelValue.currency ? resolveCurrency(props.modelValue.currency).symbol : ''
  const lo = props.modelValue.min != null ? String(props.modelValue.min) : ''
  const hi = props.modelValue.max != null ? String(props.modelValue.max) : ''
  const range = lo && hi ? `${lo}-${hi}` : lo ? `≥${lo}` : `≤${hi}`
  return symbol ? `${symbol}${range}` : range
})

function parseAmount(text: string): number | null {
  const trimmed = text.trim()
  if (trimmed === '') return null
  const n = Number(trimmed)
  return Number.isFinite(n) && n >= 0 ? n : null
}

function emitValue() {
  const v: PriceFilterValue = {
    min: parseAmount(minText.value),
    max: parseAmount(maxText.value),
    currency: currency.value,
  }
  lastEmitted = v
  emit('update:modelValue', v)
}

function onCurrencySelected(option: CurrencyOption | null) {
  currency.value = option && option.id !== ANY ? option.id : null
  emitValue()
}

function clearAll() {
  minText.value = ''
  maxText.value = ''
  currency.value = null
  emitValue()
}

const strings = {
  price: t('pantry', 'Price'),
  min: t('pantry', 'Min'),
  max: t('pantry', 'Max'),
  currency: t('pantry', 'Currency'),
  // TRANSLATORS: Option in the price filter that matches items in any currency.
  anyCurrency: t('pantry', 'Any currency'),
  clear: t('pantry', 'Clear'),
}
</script>

<style scoped lang="scss">
.price-filter {
  &__chevron {
    transition: transform 0.2s ease;

    &--open {
      transform: rotate(180deg);
    }
  }

  &__panel {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.85rem;
    min-width: 16rem;
  }

  &__title {
    font-weight: 600;
  }

  &__row {
    display: flex;
    gap: 0.5rem;
  }

  &__amount {
    flex: 1;
    min-width: 0;
  }

  // The NcSelect defaults to a 260px min-width and a bottom margin that would
  // overflow / unbalance the compact popover — rein it in.
  &__currency {
    :deep(.v-select.select) {
      min-width: 0;
      margin-bottom: 0;
    }
  }

  &__actions {
    display: flex;
    justify-content: flex-end;
  }
}
</style>

<style lang="scss">
// The popover content is teleported, so scoped styles can't reach it. Scoped by
// the popover-base-class instead so this only affects the price filter: let the
// currency dropdown escape the popover's default overflow clip.
//
// !important is required: NcPopover clips with a 4-class selector
// (`._ncPopover_x.v-popper--theme-x.v-popper__popper .v-popper__inner`) that
// outweighs any selector we can write against the same teleported element.
.price-filter__popover .v-popper__inner,
.price-filter__popover .v-popper__wrapper {
  overflow: visible !important;
}
</style>
