<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <p class="shop-pick__hint">{{ strings.hint }}</p>

    <div class="shop-pick__bulk">
      <span class="shop-pick__count">{{ countLabel }}</span>
      <NcButton variant="tertiary" :disabled="items.length === 0" @click="selectAll">
        <template #icon><SelectAllIcon :size="20" /></template>
        {{ strings.all }}
      </NcButton>
      <NcButton variant="tertiary" :disabled="items.length === 0" @click="selectNone">
        <template #icon><SelectOffIcon :size="20" /></template>
        {{ strings.none }}
      </NcButton>
      <NcButton variant="tertiary" :disabled="items.length === 0" @click="invert">
        <template #icon><SelectInverseIcon :size="20" /></template>
        {{ strings.invert }}
      </NcButton>
    </div>

    <p v-if="items.length === 0" class="shop-pick__hint">{{ strings.empty }}</p>

    <ul v-else class="shop-pick__groups">
      <li v-for="group in groups" :key="group.key" class="shop-pick__group">
        <div class="shop-pick__group-head">
          <NcCheckboxRadioSwitch
            :model-value="groupChecked(group)"
            @update:model-value="toggleGroup(group, $event)"
          >
            <span class="shop-pick__group-label">
              <span class="shop-pick__group-icon" :style="{ color: group.color }">
                <component :is="group.icon" :size="18" />
              </span>
              <span class="shop-pick__group-name">{{ group.name }}</span>
              <span class="shop-pick__group-count">{{ groupCount(group) }}</span>
            </span>
          </NcCheckboxRadioSwitch>
        </div>

        <ul class="shop-pick__items">
          <li v-for="item in group.items" :key="item.id">
            <NcCheckboxRadioSwitch
              :model-value="isSelected(item.id)"
              @update:model-value="toggleItem(item.id, $event)"
            >
              <span class="shop-pick__item">
                <span class="shop-pick__item-name">{{ item.name }}</span>
                <span v-if="item.quantity" class="shop-pick__item-qty">{{ item.quantity }}</span>
              </span>
            </NcCheckboxRadioSwitch>
          </li>
        </ul>
      </li>
    </ul>

    <template #actions>
      <NcButton variant="tertiary" @click="$emit('update:open', false)">
        {{ strings.cancel }}
      </NcButton>
      <NcButton variant="primary" @click="confirm">
        {{ strings.done }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import SelectAllIcon from '@icons/SelectAll.vue'
import SelectInverseIcon from '@icons/SelectInverse.vue'
import SelectOffIcon from '@icons/SelectOff.vue'
import TagOutlineIcon from '@icons/TagOutline.vue'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { useCategories } from '@/composables/useCategories'
import type { ChecklistItem } from '@/api/types'

const props = defineProps<{
  open: boolean
  houseId: number
  /** The items on offer, in the order they should be listed within a category. */
  items: ChecklistItem[]
  /** Ids left out of the trip. Empty means every item is shopped. */
  excludedIds: number[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:excludedIds': [value: number[]]
}>()

const categories = useCategories(props.houseId)

// Edited in place and only handed back on confirm, so closing the dialog any
// other way leaves the trip's plan as it was.
const selected = ref<Set<number>>(new Set())

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    void categories.load()
    const excluded = new Set(props.excludedIds)
    selected.value = new Set(props.items.map((i) => i.id).filter((id) => !excluded.has(id)))
  },
  { immediate: true },
)

interface Group {
  key: string
  name: string
  color: string
  icon: ReturnType<typeof categoryIconComponent> | typeof TagOutlineIcon
  items: ChecklistItem[]
}

const groups = computed<Group[]>(() => {
  const byCategory = new Map<number, ChecklistItem[]>()
  const uncategorized: ChecklistItem[] = []
  for (const item of props.items) {
    if (item.categoryId == null) {
      uncategorized.push(item)
      continue
    }
    const bucket = byCategory.get(item.categoryId)
    if (bucket) bucket.push(item)
    else byCategory.set(item.categoryId, [item])
  }

  const out: Group[] = []
  for (const category of categories.items.value) {
    const items = byCategory.get(category.id)
    if (!items) continue
    out.push({
      key: 'c-' + category.id,
      name: category.name,
      color: category.color,
      icon: categoryIconComponent(category.icon),
      items,
    })
    byCategory.delete(category.id)
  }
  // Categories the house list has not loaded (or no longer holds) still carry
  // items; they follow the known ones rather than vanishing from the picker.
  for (const [categoryId, items] of byCategory) {
    out.push({
      key: 'c-' + categoryId,
      name: strings.uncategorized,
      color: 'var(--color-text-maxcontrast)',
      icon: TagOutlineIcon,
      items,
    })
  }
  if (uncategorized.length > 0) {
    out.push({
      key: 'none',
      name: strings.uncategorized,
      color: 'var(--color-text-maxcontrast)',
      icon: TagOutlineIcon,
      items: uncategorized,
    })
  }
  return out
})

const countLabel = computed(() =>
  t('pantry', '{selected} of {total} selected', {
    selected: String(selected.value.size),
    total: String(props.items.length),
  }),
)

function isSelected(id: number): boolean {
  return selected.value.has(id)
}

function toggleItem(id: number, on: boolean) {
  const next = new Set(selected.value)
  if (on) next.add(id)
  else next.delete(id)
  selected.value = next
}

function groupChecked(group: Group): boolean {
  return group.items.every((i) => selected.value.has(i.id))
}

function groupCount(group: Group): string {
  const picked = group.items.filter((i) => selected.value.has(i.id)).length
  return `${picked}/${group.items.length}`
}

function toggleGroup(group: Group, on: boolean) {
  const next = new Set(selected.value)
  for (const item of group.items) {
    if (on) next.add(item.id)
    else next.delete(item.id)
  }
  selected.value = next
}

function selectAll() {
  selected.value = new Set(props.items.map((i) => i.id))
}

function selectNone() {
  selected.value = new Set()
}

function invert() {
  const previous = selected.value
  selected.value = new Set(props.items.map((i) => i.id).filter((id) => !previous.has(id)))
}

function confirm() {
  emit(
    'update:excludedIds',
    props.items.map((i) => i.id).filter((id) => !selected.value.has(id)),
  )
  emit('update:open', false)
}

const strings = {
  // TRANSLATORS: Dialog title, picking which items to shop for on this trip
  title: t('pantry', 'Items to shop'),
  hint: t(
    'pantry',
    'Uncheck anything you are not buying this trip. You can add it back while you shop.',
  ),
  // TRANSLATORS: Button that checks every item in the picker
  all: t('pantry', 'All'),
  // TRANSLATORS: Button that unchecks every item in the picker
  none: t('pantry', 'None'),
  // TRANSLATORS: Button that checks the unchecked items and unchecks the checked ones
  invert: t('pantry', 'Invert'),
  uncategorized: t('pantry', 'Uncategorized'),
  empty: t('pantry', 'The selected lists have nothing left to buy.'),
  cancel: t('pantry', 'Cancel'),
  done: t('pantry', 'Done'),
}
</script>

<style scoped lang="scss">
.shop-pick {
  &__hint {
    color: var(--color-text-maxcontrast);
    margin: 0 0 0.75rem 0;
  }

  &__bulk {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
  }

  &__count {
    margin-inline-end: auto;
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }

  &__groups,
  &__items {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  &__group {
    margin-bottom: 1rem;
  }

  &__group-head {
    border-bottom: 1px solid var(--color-border);
  }

  &__group-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
  }

  &__group-icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__group-name {
    font-weight: bold;
  }

  &__group-count {
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
    font-variant-numeric: tabular-nums;
  }

  &__items {
    padding-inline-start: 1.5rem;
  }

  &__item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.5rem;
    min-width: 0;
  }

  &__item-qty {
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }
}
</style>
