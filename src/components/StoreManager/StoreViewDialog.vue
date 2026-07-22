<template>
  <NcDialog
    :name="store.name"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <div class="store-view">
      <div class="store-view__header">
        <span class="store-view__icon" :style="{ color: store.color }">
          <component :is="storeIconComponent(store.icon)" :size="32" />
        </span>
        <span class="store-view__title">{{ store.name }}</span>
      </div>

      <div class="store-view__details">
        <div v-if="store.location" class="store-view__row">
          <span class="store-view__label">{{ strings.location }}:</span>
          <span dir="auto">{{ store.location }}</span>
        </div>

        <div v-if="openingHoursDays.length" class="store-view__row store-view__row--block">
          <span class="store-view__label">{{ strings.openingHours }}:</span>
          <ul class="store-view__hours">
            <li v-for="d in openingHoursDays" :key="d.day" class="store-view__hours-day">
              <span class="store-view__hours-name">{{ d.name }}</span>
              <span class="store-view__hours-ranges">
                <span v-for="(r, i) in d.ranges" :key="i" class="store-view__hours-range">
                  {{ formatTime(r.start) }}&ndash;{{ formatTime(r.end) }}
                </span>
              </span>
            </li>
          </ul>
        </div>

        <div v-if="store.contact" class="store-view__row store-view__row--block">
          <span class="store-view__label">{{ strings.contact }}:</span>
          <span class="store-view__multiline" dir="auto">{{ store.contact }}</span>
        </div>

        <div v-if="store.responsible" class="store-view__row">
          <span class="store-view__label">{{ strings.responsible }}:</span>
          <span dir="auto">{{ store.responsible }}</span>
        </div>

        <div v-if="store.notes" class="store-view__row store-view__row--block">
          <span class="store-view__label">{{ strings.notes }}:</span>
          <span class="store-view__multiline" dir="auto">{{ store.notes }}</span>
        </div>

        <p v-if="isEmpty" class="store-view__empty">{{ strings.noDetails }}</p>
      </div>
    </div>
    <template #actions>
      <NcButton variant="tertiary" @click="$emit('edit', store)">
        <template #icon><PencilIcon :size="20" /></template>
        {{ strings.editStore }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import PencilIcon from '@icons/Pencil.vue'
import type { Store } from '@/api/types'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { formatTime, groupByDay } from './openingHours'

const props = defineProps<{
  open: boolean
  store: Store
}>()

defineEmits<{
  'update:open': [value: boolean]
  edit: [store: Store]
}>()

const openingHoursDays = computed(() =>
  props.store.openingHours ? groupByDay(props.store.openingHours) : [],
)

const isEmpty = computed(
  () =>
    !props.store.location &&
    openingHoursDays.value.length === 0 &&
    !props.store.contact &&
    !props.store.responsible &&
    !props.store.notes,
)

const strings = {
  location: t('pantry', 'Location'),
  // TRANSLATORS: Detail row label listing a store's opening hours.
  openingHours: t('pantry', 'Opening hours'),
  // TRANSLATORS: Detail row label for a store's contact details (phone, social media, etc.).
  contact: t('pantry', 'Contact'),
  // TRANSLATORS: Detail row label for the person responsible for the store.
  responsible: t('pantry', 'Responsible'),
  notes: t('pantry', 'Notes'),
  noDetails: t('pantry', 'No details added yet.'),
  editStore: t('pantry', 'Edit store'),
}
</script>

<style scoped lang="scss">
.store-view {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  &__header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  &__icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__title {
    font-size: 1.2rem;
    font-weight: 600;
    min-width: 0;
    overflow-wrap: anywhere;
  }

  &__details {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
  }

  &__row {
    display: flex;
    gap: 0.5rem;
    font-size: 0.95rem;
    align-items: baseline;

    &--block {
      flex-direction: column;
      gap: 0.25rem;
    }
  }

  &__label {
    color: var(--color-text-maxcontrast);
    font-weight: 500;
    flex-shrink: 0;
  }

  &__multiline {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }

  &__hours {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  &__hours-day {
    display: flex;
    gap: 0.5rem;
    align-items: baseline;
  }

  &__hours-name {
    flex: 0 0 auto;
    min-width: 100px;
    font-weight: 600;
  }

  &__hours-ranges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__hours-range {
    padding: 1px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
    font-variant-numeric: tabular-nums;
  }

  &__empty {
    color: var(--color-text-maxcontrast);
    margin: 0;
  }
}
</style>
