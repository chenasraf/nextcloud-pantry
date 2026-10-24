<template>
  <div class="stock-control" @click.stop>
    <template v-if="item.stockMode === 'level'">
      <NcActions
        v-if="narrow"
        class="stock-control__level-menu"
        :aria-label="strings.levelMenu(stockLevelLabel(level))"
        :menu-name="stockLevelLabel(level)"
        :disabled="disabled"
      >
        <template #icon>
          <MenuDownIcon :size="20" />
        </template>
        <NcActionButton
          v-for="option in LEVELS"
          :key="option"
          :model-value="level === option"
          type="radio"
          close-after-click
          @click="setLevel(option)"
        >
          {{ stockLevelLabel(option) }}
        </NcActionButton>
      </NcActions>
      <StockLevelControl
        v-else
        :model-value="level"
        :disabled="disabled"
        @update:model-value="setLevel"
      />
    </template>
    <div v-else class="stock-control__stepper" :class="{ 'stock-control__stepper--bare': narrow }">
      <button
        type="button"
        class="stock-control__step"
        :disabled="disabled || !counted || !canStepDown(onHand, 0)"
        :aria-label="strings.decrease"
        :title="strings.decrease"
        @click="step(-1)"
      >
        <MinusIcon :size="18" />
      </button>
      <span v-if="!narrow" class="stock-control__amount">{{ amountText }}</span>
      <button
        type="button"
        class="stock-control__step"
        :disabled="disabled || !counted"
        :aria-label="strings.increase"
        :title="strings.increase"
        @click="step(1)"
      >
        <PlusIcon :size="18" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import MenuDownIcon from '@icons/MenuDown.vue'
import MinusIcon from '@icons/Minus.vue'
import PlusIcon from '@icons/Plus.vue'
import type { ChecklistItem, StockLevel } from '@/api/types'
import { canStepDown, stepQuantity } from '@/utils/quantity'
import { firstNumber, withNumber } from '@/utils/stock'
import StockLevelControl from './StockLevelControl.vue'
import { stockLevelLabel } from './stockStrings'

const LEVELS: StockLevel[] = ['full', 'low', 'out']

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    narrow?: boolean
    disabled?: boolean
  }>(),
  { narrow: false, disabled: false },
)

const emit = defineEmits<{
  update: [patch: { quantity: string } | { stockLevel: StockLevel }]
}>()

const level = computed<StockLevel>(() => props.item.stockLevel ?? 'full')

/**
 * On hand as the stepper steps it. An empty amount is 0, written with the
 * target's wording so the first step reads "1 eggs" rather than "1".
 */
const onHand = computed(() => {
  const text = (props.item.quantity ?? '').trim()
  if (text !== '') return text
  const target = (props.item.target ?? '').trim()
  return firstNumber(target) !== null ? withNumber(target, 0) : '0'
})

/** On hand holds a number; "some" leaves the stepper to the item dialog. */
const counted = computed(() => firstNumber(onHand.value) !== null)

/** The amount in the target's wording, since a row never shows a unit typed into on hand. */
const amountText = computed(() => {
  const value = firstNumber(onHand.value)
  const target = (props.item.target ?? '').trim()
  return value !== null && firstNumber(target) !== null ? withNumber(target, value) : onHand.value
})

function step(direction: number) {
  emit('update', { quantity: stepQuantity(onHand.value, direction, 0) })
}

function setLevel(next: StockLevel) {
  if (next !== level.value) emit('update', { stockLevel: next })
}

const strings = {
  decrease: t('pantry', 'Decrease the amount on hand'),
  increase: t('pantry', 'Increase the amount on hand'),
  // TRANSLATORS: Accessible label of the button that changes an inventory item's stock level. {level} is the current one: Full, Low or Out.
  levelMenu: (level: string) => t('pantry', 'Stock level: {level}', { level }),
}
</script>

<style scoped lang="scss">
.stock-control {
  display: flex;
  align-items: center;
  flex-shrink: 0;

  &__stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, var(--border-radius, 6px));
    background: var(--color-main-background);

    &--bare {
      border-color: transparent;
      background: transparent;
      gap: 0.25rem;
    }
  }

  &__step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 34px;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: var(--border-radius-element, var(--border-radius, 6px));
    background: transparent;
    color: var(--color-main-text);
    cursor: pointer;

    &:hover:not(:disabled) {
      background: var(--color-background-hover);
    }

    &:focus-visible {
      outline: 2px solid var(--color-primary-element);
      outline-offset: -2px;
    }

    &:disabled {
      cursor: default;
      opacity: 0.4;
    }
  }

  &__stepper--bare &__step {
    border: 1px solid var(--color-border);
  }

  &__amount {
    min-width: 3.5rem;
    padding: 0 0.25rem;
    text-align: center;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
}
</style>
