<template>
  <FieldCard :label="strings.caption" class="expiry-card">
    <template #trailing>
      <div class="expiry-card__modes" role="radiogroup" :aria-label="strings.modeLabel">
        <button
          v-for="mode in modes"
          :key="mode.value"
          type="button"
          class="expiry-card__mode"
          :class="{ 'expiry-card__mode--active': draft.mode === mode.value }"
          role="radio"
          :aria-checked="draft.mode === mode.value"
          :data-testid="`expiry-mode-${mode.value}`"
          @click="setMode(mode.value)"
        >
          {{ mode.label }}
        </button>
      </div>
    </template>

    <NcDateTimePickerNative
      v-if="draft.mode === 'date'"
      type="date"
      :model-value="pickedDate"
      :label="strings.date"
      @update:model-value="setDate"
    />
    <div v-else-if="draft.mode === 'days'" class="expiry-card__days">
      <NcTextField
        :model-value="draft.shelfLifeDays != null ? String(draft.shelfLifeDays) : ''"
        :label="strings.days"
        type="number"
        inputmode="numeric"
        min="1"
        :max="MAX_SHELF_LIFE_DAYS"
        autocomplete="off"
        data-testid="expiry-days"
        @update:model-value="setDays(String($event))"
      />
    </div>

    <p v-if="summary" class="expiry-card__summary" data-testid="expiry-summary">{{ summary }}</p>
  </FieldCard>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { n, t } from '@nextcloud/l10n'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FieldCard from '@/components/FieldCard'
import { formatShortDate } from '@/utils/datetime'
import { daysUntil, todayEpoch } from '@/utils/stock'
import {
  MAX_SHELF_LIFE_DAYS,
  dateToEpoch,
  parseShelfLife,
  previewExpiry,
  type ExpiryDraft,
  type ExpiryMode,
  type StoredExpiry,
} from './expiryDraft'

const props = withDefaults(
  defineProps<{
    /** What the item has stored, for the summary of a draft left as it was. */
    stored: StoredExpiry
    /** A template keeps only a shelf life; it gets its date when drawn into an inventory. */
    shelfLifeOnly?: boolean
  }>(),
  { shelfLifeOnly: false },
)

const draft = defineModel<ExpiryDraft>({ required: true })

const modes = computed(() =>
  [
    // TRANSLATORS: Expiry option, button: the item has no expiry.
    { value: 'none' as const, label: t('pantry', 'None') },
    // TRANSLATORS: Expiry option, button: the item expires on a picked date.
    { value: 'date' as const, label: t('pantry', 'Date') },
    // TRANSLATORS: Expiry option, button: the item keeps a number of days, then expires.
    { value: 'days' as const, label: t('pantry', 'Keeps N days') },
  ].filter((mode) => !props.shelfLifeOnly || mode.value !== 'date'),
)

const pickedDate = computed(() =>
  draft.value.expiresAt !== null ? new Date(draft.value.expiresAt * 1000) : null,
)

function setMode(mode: ExpiryMode) {
  draft.value = { ...draft.value, mode }
}

function setDate(value: Date | null) {
  draft.value = { ...draft.value, expiresAt: dateToEpoch(value) }
}

function setDays(value: string) {
  draft.value = { ...draft.value, shelfLifeDays: parseShelfLife(value) }
}

const summary = computed(() => {
  if (props.shelfLifeOnly) {
    const days = draft.value.mode === 'days' ? draft.value.shelfLifeDays : null
    return days
      ? n(
          'pantry',
          'Drawn into an inventory, it expires %n day later.',
          'Drawn into an inventory, it expires %n days later.',
          days,
        )
      : null
  }
  const today = todayEpoch()
  const { expiresAt, shelfLifeDays } = previewExpiry(draft.value, props.stored, today)
  if (expiresAt === null) return null
  const date = formatShortDate(expiresAt)
  const first =
    daysUntil(expiresAt, today) < 0
      ? // TRANSLATORS: Summary under the item's expiry. {date} is the date, e.g. "Oct 21".
        t('pantry', 'Expired {date}.', { date })
      : // TRANSLATORS: Summary under the item's expiry. {date} is the date, e.g. "Oct 31".
        t('pantry', 'Good until {date}.', { date })
  if (!shelfLifeDays) return first
  const renews = n(
    'pantry',
    'Each restock renews it to %n day from that day.',
    'Each restock renews it to %n days from that day.',
    shelfLifeDays,
  )
  return `${first} ${renews}`
})

const strings = {
  // TRANSLATORS: Noun, caption of the item dialog card for when the item goes off.
  caption: t('pantry', 'Expiry'),
  // TRANSLATORS: Accessible name of the None / Date / Keeps N days choice.
  modeLabel: t('pantry', 'How the item expires'),
  // TRANSLATORS: Label of the expiry date picker.
  date: t('pantry', 'Expiry date'),
  // TRANSLATORS: Label of the number of days an item keeps before it expires.
  days: t('pantry', 'Days it keeps'),
}
</script>

<style scoped lang="scss">
.expiry-card {
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

    &:hover:not(.expiry-card__mode--active) {
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

  &__days {
    max-width: 12rem;
  }

  &__summary {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
