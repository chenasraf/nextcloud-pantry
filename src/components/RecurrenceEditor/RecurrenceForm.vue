<template>
  <div class="pantry-recurrence">
    <!-- Quick presets -->
    <section class="pantry-recurrence__section">
      <label class="pantry-recurrence__label">{{ strings.presetsLabel }}</label>
      <div class="pantry-recurrence__presets">
        <NcButton
          v-for="preset in presetButtons"
          :key="preset.key"
          :variant="activePreset === preset.key ? 'primary' : 'secondary'"
          @click="applyPreset(preset.key)"
        >
          {{ preset.label }}
        </NcButton>
      </div>
    </section>

    <hr class="pantry-recurrence__divider" />

    <!-- Frequency + interval -->
    <section class="pantry-recurrence__section pantry-recurrence__row">
      <div class="pantry-recurrence__field">
        <label :for="intervalId" class="pantry-recurrence__label">{{ strings.everyLabel }}</label>
        <input
          :id="intervalId"
          v-model.number="interval"
          type="number"
          min="1"
          max="999"
          class="pantry-recurrence__number"
        />
      </div>
      <div class="pantry-recurrence__field pantry-recurrence__field--grow">
        <label class="pantry-recurrence__label">{{ strings.frequencyLabel }}</label>
        <NcSelect
          v-model="frequencyOption"
          class="pantry-recurrence__select--frequency"
          :options="frequencyOptions"
          :clearable="false"
          :input-label="''"
        />
      </div>
    </section>

    <!-- Weekly: weekday picker -->
    <section v-if="frequencyOption?.value === 'WEEKLY'" class="pantry-recurrence__section">
      <label class="pantry-recurrence__label">{{ strings.weekdaysLabel }}</label>
      <div class="pantry-recurrence__weekdays">
        <button
          v-for="day in weekdays"
          :key="day.value"
          type="button"
          class="pantry-recurrence__weekday"
          :class="{ 'pantry-recurrence__weekday--active': selectedWeekdays.includes(day.value) }"
          @click="toggleWeekday(day.value)"
        >
          {{ day.short }}
        </button>
      </div>
    </section>

    <!-- Monthly: day of the month, or an ordinal weekday -->
    <section v-if="frequencyOption?.value === 'MONTHLY'" class="pantry-recurrence__section">
      <label class="pantry-recurrence__label">{{ strings.weekdaysLabel }}</label>
      <div class="pantry-recurrence__ends">
        <NcCheckboxRadioSwitch
          :model-value="monthlyMode"
          value="monthday"
          :name="monthlyModeName"
          type="radio"
          @update:model-value="monthlyMode = $event"
        >
          {{ strings.monthDaysLabel }}
        </NcCheckboxRadioSwitch>
        <template v-if="monthlyMode === 'monthday'">
          <NcSelect
            v-model="selectedMonthDayOptions"
            class="pantry-recurrence__select--monthday"
            :options="monthDayOptions"
            multiple
            :close-on-select="false"
            :input-label="''"
            :placeholder="strings.monthDaysPlaceholder"
            :aria-label="strings.monthDaysLabel"
          />
          <p class="pantry-recurrence__hint">{{ strings.monthDaysHint }}</p>
        </template>

        <NcCheckboxRadioSwitch
          :model-value="monthlyMode"
          value="weekday"
          :name="monthlyModeName"
          type="radio"
          @update:model-value="monthlyMode = $event"
        >
          {{ strings.monthWeekdayLabel }}
        </NcCheckboxRadioSwitch>
        <div v-if="monthlyMode === 'weekday'" class="pantry-recurrence__row">
          <div class="pantry-recurrence__field pantry-recurrence__field--grow">
            <NcSelect
              v-model="ordinalOption"
              class="pantry-recurrence__select--ordinal"
              :options="ordinalOptions"
              :clearable="false"
              :input-label="''"
              :aria-label="strings.ordinalAriaLabel"
            />
          </div>
          <div class="pantry-recurrence__field pantry-recurrence__field--grow">
            <NcSelect
              v-model="ordinalWeekdayOption"
              class="pantry-recurrence__select--ordinal-weekday"
              :options="ordinalWeekdayOptions"
              :clearable="false"
              :input-label="''"
              :aria-label="strings.ordinalWeekdayAriaLabel"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Yearly: month and day, without a year -->
    <section v-if="frequencyOption?.value === 'YEARLY'" class="pantry-recurrence__section">
      <label class="pantry-recurrence__label">{{ strings.yearDayLabel }}</label>
      <p class="pantry-recurrence__hint">{{ strings.yearDayHint }}</p>
      <NcDateTimePicker
        v-model="yearlyDate"
        type="date"
        clearable
        :format="formatYearlessDate"
        :placeholder="strings.yearDayPlaceholder"
        :aria-label="strings.yearDayLabel"
      />
    </section>

    <!-- End condition -->
    <section class="pantry-recurrence__section">
      <label class="pantry-recurrence__label">{{ strings.endsLabel }}</label>
      <div class="pantry-recurrence__ends">
        <NcCheckboxRadioSwitch
          :model-value="endKind"
          value="never"
          :name="endKindName"
          type="radio"
          @update:model-value="endKind = $event"
        >
          {{ strings.endNever }}
        </NcCheckboxRadioSwitch>
        <div class="pantry-recurrence__radio-row">
          <NcCheckboxRadioSwitch
            :model-value="endKind"
            value="count"
            :name="endKindName"
            type="radio"
            @update:model-value="endKind = $event"
          >
            {{ strings.endAfter }}
          </NcCheckboxRadioSwitch>
          <input
            v-model.number="endCount"
            type="number"
            min="1"
            max="9999"
            class="pantry-recurrence__number pantry-recurrence__number--inline"
            :disabled="endKind !== 'count'"
          />
          <span>{{ strings.endAfterSuffix }}</span>
        </div>
        <div class="pantry-recurrence__radio-row">
          <NcCheckboxRadioSwitch
            :model-value="endKind"
            value="until"
            :name="endKindName"
            type="radio"
            @update:model-value="endKind = $event"
          >
            {{ strings.endOn }}
          </NcCheckboxRadioSwitch>
          <input
            v-model="endUntil"
            type="date"
            class="pantry-recurrence__date"
            :disabled="endKind !== 'until'"
          />
        </div>
      </div>
    </section>

    <hr class="pantry-recurrence__divider" />

    <!-- Anchor mode toggle -->
    <section class="pantry-recurrence__section">
      <NcCheckboxRadioSwitch v-model="fromCompletionLocal" type="switch">
        {{ strings.fromCompletionLabel }}
      </NcCheckboxRadioSwitch>
      <p class="pantry-recurrence__hint">{{ fromCompletionHint }}</p>
    </section>

    <hr class="pantry-recurrence__divider" />

    <section class="pantry-recurrence__section">
      <p class="pantry-recurrence__summary">
        <RepeatIcon :size="16" />
        <strong>{{ strings.summaryLabel }}</strong>
        <span>{{ summaryText }}</span>
      </p>
      <p v-if="error" class="pantry-recurrence__error">{{ error }}</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { getDayNames, getDayNamesShort, getFirstDay, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import RepeatIcon from '@icons/Repeat.vue'
import { Frequency, RRule, Weekday } from 'rrule'

type Freq = 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY'
type EndKind = 'never' | 'count' | 'until'
type MonthlyMode = 'monthday' | 'weekday'
type PresetKey = 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom'

interface FreqOption {
  label: string
  value: Freq
}

interface NumberOption {
  label: string
  value: number
}

/**
 * The yearly rule only keeps a month and a day, but the date picker needs a full date to work
 * with. Anchoring on a leap year keeps 29 February selectable.
 */
const YEARLESS_ANCHOR_YEAR = 2024

const props = defineProps<{
  modelValue: string | null
  fromCompletion?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  'update:fromCompletion': [value: boolean]
}>()

const fromCompletionLocal = ref<boolean>(!!props.fromCompletion)

const frequencyOptions = computed<FreqOption[]>(() => [
  // TRANSLATORS: Unit shown in a dropdown after the interval number, e.g. 'Every 3 days'.
  { label: t('pantry', 'days'), value: 'DAILY' },
  // TRANSLATORS: Unit shown in a dropdown after the interval number, e.g. 'Every 2 weeks'.
  { label: t('pantry', 'weeks'), value: 'WEEKLY' },
  // TRANSLATORS: Unit shown in a dropdown after the interval number, e.g. 'Every 6 months'.
  { label: t('pantry', 'months'), value: 'MONTHLY' },
  // TRANSLATORS: Unit shown in a dropdown after the interval number, e.g. 'Every 2 years'.
  { label: t('pantry', 'years'), value: 'YEARLY' },
])

const frequencyOption = ref<FreqOption>(frequencyOptions.value[1]!)
const interval = ref<number>(1)
const selectedWeekdays = ref<number[]>([])
const selectedMonthDays = ref<number[]>([])
const monthlyMode = ref<MonthlyMode>('monthday')
const endKind = ref<EndKind>('never')
const endCount = ref<number>(10)
const endUntil = ref<string>('')
const yearlyDate = ref<Date | null>(null)
const error = ref<string | null>(null)

const uniqueSuffix = Math.random().toString(36).slice(2, 8)
const intervalId = `pantry-interval-${uniqueSuffix}`
const endKindName = `pantry-end-kind-${uniqueSuffix}`
const monthlyModeName = `pantry-monthly-mode-${uniqueSuffix}`

const weekdays = computed(() => {
  const shortNames = getDayNamesShort()
  const allDays = [
    { value: 0, short: shortNames[1] ?? 'Mo' },
    { value: 1, short: shortNames[2] ?? 'Tu' },
    { value: 2, short: shortNames[3] ?? 'We' },
    { value: 3, short: shortNames[4] ?? 'Th' },
    { value: 4, short: shortNames[5] ?? 'Fr' },
    { value: 5, short: shortNames[6] ?? 'Sa' },
    { value: 6, short: shortNames[0] ?? 'Su' },
  ]
  const jsFirst = getFirstDay()
  const rruleFirst = jsFirst === 0 ? 6 : jsFirst - 1
  const startIdx = allDays.findIndex((d) => d.value === rruleFirst)
  return [...allDays.slice(startIdx), ...allDays.slice(0, startIdx)]
})

const ordinalOptions = computed<NumberOption[]>(() => [
  // TRANSLATORS: Ordinal in a dropdown picking which weekday of the month, e.g. 'First Monday'.
  { label: t('pantry', 'First'), value: 1 },
  // TRANSLATORS: Ordinal in a dropdown picking which weekday of the month, e.g. 'Second Monday'.
  { label: t('pantry', 'Second'), value: 2 },
  // TRANSLATORS: Ordinal in a dropdown picking which weekday of the month, e.g. 'Third Monday'.
  { label: t('pantry', 'Third'), value: 3 },
  // TRANSLATORS: Ordinal in a dropdown picking which weekday of the month, e.g. 'Fourth Monday'.
  { label: t('pantry', 'Fourth'), value: 4 },
  // TRANSLATORS: Ordinal in a dropdown picking which weekday of the month, e.g. 'Last Monday'.
  { label: t('pantry', 'Last'), value: -1 },
])

// Full weekday names keyed by the rrule weekday index, where 0 is Monday.
const ordinalWeekdayOptions = computed<NumberOption[]>(() => {
  const names = getDayNames()
  const fallback = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
  return [0, 1, 2, 3, 4, 5, 6].map((value) => ({
    label: names[(value + 1) % 7] ?? fallback[(value + 1) % 7]!,
    value,
  }))
})

const ordinalOption = ref<NumberOption>(ordinalOptions.value[0]!)
const ordinalWeekdayOption = ref<NumberOption>(ordinalWeekdayOptions.value[0]!)

const monthDayOptions = computed<NumberOption[]>(() =>
  Array.from({ length: 31 }, (_, i) => ({ label: String(i + 1), value: i + 1 })),
)

const selectedMonthDayOptions = computed<NumberOption[]>({
  get: () => monthDayOptions.value.filter((o) => selectedMonthDays.value.includes(o.value)),
  set: (options) => {
    selectedMonthDays.value = (options ?? []).map((o) => o.value).sort((a, b) => a - b)
  },
})

function formatYearlessDate(date: Date): string {
  return date.toLocaleDateString(undefined, { month: 'long', day: 'numeric' })
}

const presetButtons = computed(() => [
  { key: 'daily' as PresetKey, label: t('pantry', 'Daily') },
  { key: 'weekly' as PresetKey, label: t('pantry', 'Weekly') },
  { key: 'biweekly' as PresetKey, label: t('pantry', 'Every 2 weeks') },
  { key: 'monthly' as PresetKey, label: t('pantry', 'Monthly') },
])

const activePreset = computed<PresetKey>(() => {
  if (endKind.value !== 'never') return 'custom'
  const freq = frequencyOption.value?.value
  if (freq === 'DAILY' && interval.value === 1) return 'daily'
  if (freq === 'WEEKLY' && interval.value === 1 && selectedWeekdays.value.length === 0)
    return 'weekly'
  if (freq === 'WEEKLY' && interval.value === 2 && selectedWeekdays.value.length === 0)
    return 'biweekly'
  if (
    freq === 'MONTHLY' &&
    interval.value === 1 &&
    monthlyMode.value === 'monthday' &&
    selectedMonthDays.value.length === 0
  )
    return 'monthly'
  return 'custom'
})

function applyPreset(key: PresetKey): void {
  error.value = null
  endKind.value = 'never'
  endCount.value = 10
  endUntil.value = ''
  selectedWeekdays.value = []
  selectedMonthDays.value = []
  monthlyMode.value = 'monthday'
  yearlyDate.value = null
  switch (key) {
    case 'daily':
      frequencyOption.value = frequencyOptions.value[0]!
      interval.value = 1
      break
    case 'weekly':
      frequencyOption.value = frequencyOptions.value[1]!
      interval.value = 1
      break
    case 'biweekly':
      frequencyOption.value = frequencyOptions.value[1]!
      interval.value = 2
      break
    case 'monthly':
      frequencyOption.value = frequencyOptions.value[2]!
      interval.value = 1
      break
  }
}

function toggleWeekday(value: number): void {
  const idx = selectedWeekdays.value.indexOf(value)
  if (idx === -1) {
    selectedWeekdays.value = [...selectedWeekdays.value, value].sort((a, b) => a - b)
  } else {
    selectedWeekdays.value = selectedWeekdays.value.filter((v) => v !== value)
  }
}

function freqToRrule(freq: Freq): Frequency {
  switch (freq) {
    case 'DAILY':
      return RRule.DAILY
    case 'WEEKLY':
      return RRule.WEEKLY
    case 'MONTHLY':
      return RRule.MONTHLY
    case 'YEARLY':
      return RRule.YEARLY
  }
}

function rruleToFreq(freq: Frequency): Freq {
  switch (freq) {
    case RRule.DAILY:
      return 'DAILY'
    case RRule.WEEKLY:
      return 'WEEKLY'
    case RRule.MONTHLY:
      return 'MONTHLY'
    case RRule.YEARLY:
      return 'YEARLY'
    default:
      return 'WEEKLY'
  }
}

function buildRrule(): string | null {
  const freq = frequencyOption.value?.value ?? 'WEEKLY'
  const options: Record<string, unknown> = {
    freq: freqToRrule(freq),
    interval: Math.max(1, Math.floor(Number(interval.value) || 1)),
  }

  if (freq === 'WEEKLY' && selectedWeekdays.value.length > 0) {
    options.byweekday = selectedWeekdays.value.map((n) => new Weekday(n))
  }
  if (freq === 'MONTHLY') {
    if (monthlyMode.value === 'weekday') {
      options.byweekday = [
        new Weekday(ordinalWeekdayOption.value?.value ?? 0, ordinalOption.value?.value ?? 1),
      ]
    } else if (selectedMonthDays.value.length > 0) {
      options.bymonthday = [...selectedMonthDays.value]
    }
  }
  if (freq === 'YEARLY' && yearlyDate.value) {
    options.bymonth = [yearlyDate.value.getMonth() + 1]
    options.bymonthday = [yearlyDate.value.getDate()]
  }

  if (endKind.value === 'count') {
    const n = Math.max(1, Math.floor(Number(endCount.value) || 1))
    options.count = n
  } else if (endKind.value === 'until') {
    if (!endUntil.value) {
      throw new Error(t('pantry', 'Please pick an end date.'))
    }
    const d = new Date(endUntil.value + 'T23:59:59Z')
    if (Number.isNaN(d.getTime())) {
      throw new Error(t('pantry', 'Invalid end date.'))
    }
    options.until = d
  }

  const rule = new RRule(options as ConstructorParameters<typeof RRule>[0])
  const full = rule.toString()
  const rruleLine = full
    .split('\n')
    .map((l) => l.trim())
    .find((l) => l.startsWith('RRULE:'))
  if (!rruleLine) return null
  return rruleLine.slice('RRULE:'.length)
}

function toList<T>(value: T | T[] | null | undefined): T[] {
  if (value == null) return []
  return Array.isArray(value) ? value : [value]
}

/** Weekday index and, when the rule pins an ordinal ("2nd Monday"), its position. */
function readWeekday(entry: unknown): { weekday: number; n: number | null } | null {
  if (typeof entry === 'number') return { weekday: entry, n: null }
  if (entry && typeof entry === 'object' && 'weekday' in entry) {
    const w = entry as { weekday: number; n?: number | null }
    return { weekday: w.weekday, n: typeof w.n === 'number' ? w.n : null }
  }
  return null
}

function loadFromRrule(raw: string | null): void {
  error.value = null
  selectedWeekdays.value = []
  selectedMonthDays.value = []
  monthlyMode.value = 'monthday'
  yearlyDate.value = null
  endKind.value = 'never'
  endCount.value = 10
  endUntil.value = ''

  if (!raw) {
    frequencyOption.value = frequencyOptions.value[1]!
    interval.value = 1
    return
  }

  try {
    const rule = RRule.fromString('RRULE:' + raw.replace(/^RRULE:/i, ''))
    const opts = rule.origOptions

    const freq = rruleToFreq(rule.options.freq)
    frequencyOption.value =
      frequencyOptions.value.find((o) => o.value === freq) ?? frequencyOptions.value[1]!
    interval.value = opts.interval ?? 1

    const byWeekday = toList(opts.byweekday)
      .map(readWeekday)
      .filter((w) => w !== null)
    const byMonthDay = toList(opts.bymonthday).filter((n): n is number => typeof n === 'number')
    const byMonth = toList(opts.bymonth).filter((n): n is number => typeof n === 'number')

    const ordinalDay = byWeekday.find((w) => w.n !== null)
    if (freq === 'MONTHLY' && ordinalDay) {
      monthlyMode.value = 'weekday'
      ordinalOption.value =
        ordinalOptions.value.find((o) => o.value === ordinalDay.n) ?? ordinalOptions.value[0]!
      ordinalWeekdayOption.value =
        ordinalWeekdayOptions.value.find((o) => o.value === ordinalDay.weekday) ??
        ordinalWeekdayOptions.value[0]!
    } else if (freq === 'WEEKLY') {
      selectedWeekdays.value = byWeekday.map((w) => w.weekday)
    }

    if (freq === 'YEARLY') {
      const month = byMonth[0]
      const day = byMonthDay[0]
      if (month != null && day != null) {
        yearlyDate.value = new Date(YEARLESS_ANCHOR_YEAR, month - 1, day)
      }
    } else if (freq === 'MONTHLY' && !ordinalDay) {
      selectedMonthDays.value = byMonthDay
    }

    if (opts.count != null) {
      endKind.value = 'count'
      endCount.value = opts.count
    } else if (opts.until) {
      endKind.value = 'until'
      const d = new Date(opts.until)
      endUntil.value = d.toISOString().slice(0, 10)
    }
  } catch (e) {
    error.value = (e as Error).message || t('pantry', 'Could not read the existing rule.')
  }
}

const summaryText = computed<string>(() => {
  try {
    const raw = buildRrule()
    if (!raw) return t('pantry', 'No repeat')
    const rule = RRule.fromString('RRULE:' + raw)
    return rule.toText()
  } catch {
    return t('pantry', '—')
  }
})

const fromCompletionHint = computed<string>(() =>
  fromCompletionLocal.value
    ? t(
        'pantry',
        'The next occurrence is counted from the moment you tick the item off, so it always comes back a full interval after it was completed.',
      )
    : t(
        'pantry',
        'The schedule is fixed: the item reappears on its next scheduled occurrence, regardless of when you tick it off.',
      ),
)

// Initialize form state from props once, then own the state. Live-emit any
// subsequent change up. Suppressed during the initial load to avoid emitting
// the parsed-then-rebuilt rrule back at the parent on mount.
const initialized = ref(false)
onMounted(async () => {
  loadFromRrule(props.modelValue)
  fromCompletionLocal.value = !!props.fromCompletion
  await nextTick()
  initialized.value = true
})

watch(
  [
    frequencyOption,
    interval,
    selectedWeekdays,
    selectedMonthDays,
    monthlyMode,
    ordinalOption,
    ordinalWeekdayOption,
    yearlyDate,
    endKind,
    endCount,
    endUntil,
  ],
  () => {
    if (!initialized.value) return
    try {
      const raw = buildRrule()
      emit('update:modelValue', raw)
    } catch (e) {
      error.value = (e as Error).message || t('pantry', 'Invalid recurrence rule.')
    }
  },
  { deep: true },
)

watch(fromCompletionLocal, (v) => {
  if (!initialized.value) return
  emit('update:fromCompletion', v)
})

const strings = {
  presetsLabel: t('pantry', 'Presets'),
  // TRANSLATORS: Label of the frequency-unit dropdown (days/weeks/months/years).
  frequencyLabel: t('pantry', 'Unit'),
  // TRANSLATORS: Label before the interval number input, forming 'Every N <unit>'.
  everyLabel: t('pantry', 'Every'),
  weekdaysLabel: t('pantry', 'Repeat on'),
  // TRANSLATORS: Radio option under 'Repeat on' for a monthly rule; followed by a dropdown
  // holding the numbers 1 to 31.
  monthDaysLabel: t('pantry', 'Days of the month'),
  monthDaysHint: t('pantry', 'Leave empty to repeat on the same day each month.'),
  monthDaysPlaceholder: t('pantry', 'Pick one or more days'),
  // TRANSLATORS: Radio option under 'Repeat on' for a monthly rule; followed by two dropdowns
  // that together read like 'Second Monday'.
  monthWeekdayLabel: t('pantry', 'A weekday of the month'),
  // TRANSLATORS: Accessible name of the dropdown holding First / Second / … / Last.
  ordinalAriaLabel: t('pantry', 'Position in the month'),
  // TRANSLATORS: Accessible name of the dropdown holding Monday … Sunday.
  ordinalWeekdayAriaLabel: t('pantry', 'Weekday'),
  // TRANSLATORS: Label of the month-and-day picker for a yearly rule.
  yearDayLabel: t('pantry', 'Date of the year'),
  yearDayHint: t('pantry', 'Leave empty to repeat on the same date each year.'),
  yearDayPlaceholder: t('pantry', 'Pick a month and day'),
  // TRANSLATORS: Label for the group of radio options choosing when the recurrence stops.
  endsLabel: t('pantry', 'Ends'),
  // TRANSLATORS: Radio option under 'Ends'; the recurrence never stops.
  endNever: t('pantry', 'Never'),
  // TRANSLATORS: Radio option under 'Ends'; followed by a number input and 'occurrences', e.g. 'After 10 occurrences'.
  endAfter: t('pantry', 'After'),
  endAfterSuffix: t('pantry', 'occurrences'),
  // TRANSLATORS: Radio option under 'Ends'; followed by a date picker, meaning the recurrence stops on that date.
  endOn: t('pantry', 'On date'),
  fromCompletionLabel: t('pantry', 'Count interval from when the item is ticked off'),
  summaryLabel: t('pantry', 'Summary'),
}
</script>

<style scoped lang="scss">
.pantry-recurrence {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.25rem 0;

  &__section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  &__row {
    flex-direction: row;
    align-items: flex-end;
    gap: 1rem;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;

    &--grow {
      flex: 1;
    }
  }

  &__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }

  &__hint {
    margin: 0;
    color: var(--color-text-maxcontrast);
    font-size: 0.8rem;
  }

  &__presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  &__number {
    width: 80px;
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 0.95rem;

    &--inline {
      width: 64px;
    }
  }

  &__date {
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 0.95rem;
  }

  &__weekdays {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__weekday {
    min-width: 38px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-weight: 600;
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

  &__ends {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  &__radio-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  &__divider {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: 0;
  }

  &__summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    color: var(--color-main-text);
    font-size: 0.95rem;
    flex-wrap: wrap;
  }

  &__error {
    margin: 0;
    color: var(--color-error);
  }
}
</style>
