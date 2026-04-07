<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    close-on-click-outside
    size="normal"
    @update:open="$emit('update:open', $event)"
  >
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

      <!-- Monthly: bymonthday -->
      <section v-if="frequencyOption?.value === 'MONTHLY'" class="pantry-recurrence__section">
        <label class="pantry-recurrence__label">{{ strings.monthDaysLabel }}</label>
        <p class="pantry-recurrence__hint">{{ strings.monthDaysHint }}</p>
        <div class="pantry-recurrence__month-grid">
          <button
            v-for="day in 31"
            :key="day"
            type="button"
            class="pantry-recurrence__month-day"
            :class="{ 'pantry-recurrence__month-day--active': selectedMonthDays.includes(day) }"
            @click="toggleMonthDay(day)"
          >
            {{ day }}
          </button>
        </div>
      </section>

      <!-- End condition -->
      <section class="pantry-recurrence__section">
        <label class="pantry-recurrence__label">{{ strings.endsLabel }}</label>
        <div class="pantry-recurrence__ends">
          <NcCheckboxRadioSwitch
            :model-value="endKind"
            value="never"
            name="pantry-end-kind"
            type="radio"
            @update:model-value="endKind = $event"
          >
            {{ strings.endNever }}
          </NcCheckboxRadioSwitch>
          <div class="pantry-recurrence__radio-row">
            <NcCheckboxRadioSwitch
              :model-value="endKind"
              value="count"
              name="pantry-end-kind"
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
              name="pantry-end-kind"
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

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton v-if="hasExisting" variant="tertiary" @click="clear">
        {{ strings.clearButton }}
      </NcButton>
      <NcButton variant="primary" @click="submit">{{ strings.save }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import RepeatIcon from '@icons/Repeat.vue'
import { Frequency, RRule, Weekday } from 'rrule'

// ---------- Types ----------
type Freq = 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY'
type EndKind = 'never' | 'count' | 'until'
type PresetKey = 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom'

interface FreqOption {
  label: string
  value: Freq
}

// ---------- Props / emits ----------
const props = defineProps<{
  open: boolean
  modelValue: string | null
  fromCompletion?: boolean
}>()
const emit = defineEmits<{
  (e: 'update:open', v: boolean): void
  (e: 'update:modelValue', v: string | null): void
  (e: 'update:fromCompletion', v: boolean): void
}>()

const fromCompletionLocal = ref<boolean>(!!props.fromCompletion)

// ---------- Form state ----------
const frequencyOptions = computed<FreqOption[]>(() => [
  { label: t('pantry', 'days'), value: 'DAILY' },
  { label: t('pantry', 'weeks'), value: 'WEEKLY' },
  { label: t('pantry', 'months'), value: 'MONTHLY' },
  { label: t('pantry', 'years'), value: 'YEARLY' },
])

const frequencyOption = ref<FreqOption>(frequencyOptions.value[1]!) // weekly default
const interval = ref<number>(1)
const selectedWeekdays = ref<number[]>([]) // 0 = Monday … 6 = Sunday (rrule.js convention)
const selectedMonthDays = ref<number[]>([])
const endKind = ref<EndKind>('never')
const endCount = ref<number>(10)
const endUntil = ref<string>('')
const error = ref<string | null>(null)

const intervalId = `pantry-interval-${Math.random().toString(36).slice(2, 8)}`

const weekdays = computed(() => [
  { value: 0, short: t('pantry', 'Mo') },
  { value: 1, short: t('pantry', 'Tu') },
  { value: 2, short: t('pantry', 'We') },
  { value: 3, short: t('pantry', 'Th') },
  { value: 4, short: t('pantry', 'Fr') },
  { value: 5, short: t('pantry', 'Sa') },
  { value: 6, short: t('pantry', 'Su') },
])

const hasExisting = computed(() => !!props.modelValue)

// ---------- Presets ----------
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
  if (freq === 'MONTHLY' && interval.value === 1 && selectedMonthDays.value.length === 0)
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
    default:
      break
  }
}

// ---------- Toggles ----------
function toggleWeekday(value: number): void {
  const idx = selectedWeekdays.value.indexOf(value)
  if (idx === -1) {
    selectedWeekdays.value = [...selectedWeekdays.value, value].sort((a, b) => a - b)
  } else {
    selectedWeekdays.value = selectedWeekdays.value.filter((v) => v !== value)
  }
}

function toggleMonthDay(day: number): void {
  const idx = selectedMonthDays.value.indexOf(day)
  if (idx === -1) {
    selectedMonthDays.value = [...selectedMonthDays.value, day].sort((a, b) => a - b)
  } else {
    selectedMonthDays.value = selectedMonthDays.value.filter((v) => v !== day)
  }
}

// ---------- RRULE build / parse ----------
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
  if (freq === 'MONTHLY' && selectedMonthDays.value.length > 0) {
    options.bymonthday = [...selectedMonthDays.value]
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
  // rrule.js returns a string like "DTSTART:...\nRRULE:FREQ=..." or "RRULE:..."
  const full = rule.toString()
  const rruleLine = full
    .split('\n')
    .map((l) => l.trim())
    .find((l) => l.startsWith('RRULE:'))
  if (!rruleLine) return null
  return rruleLine.slice('RRULE:'.length)
}

function loadFromRrule(raw: string | null): void {
  error.value = null
  selectedWeekdays.value = []
  selectedMonthDays.value = []
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

    if (opts.byweekday) {
      const list = Array.isArray(opts.byweekday) ? opts.byweekday : [opts.byweekday]
      selectedWeekdays.value = list
        .map((w) => {
          if (typeof w === 'number') return w
          if (w instanceof Weekday) return w.weekday
          return null
        })
        .filter((v): v is number => v !== null)
    }

    if (opts.bymonthday) {
      const list = Array.isArray(opts.bymonthday) ? opts.bymonthday : [opts.bymonthday]
      selectedMonthDays.value = list.filter((n): n is number => typeof n === 'number')
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

// ---------- Human-readable summary ----------
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

// ---------- Dialog open lifecycle ----------
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadFromRrule(props.modelValue)
      fromCompletionLocal.value = !!props.fromCompletion
    }
  },
  { immediate: true },
)

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

// ---------- Submit / clear ----------
function submit(): void {
  try {
    const raw = buildRrule()
    emit('update:modelValue', raw)
    emit('update:fromCompletion', fromCompletionLocal.value)
    emit('update:open', false)
  } catch (e) {
    error.value = (e as Error).message || t('pantry', 'Invalid recurrence rule.')
  }
}

function clear(): void {
  emit('update:modelValue', null)
  emit('update:fromCompletion', false)
  emit('update:open', false)
}

const strings = {
  title: t('pantry', 'Recurrence'),
  presetsLabel: t('pantry', 'Presets'),
  frequencyLabel: t('pantry', 'Unit'),
  everyLabel: t('pantry', 'Every'),
  weekdaysLabel: t('pantry', 'Repeat on'),
  monthDaysLabel: t('pantry', 'Days of the month'),
  monthDaysHint: t('pantry', 'Leave empty to repeat on the same day each month.'),
  endsLabel: t('pantry', 'Ends'),
  endNever: t('pantry', 'Never'),
  endAfter: t('pantry', 'After'),
  endAfterSuffix: t('pantry', 'occurrences'),
  endOn: t('pantry', 'On date'),
  fromCompletionLabel: t('pantry', 'Count interval from when the item is ticked off'),
  summaryLabel: t('pantry', 'Summary'),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  clearButton: t('pantry', 'Remove recurrence'),
}
</script>

<style scoped lang="scss">
.pantry-recurrence {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.25rem 0;
  min-width: 420px;

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

  &__month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.25rem;
  }

  &__month-day {
    padding: 6px 0;
    border-radius: var(--border-radius, 8px);
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    color: var(--color-main-text);
    cursor: pointer;
    font-size: 0.85rem;
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

@media (max-width: 600px) {
  .pantry-recurrence {
    min-width: 0;
  }
}
</style>
