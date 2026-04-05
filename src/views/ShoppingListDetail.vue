<template>
  <div class="pantry-detail">
    <header class="pantry-detail__header">
      <NcButton
        variant="tertiary"
        :aria-label="strings.back"
        @click="$router.push({ name: 'lists', params: { houseId } })"
      >
        <template #icon>
          <ArrowLeftIcon :size="20" />
        </template>
      </NcButton>
      <h2 v-if="list">{{ list.name }}</h2>
      <h2 v-else>&nbsp;</h2>
    </header>

    <form class="pantry-detail__add" @submit.prevent="submitAdd">
      <NcTextField
        v-model="newName"
        :label="strings.newItemLabel"
        :placeholder="strings.newItemPlaceholder"
      />
      <NcTextField
        v-model="newQuantity"
        :label="strings.quantityLabel"
        :placeholder="strings.quantityPlaceholder"
      />
      <NcTextField
        v-model="newCategory"
        :label="strings.categoryLabel"
        :placeholder="strings.categoryPlaceholder"
      />
      <NcButton variant="tertiary" @click="showRecurrenceEditor = true">
        <template #icon>
          <RepeatIcon :size="20" />
        </template>
        {{ newRrule ? strings.recurrenceSet : strings.recurrenceButton }}
      </NcButton>
      <NcButton type="submit" variant="primary" :disabled="!newName.trim() || adding">
        <template #icon>
          <PlusIcon :size="20" />
        </template>
        {{ strings.add }}
      </NcButton>
    </form>

    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="36" />
    </div>

    <NcEmptyContent
      v-else-if="items.length === 0"
      :name="strings.emptyTitle"
      :description="strings.emptyBody"
    >
      <template #icon>
        <CartIcon />
      </template>
    </NcEmptyContent>

    <ul v-else class="pantry-items">
      <li
        v-for="item in sortedItems"
        :key="item.id"
        class="pantry-item"
        :class="{ 'pantry-item--bought': item.bought }"
      >
        <NcCheckboxRadioSwitch
          :model-value="item.bought"
          @update:model-value="handleToggle(item.id)"
        >
          <span class="pantry-item__name">{{ item.name }}</span>
        </NcCheckboxRadioSwitch>
        <div class="pantry-item__meta">
          <span v-if="item.quantity" class="pantry-item__quantity">{{ item.quantity }}</span>
          <span v-if="item.category" class="pantry-item__category">{{ item.category }}</span>
          <span v-if="item.rrule" class="pantry-item__recurrence" :title="item.rrule">
            <RepeatIcon :size="14" />
            {{ formatRrule(item.rrule) }}
          </span>
        </div>
        <div class="pantry-item__actions">
          <NcButton
            variant="tertiary"
            :aria-label="strings.removeItem"
            @click="handleRemove(item.id)"
          >
            <template #icon>
              <DeleteIcon :size="18" />
            </template>
          </NcButton>
        </div>
      </li>
    </ul>

    <RecurrenceEditor v-model:open="showRecurrenceEditor" v-model="newRrule" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PlusIcon from '@icons/Plus.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import CartIcon from '@icons/Cart.vue'
import RecurrenceEditor from '@/components/RecurrenceEditor.vue'
import { useShoppingListItems } from '@/composables/useShoppingList'
import { getList } from '@/api/lists'
import type { ShoppingList } from '@/api/types'
import { RRule } from 'rrule'

const props = defineProps<{ houseId: string; listId: string }>()

const houseIdNum = computed(() => Number(props.houseId))
const listIdNum = computed(() => Number(props.listId))

const list = ref<ShoppingList | null>(null)
const { items, loading, load, add, toggle, remove } = useShoppingListItems(
  houseIdNum.value,
  listIdNum.value,
)

const newName = ref('')
const newQuantity = ref('')
const newCategory = ref('')
const newRrule = ref<string | null>(null)
const adding = ref(false)
const showRecurrenceEditor = ref(false)

async function loadList() {
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

onMounted(async () => {
  await Promise.all([loadList(), load()])
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    await Promise.all([loadList(), load()])
  },
)

const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => {
    if (a.bought !== b.bought) return a.bought ? 1 : -1
    if (a.sortOrder !== b.sortOrder) return a.sortOrder - b.sortOrder
    return a.name.localeCompare(b.name)
  })
})

async function submitAdd() {
  const name = newName.value.trim()
  if (!name) return
  adding.value = true
  try {
    await add({
      name,
      quantity: newQuantity.value.trim() || null,
      category: newCategory.value.trim() || null,
      rrule: newRrule.value,
    })
    newName.value = ''
    newQuantity.value = ''
    newCategory.value = ''
    newRrule.value = null
  } finally {
    adding.value = false
  }
}

async function handleToggle(itemId: number) {
  await toggle(itemId)
}

async function handleRemove(itemId: number) {
  await remove(itemId)
}

function formatRrule(rrule: string): string {
  try {
    const rule = RRule.fromString('RRULE:' + rrule.replace(/^RRULE:/i, ''))
    return rule.toText()
  } catch {
    return rrule
  }
}

const strings = {
  back: t('pantry', 'Back to lists'),
  add: t('pantry', 'Add'),
  newItemLabel: t('pantry', 'Item:'),
  newItemPlaceholder: t('pantry', 'e.g. Milk'),
  quantityLabel: t('pantry', 'Quantity:'),
  quantityPlaceholder: t('pantry', 'e.g. 2 L'),
  categoryLabel: t('pantry', 'Category:'),
  categoryPlaceholder: t('pantry', 'e.g. Dairy'),
  recurrenceButton: t('pantry', 'Repeat …'),
  recurrenceSet: t('pantry', 'Repeat: set'),
  removeItem: t('pantry', 'Remove item'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
}
</script>

<style scoped lang="scss">
.pantry-detail {
  max-width: 900px;
  margin: 0 auto;

  &__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;

    h2 {
      margin: 0;
    }
  }

  &__add {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto auto;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 1.5rem;

    @media (max-width: 900px) {
      grid-template-columns: 1fr 1fr;
    }
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.pantry-item {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  background: var(--color-main-background);

  &--bought {
    opacity: 0.6;

    .pantry-item__name {
      text-decoration: line-through;
    }
  }

  &__name {
    font-weight: 500;
  }

  &__meta {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__quantity,
  &__category,
  &__recurrence {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }
}
</style>
