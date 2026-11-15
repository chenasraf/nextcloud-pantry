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
    <AmountStepper
      v-else
      :amount="narrow ? null : amountText"
      :bare="narrow"
      :disabled="disabled || !counted"
      :can-decrease="canStepDown(onHand, 0)"
      :decrease-label="strings.decrease"
      :increase-label="strings.increase"
      @step="step"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import MenuDownIcon from '@icons/MenuDown.vue'
import { AmountStepper } from '@/components/AmountStepper'
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
}
</style>
