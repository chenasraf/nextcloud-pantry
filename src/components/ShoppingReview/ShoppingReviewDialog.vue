<template>
  <NcDialog
    :name="title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="loading" class="shop-review__center">
      <NcLoadingIcon :size="32" />
    </div>

    <template v-else-if="review">
      <ShoppingReminderBlock
        v-if="reminderMoment"
        class="shop-review__reminders"
        :house-id="houseId"
        :moment="reminderMoment"
        @manage="remindersOpen = true"
      />

      <p v-if="visibleStores.length === 0" class="shop-review__empty">
        {{ strings.nothingChecked }}
      </p>

      <section v-for="grp in visibleStores" :key="storeKey(grp.storeId)" class="shop-review__store">
        <h4 class="shop-review__store-head">
          <span class="shop-review__store-icon" :style="{ color: storeColor(grp.storeId) }">
            <component :is="storeIcon(grp.storeId)" :size="18" />
          </span>
          <span class="shop-review__store-name">{{ storeLabel(grp.storeId) }}</span>
          <span class="shop-review__store-est">{{
            readOnly ? storeHeadTotal(grp) : formatEstimate(grp.estimate)
          }}</span>
        </h4>

        <ul class="shop-review__items">
          <li v-for="item in grp.items" :key="item.id" class="shop-review__item">
            <span class="shop-review__item-name">{{ item.name }}</span>
            <span v-if="itemPrice(item, grp.storeId)" class="shop-review__item-price">{{
              itemPrice(item, grp.storeId)
            }}</span>
          </li>
        </ul>
        <p v-if="grp.noPriceCount > 0" class="shop-review__noprice">
          {{ noPriceText(grp.noPriceCount) }}
        </p>

        <div v-if="!readOnly" class="shop-review__billed">
          <label class="shop-review__billed-label" :for="'billed-' + storeKey(grp.storeId)">
            {{ strings.actualPaid }}
          </label>
          <input
            :id="'billed-' + storeKey(grp.storeId)"
            v-model="billed[storeKey(grp.storeId)].total"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            class="shop-review__billed-amount"
            :placeholder="estimatePlaceholder(grp)"
            @change="saveBilled(grp)"
          />
          <NcSelect
            class="shop-review__billed-currency"
            :model-value="selectedCurrency(grp.storeId)"
            :options="currencyOptions"
            :clearable="false"
            :searchable="true"
            label="label"
            :aria-label="strings.currency"
            @update:model-value="onCurrencySelected(grp, $event)"
          />
        </div>
      </section>

      <div v-if="mode !== 'advance'" class="shop-review__grand">
        <div class="shop-review__grand-row">
          <span>{{ strings.grandTotal }}</span>
          <strong>{{ formatEstimate(review.grandTotal) }}</strong>
        </div>
        <p v-if="review.uncheckedCount > 0" class="shop-review__unchecked">
          {{ uncheckedText(review.uncheckedCount) }}
        </p>
      </div>
    </template>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">
        {{ readOnly ? strings.close : strings.back }}
      </NcButton>
      <NcButton v-if="!readOnly" variant="primary" :disabled="saving" @click="confirm">
        {{ mode === 'close' ? strings.finish : strings.nextStore }}
      </NcButton>
    </template>
  </NcDialog>

  <ShoppingRemindersDialog
    v-if="remindersOpen"
    :open="remindersOpen"
    :house-id="houseId"
    @update:open="remindersOpen = $event"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { ShoppingReminderBlock, ShoppingRemindersDialog } from '@/components/ShoppingReminders'
import { useStores } from '@/composables/useStores'
import { CURRENCIES, DEFAULT_CURRENCY } from '@/utils/currencies'
import { formatPrice, formatEstimate, formatMoney, resolveItemPrice } from '@/utils/price'
import { getReview, getSessionSummary, patchStoreBilled, patchSessionBilled } from '@/api/shopping'
import type {
  ChecklistItem,
  ShoppingReminderMoment,
  ShoppingReview,
  ShoppingReviewStore,
} from '@/api/types'

const props = defineProps<{
  open: boolean
  houseId: number
  sessionId: number
  /**
   * 'advance' shows a single store's till summary; 'close' shows the whole trip
   * with amendable totals; 'history' is a read-only view of a past trip.
   */
  mode: 'advance' | 'close' | 'history'
  /** In 'advance' mode, the store whose till is being reviewed. */
  storeId?: number | null
}>()

/** History is look-only: no billed inputs, no confirm action. */
const readOnly = computed(() => props.mode === 'history')
const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
}>()

const { findById: findStore, load: loadStores } = useStores(props.houseId)

const review = ref<ShoppingReview | null>(null)
const loading = ref(false)
const saving = ref(false)
const billed = ref<Record<string, { total: string; currency: string }>>({})
const remindersOpen = ref(false)

// Which reminder moment this dialog surfaces: the next-store confirm shows
// `on_store_advance`, the close review shows `on_close`; history shows none.
const reminderMoment = computed<ShoppingReminderMoment | null>(() => {
  if (props.mode === 'advance') return 'on_store_advance'
  if (props.mode === 'close') return 'on_close'
  return null
})

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    loading.value = true
    try {
      await loadStores()
      review.value = readOnly.value
        ? await getSessionSummary(props.houseId, props.sessionId)
        : await getReview(props.houseId, props.sessionId)
      seedBilled()
    } catch (e) {
      showError((e as Error).message || strings.loadFailed)
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

const visibleStores = computed<ShoppingReviewStore[]>(() => {
  if (!review.value) return []
  // 'advance' shows only the current store; 'close'/'history' show the whole trip.
  if (props.mode === 'advance') {
    return review.value.stores.filter((s) => s.storeId === (props.storeId ?? null))
  }
  return review.value.stores
})

function storeKey(storeId: number | null): string {
  return storeId === null ? 'none' : String(storeId)
}

function seedBilled() {
  const next: Record<string, { total: string; currency: string }> = {}
  for (const grp of review.value?.stores ?? []) {
    const key = storeKey(grp.storeId)
    next[key] = {
      total: grp.billedTotal != null ? String(grp.billedTotal) : '',
      currency: grp.billedCurrency ?? grp.estimate[0]?.currency ?? DEFAULT_CURRENCY,
    }
  }
  billed.value = next
}

async function saveBilled(grp: ShoppingReviewStore) {
  const entry = billed.value[storeKey(grp.storeId)]
  if (!entry) return
  const trimmed = entry.total.trim()
  const total = trimmed === '' ? null : Number(trimmed)
  if (total !== null && (!Number.isFinite(total) || total < 0)) return
  const currency = entry.currency.trim() || null
  try {
    saving.value = true
    if (grp.storeId === null) {
      await patchSessionBilled(props.houseId, props.sessionId, {
        billedTotal: total,
        billedCurrency: currency,
      })
    } else {
      await patchStoreBilled(props.houseId, props.sessionId, grp.storeId, {
        billedTotal: total,
        billedCurrency: currency,
      })
    }
    review.value = await getReview(props.houseId, props.sessionId)
  } catch (e) {
    showError((e as Error).message || strings.saveFailed)
  } finally {
    saving.value = false
  }
}

function confirm() {
  emit('confirm')
}

function itemPrice(item: ChecklistItem, storeId: number | null): string | null {
  const price = resolveItemPrice(item.prices, storeId)
  return price ? formatPrice(price) : null
}

// Read-only store total: the actual paid amount when the shopper amended one,
// otherwise the range-aware estimate.
function storeHeadTotal(grp: ShoppingReviewStore): string {
  if (grp.billedTotal != null) return formatMoney(grp.billedTotal, grp.billedCurrency)
  return formatEstimate(grp.estimate)
}

interface CurrencyOption {
  id: string
  label: string
}
const currencyOptions: CurrencyOption[] = CURRENCIES.map((c) => ({
  id: c.code,
  label: `${c.code} (${c.symbol})`,
}))

function selectedCurrency(storeId: number | null): CurrencyOption | null {
  const code = billed.value[storeKey(storeId)]?.currency
  return currencyOptions.find((o) => o.id === code) ?? null
}

function onCurrencySelected(grp: ShoppingReviewStore, option: CurrencyOption | null) {
  const entry = billed.value[storeKey(grp.storeId)]
  if (!entry) return
  entry.currency = option?.id ?? DEFAULT_CURRENCY
  void saveBilled(grp)
}

// The amount field's placeholder is the store's estimate as a bare number (the
// currency lives in the dropdown), signalling that leaving it empty keeps the
// calculated total. Prefers the estimate in the currently-selected currency.
function estimatePlaceholder(grp: ShoppingReviewStore): string {
  const code = billed.value[storeKey(grp.storeId)]?.currency
  const entry = grp.estimate.find((e) => e.currency === code) ?? grp.estimate[0]
  if (!entry) return strings.estimatePlaceholder
  const min = trimAmount(entry.min)
  return entry.min === entry.max ? min : `${min}–${trimAmount(entry.max)}`
}

function trimAmount(amount: number): string {
  return String(Number(amount.toFixed(2)))
}
function storeLabel(storeId: number | null): string {
  if (storeId === null) return strings.anyStore
  return findStore(storeId)?.name ?? ''
}
function storeColor(storeId: number | null): string {
  return (storeId !== null && findStore(storeId)?.color) || 'var(--color-text-maxcontrast)'
}
function storeIcon(storeId: number | null) {
  return storeIconComponent(storeId !== null ? findStore(storeId)?.icon : undefined)
}
function noPriceText(count: number): string {
  return n('pantry', '%n item without a price', '%n items without a price', count)
}
function uncheckedText(count: number): string {
  return n('pantry', '%n item still unchecked', '%n items still unchecked', count)
}

const title = computed(() => {
  if (props.mode === 'history') return strings.tripSummaryTitle
  return props.mode === 'close' ? strings.reviewTitle : strings.storeSummaryTitle
})

const strings = {
  reviewTitle: t('pantry', 'Review your trip'),
  storeSummaryTitle: t('pantry', 'Store summary'),
  tripSummaryTitle: t('pantry', 'Trip summary'),
  close: t('pantry', 'Close'),
  grandTotal: t('pantry', 'Grand total:'),
  actualPaid: t('pantry', 'Actual paid:'),
  estimatePlaceholder: t('pantry', 'e.g. 9.99'),
  currency: t('pantry', 'Currency'),
  anyStore: t('pantry', 'Any store'),
  nothingChecked: t('pantry', 'Nothing checked off here yet.'),
  finish: t('pantry', 'Finish trip'),
  nextStore: t('pantry', 'Next store'),
  back: t('pantry', 'Back'),
  loadFailed: t('pantry', 'Could not load the review.'),
  saveFailed: t('pantry', 'Could not save the amount.'),
}
</script>

<style scoped lang="scss">
.shop-review {
  &__center {
    display: flex;
    justify-content: center;
    padding: 2rem;
  }

  &__reminders {
    margin-bottom: 1rem;
  }

  &__empty {
    color: var(--color-text-maxcontrast);
  }

  &__store {
    margin-bottom: 1.25rem;
  }

  &__store-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.25rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--color-border);
  }

  &__store-icon {
    display: inline-flex;
    flex-shrink: 0;
  }

  &__store-name {
    font-weight: 600;
  }

  &__store-est {
    margin-inline-start: auto;
    color: var(--color-text-maxcontrast);
    font-variant-numeric: tabular-nums;
  }

  &__items {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  &__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.2rem 0;
  }

  &__item-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__item-price {
    color: var(--color-text-maxcontrast);
    font-variant-numeric: tabular-nums;
  }

  &__noprice {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__billed {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
  }

  &__billed-label {
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }

  &__billed-amount {
    width: 8rem;
  }

  &__billed-currency {
    flex: 0 0 9rem;
    min-width: 8rem;
  }

  &__grand {
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 2px solid var(--color-border);
  }

  &__grand-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 1.1rem;
  }

  &__unchecked {
    margin: 0.5rem 0 0;
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }
}
</style>
