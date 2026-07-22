<template>
  <div class="pantry-oh">
    <ul v-if="grouped.length" class="pantry-oh__list">
      <li v-for="d in grouped" :key="d.day" class="pantry-oh__day">
        <span class="pantry-oh__day-name">{{ d.name }}</span>
        <span class="pantry-oh__ranges">
          <span v-for="(r, i) in d.ranges" :key="i" class="pantry-oh__range">
            {{ formatTime(r.start) }}&ndash;{{ formatTime(r.end) }}
            <button
              type="button"
              class="pantry-oh__remove"
              :aria-label="strings.remove"
              @click="remove(r)"
            >
              <CloseIcon :size="14" />
            </button>
          </span>
        </span>
      </li>
    </ul>
    <p v-else class="pantry-oh__empty">{{ strings.empty }}</p>

    <div v-if="adding" class="pantry-oh__add">
      <div class="pantry-oh__days" role="group" :aria-label="strings.days">
        <button
          v-for="day in dayOptions"
          :key="day.value"
          type="button"
          class="pantry-oh__day-toggle"
          :class="{ 'pantry-oh__day-toggle--active': draftDays.includes(day.value) }"
          :aria-pressed="draftDays.includes(day.value)"
          @click="toggleDraftDay(day.value)"
        >
          {{ day.short }}
        </button>
      </div>
      <div class="pantry-oh__times">
        <input
          v-model="draftStart"
          type="time"
          class="pantry-oh__time"
          :aria-label="strings.from"
        />
        <span class="pantry-oh__dash">&ndash;</span>
        <input v-model="draftEnd" type="time" class="pantry-oh__time" :aria-label="strings.to" />
        <NcButton variant="primary" :disabled="!canAdd" @click="add">{{ strings.add }}</NcButton>
        <NcButton variant="tertiary" @click="adding = false">{{ strings.cancel }}</NcButton>
      </div>
    </div>
    <NcButton v-else variant="secondary" @click="startAdd">
      <template #icon><PlusIcon :size="18" /></template>
      {{ strings.addOpeningHours }}
    </NcButton>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from '@icons/Plus.vue'
import CloseIcon from '@icons/Close.vue'
import type { OpeningHoursInterval } from '@/api/types'
import { dayNameShort, formatTime, groupByDay, orderedDays } from './openingHours'

const model = defineModel<OpeningHoursInterval[]>({ default: () => [] })

const grouped = computed(() => groupByDay(model.value))
const dayOptions = computed(() => orderedDays().map((d) => ({ value: d, short: dayNameShort(d) })))

const adding = ref(false)
const draftDays = ref<number[]>([])
const draftStart = ref('09:00')
const draftEnd = ref('17:00')

const canAdd = computed(
  () =>
    draftDays.value.length > 0 &&
    !!draftStart.value &&
    !!draftEnd.value &&
    draftStart.value < draftEnd.value,
)

function startAdd() {
  // Default to the locale's first day of the week (ISO 1-7, matching dayOptions).
  draftDays.value = [orderedDays()[0]!]
  draftStart.value = '09:00'
  draftEnd.value = '17:00'
  adding.value = true
}

function toggleDraftDay(day: number) {
  draftDays.value = draftDays.value.includes(day)
    ? draftDays.value.filter((d) => d !== day)
    : [...draftDays.value, day]
}

function add() {
  if (!canAdd.value) return
  const additions = draftDays.value
    .map((day) => ({ day, start: draftStart.value, end: draftEnd.value }))
    .filter(
      (next) =>
        !model.value.some(
          (r) => r.day === next.day && r.start === next.start && r.end === next.end,
        ),
    )
  if (additions.length > 0) {
    model.value = [...model.value, ...additions]
  }
  adding.value = false
}

function remove(range: OpeningHoursInterval) {
  model.value = model.value.filter(
    (r) => !(r.day === range.day && r.start === range.start && r.end === range.end),
  )
}

const strings = {
  empty: t('pantry', 'No opening hours set.'),
  // TRANSLATORS: Button that reveals a form to add a store's opening hours.
  addOpeningHours: t('pantry', 'Add opening hours'),
  add: t('pantry', 'Add'),
  cancel: t('pantry', 'Cancel'),
  remove: t('pantry', 'Remove'),
  // TRANSLATORS: Accessible label for a group of weekday toggles; several days can be selected at once.
  days: t('pantry', 'Days'),
  // TRANSLATORS: Label for the opening time input (start of an opening-hours range).
  from: t('pantry', 'From:'),
  // TRANSLATORS: Label for the closing time input (end of an opening-hours range).
  to: t('pantry', 'To:'),
}
</script>

<style scoped lang="scss">
.pantry-oh {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  &__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__day {
    display: flex;
    gap: 0.5rem;
    align-items: baseline;
  }

  &__day-name {
    flex: 0 0 auto;
    min-width: 90px;
    font-weight: 600;
  }

  &__ranges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__range {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 1px 4px 1px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
    font-variant-numeric: tabular-nums;
  }

  &__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 2px;
    border-radius: 999px;
    color: var(--color-text-maxcontrast);

    &:hover {
      color: var(--color-error);
      background: var(--color-background-dark);
    }
  }

  &__empty {
    color: var(--color-text-maxcontrast);
    margin: 0;
  }

  &__add {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
  }

  &__days {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__day-toggle {
    min-width: 40px;
    height: 34px;
    padding: 0 0.5rem;
    border: 1px solid var(--color-border-dark, var(--color-border));
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
      background: var(--color-background-hover);
    }

    &--active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
      border-color: var(--color-primary-element);
    }
  }

  &__times {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
  }

  &__time {
    height: 36px;
    padding: 0 0.5rem;
    border: 1px solid var(--color-border-dark, var(--color-border));
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
  }

  &__dash {
    color: var(--color-text-maxcontrast);
  }
}
</style>
