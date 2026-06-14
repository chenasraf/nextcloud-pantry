<template>
  <div class="item-type-selector" role="radiogroup" :aria-label="strings.label">
    <button
      type="button"
      class="item-type-selector__button"
      :class="{ 'item-type-selector__button--active': currentType === 'staple' }"
      :title="strings.stapleHint"
      role="radio"
      :aria-checked="currentType === 'staple'"
      @click="$emit('select-staple')"
    >
      <PinIcon :size="16" />
      <span>{{ strings.staple }}</span>
    </button>
    <span
      v-if="currentType === 'recurring'"
      class="item-type-selector__divider"
      aria-hidden="true"
    />
    <button
      type="button"
      class="item-type-selector__button"
      :class="{ 'item-type-selector__button--active': currentType === 'oneTime' }"
      :title="strings.oneTimeHint"
      role="radio"
      :aria-checked="currentType === 'oneTime'"
      @click="$emit('select-one-time')"
    >
      <DeleteIcon :size="16" />
      <span>{{ strings.oneTime }}</span>
    </button>
    <span v-if="currentType === 'staple'" class="item-type-selector__divider" aria-hidden="true" />
    <button
      type="button"
      class="item-type-selector__button"
      :class="{ 'item-type-selector__button--active': currentType === 'recurring' }"
      :title="strings.recurringHint"
      role="radio"
      :aria-checked="currentType === 'recurring'"
      @click="$emit('select-recurring')"
    >
      <RepeatIcon :size="16" />
      <span>{{ recurringLabel }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import { formatRrule } from '@/utils/rrule'

const props = defineProps<{
  deleteOnDone: boolean
  rrule: string | null
}>()

defineEmits<{
  'select-staple': []
  'select-one-time': []
  'select-recurring': []
}>()

type ItemType = 'staple' | 'oneTime' | 'recurring'

const currentType = computed<ItemType>(() => {
  if (props.deleteOnDone) return 'oneTime'
  if (props.rrule) return 'recurring'
  return 'staple'
})

const strings = {
  label: t('pantry', 'Item type'),
  staple: t('pantry', 'Staple'),
  stapleHint: t('pantry', 'Stays on the list after it is marked done.'),
  oneTime: t('pantry', 'One-time'),
  oneTimeHint: t('pantry', 'Removed from the list once marked done.'),
  recurring: t('pantry', 'Recurring'),
  recurringHint: t('pantry', 'Comes back on a schedule.'),
}

const recurringLabel = computed(() => {
  if (currentType.value === 'recurring' && props.rrule) {
    return formatRrule(props.rrule)
  }
  return strings.recurring
})
</script>

<style scoped lang="scss">
.item-type-selector {
  display: inline-flex;
  align-items: stretch;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-element, var(--border-radius, 6px));
  overflow: hidden;
  background: var(--color-main-background);
  width: fit-content;

  &__button {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.6rem;
    border: 0;
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;
    font: inherit;
    font-size: 0.9rem;
    line-height: 1.2;
    white-space: nowrap;

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &:hover:not(.item-type-selector__button--active) {
      background: var(--color-background-hover);
    }

    // Lock the selected button to a single appearance — :hover, :focus, and
    // :active must not shift its color or paint the browser default overlay
    // on top of the primary fill.
    &--active,
    &--active:hover,
    &--active:focus,
    &--active:focus-visible,
    &--active:active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
      outline: none;
    }
  }

  &__divider {
    width: 1px;
    background: var(--color-border);
    align-self: stretch;
  }
}
</style>
