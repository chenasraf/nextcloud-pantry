<template>
  <ul class="item-guesses" :aria-label="strings.label">
    <li v-for="row in shownRows" :key="row.key" class="item-guesses__row">
      <AutoFixIcon :size="16" class="item-guesses__icon" />
      <span v-if="showNames" class="item-guesses__name">{{ row.guess.name }}</span>
      <PantryChip
        v-if="row.guess.parsedQuantity"
        size="sm"
        variant="secondary"
        :title="strings.dropSplit"
        :aria-label="keepSplitLabel(row.guess.parsedQuantity)"
        @click="$emit('drop', row.key, 'split')"
      >
        <template #icon>
          <FormatListBulletedIcon :size="14" />
        </template>
        {{ row.guess.parsedQuantity }}
        <template #trailing>
          <CloseIcon :size="12" />
        </template>
      </PantryChip>
      <PantryChip
        v-if="row.guess.quantity"
        size="sm"
        variant="secondary"
        :title="strings.dropRemembered"
        :aria-label="dropAmountLabel(row.guess.quantity)"
        @click="$emit('drop', row.key, 'quantity')"
      >
        <template #icon>
          <FormatListBulletedIcon :size="14" />
        </template>
        {{ row.guess.quantity }}
        <template #trailing>
          <CloseIcon :size="12" />
        </template>
      </PantryChip>
      <PantryChip
        v-if="categoryOf(row.guess)"
        size="sm"
        :color="categoryOf(row.guess)!.color"
        :title="strings.dropRemembered"
        :aria-label="dropCategoryLabel(categoryOf(row.guess)!.name)"
        @click="$emit('drop', row.key, 'category')"
      >
        <template #icon>
          <component :is="categoryIconComponent(categoryOf(row.guess)!.icon)" :size="14" />
        </template>
        {{ categoryOf(row.guess)!.name }}
        <template #trailing>
          <CloseIcon :size="12" />
        </template>
      </PantryChip>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import AutoFixIcon from '@icons/AutoFix.vue'
import CloseIcon from '@icons/Close.vue'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import PantryChip from '@/components/PantryChip'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import type { Category } from '@/api/types'
import type { GuessDrops, LineGuess } from '@/utils/itemGuess'

export interface GuessRow {
  key: string
  guess: LineGuess
}

const props = withDefaults(
  defineProps<{
    rows: GuessRow[]
    categories: Category[]
    /** Show the name each row is added as; off where the name is shown beside it already. */
    showNames?: boolean
  }>(),
  { showNames: true },
)

defineEmits<{
  drop: [key: string, part: keyof GuessDrops]
}>()

function categoryOf(guess: LineGuess): Category | null {
  return guess.categoryId === null
    ? null
    : (props.categories.find((c) => c.id === guess.categoryId) ?? null)
}

function keepSplitLabel(amount: string): string {
  // TRANSLATORS: Button that undoes reading an amount out of a typed item. {amount} is the amount, e.g. "2 L".
  return t('pantry', 'Keep {amount} in the name', { amount })
}

function dropAmountLabel(amount: string): string {
  // TRANSLATORS: Button that turns down a suggested amount. {amount} is the amount, e.g. "2 L".
  return t('pantry', 'Drop the suggested amount {amount}', { amount })
}

function dropCategoryLabel(category: string): string {
  // TRANSLATORS: Button that turns down a suggested category. {category} is its name, e.g. "Dairy".
  return t('pantry', 'Drop the suggested category {category}', { category })
}

// A row with nothing guessed says nothing new.
const shownRows = computed(() =>
  props.rows.filter(
    (r) => r.guess.parsedQuantity !== null || r.guess.quantity !== null || categoryOf(r.guess),
  ),
)

const strings = {
  // TRANSLATORS: Accessible name of the list showing how typed items will be added, with the amount and category guessed for each.
  label: t('pantry', 'Suggested details'),
  // TRANSLATORS: Tooltip of a chip holding the amount read out of the typed item name.
  dropSplit: t('pantry', 'Read from what you typed. Click to keep it in the name.'),
  // TRANSLATORS: Tooltip of a chip holding a category or amount suggested from items added before.
  dropRemembered: t('pantry', 'Suggested from items you added before. Click to drop it.'),
}
</script>

<style scoped lang="scss">
.item-guesses {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
  }

  &__icon {
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
  }

  &__name {
    min-width: 0;
    overflow-wrap: anywhere;
    color: var(--color-text-maxcontrast);
  }
}
</style>
