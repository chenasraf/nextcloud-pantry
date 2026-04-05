<template>
  <NcDialog :name="strings.title" :open="open" @update:open="$emit('update:open', $event)">
    <div class="pantry-recurrence">
      <NcSelect
        v-model="selectedPreset"
        :options="presetOptions"
        :input-label="strings.repeatLabel"
      />

      <div v-if="selectedPreset?.value === 'custom'" class="pantry-recurrence__custom">
        <NcTextField
          v-model="customRrule"
          :label="strings.customLabel"
          :placeholder="strings.customPlaceholder"
        />
        <p class="pantry-recurrence__hint">{{ strings.customHint }}</p>
      </div>

      <p v-if="error" class="pantry-recurrence__error">{{ error }}</p>
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
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { RRule } from 'rrule'

type PresetValue = 'none' | 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom'
interface PresetOption {
  label: string
  value: PresetValue
  rrule: string | null
}

const props = defineProps<{
  open: boolean
  modelValue: string | null
}>()
const emit = defineEmits<{
  (e: 'update:open', open: boolean): void
  (e: 'update:modelValue', value: string | null): void
}>()

const presetOptions = computed<PresetOption[]>(() => [
  { label: t('pantry', 'No repeat'), value: 'none', rrule: null },
  { label: t('pantry', 'Daily'), value: 'daily', rrule: 'FREQ=DAILY' },
  { label: t('pantry', 'Weekly'), value: 'weekly', rrule: 'FREQ=WEEKLY' },
  { label: t('pantry', 'Every two weeks'), value: 'biweekly', rrule: 'FREQ=WEEKLY;INTERVAL=2' },
  { label: t('pantry', 'Monthly'), value: 'monthly', rrule: 'FREQ=MONTHLY' },
  { label: t('pantry', 'Custom …'), value: 'custom', rrule: null },
])

const selectedPreset = ref<PresetOption | null>(presetOptions.value[0] ?? null)
const customRrule = ref('')
const error = ref<string | null>(null)

const hasExisting = computed(() => !!props.modelValue)

function matchPreset(rrule: string | null): PresetOption {
  const all = presetOptions.value
  if (!rrule) return all[0]!
  const normalized = rrule.trim().replace(/^RRULE:/i, '')
  const found = all.find((p) => p.rrule === normalized)
  return found ?? all[all.length - 1]! // custom
}

watch(
  () => [props.open, props.modelValue] as const,
  ([isOpen, value]) => {
    if (isOpen) {
      error.value = null
      selectedPreset.value = matchPreset(value)
      customRrule.value = selectedPreset.value.value === 'custom' ? (value ?? '') : ''
    }
  },
  { immediate: true },
)

function submit() {
  try {
    const preset = selectedPreset.value
    if (!preset || preset.value === 'none') {
      emit('update:modelValue', null)
      emit('update:open', false)
      return
    }
    if (preset.value === 'custom') {
      const raw = customRrule.value.trim().replace(/^RRULE:/i, '')
      if (!raw) {
        error.value = t('pantry', 'Please enter a rule.')
        return
      }
      // Validate on the client via rrule library.
      RRule.fromString('RRULE:' + raw)
      emit('update:modelValue', raw)
    } else if (preset.rrule) {
      emit('update:modelValue', preset.rrule)
    }
    emit('update:open', false)
  } catch (e) {
    error.value = (e as Error).message || t('pantry', 'Invalid recurrence rule.')
  }
}

function clear() {
  emit('update:modelValue', null)
  emit('update:open', false)
}

const strings = {
  title: t('pantry', 'Recurrence'),
  repeatLabel: t('pantry', 'Repeat:'),
  customLabel: t('pantry', 'Custom rule (RFC 5545):'),
  customPlaceholder: t('pantry', 'e.g. FREQ=WEEKLY;BYDAY=MO,FR'),
  customHint: t(
    'pantry',
    'Specify a standard iCalendar RRULE value. Leave off the "RRULE:" prefix.',
  ),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  clearButton: t('pantry', 'Remove recurrence'),
}
</script>

<style scoped>
.pantry-recurrence {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}

.pantry-recurrence__custom {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.pantry-recurrence__hint {
  margin: 0;
  color: var(--color-text-maxcontrast);
  font-size: 0.85rem;
}

.pantry-recurrence__error {
  margin: 0;
  color: var(--color-error);
}
</style>
