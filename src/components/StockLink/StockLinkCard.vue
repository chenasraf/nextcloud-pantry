<template>
  <FieldCard :label="strings.caption" class="stock-link-card" data-testid="stock-link-card">
    <template v-if="item.stockLink">
      <p class="stock-link-card__text" data-testid="stock-link-card-linked">
        {{ linkedBefore }}<strong>{{ item.stockLink.name }}</strong
        >{{ linkedAfter }}
      </p>
      <div v-if="!disabled" class="stock-link-card__actions">
        <NcButton data-testid="stock-link-card-unlink" @click="$emit('unlink')">
          <template #icon>
            <LinkOffIcon :size="18" />
          </template>
          {{ strings.unlink }}
        </NcButton>
        <NcButton variant="tertiary" @click="picking = true">
          {{ strings.linkDifferent }}
        </NcButton>
      </div>
      <p class="stock-link-card__hint">{{ strings.linkedHint }}</p>
    </template>

    <template v-else>
      <div
        v-if="candidates.length > 1"
        class="stock-link-card__pick"
        data-testid="stock-link-card-pick"
      >
        <p class="stock-link-card__text">
          {{ pickBefore }}<strong>{{ pickCount }}</strong
          >{{ pickAfter }}
        </p>
        <NcCheckboxRadioSwitch
          v-for="row in candidates"
          :key="row.itemId"
          v-model="candidateId"
          type="radio"
          name="stock-link-candidate"
          :value="String(row.itemId)"
          :disabled="disabled"
        >
          {{ candidateText(row) }}
        </NcCheckboxRadioSwitch>
        <NcButton
          v-if="!disabled"
          variant="primary"
          :disabled="!pickedCandidate"
          data-testid="stock-link-card-link"
          @click="pickedCandidate && $emit('link', pickedCandidate)"
        >
          {{ strings.link }}
        </NcButton>
      </div>
      <NcCheckboxRadioSwitch
        type="switch"
        :model-value="item.autoLink"
        :disabled="disabled"
        data-testid="stock-link-card-auto"
        @update:model-value="$emit('auto-link', $event)"
      >
        {{ strings.autoLink }}
      </NcCheckboxRadioSwitch>
      <p class="stock-link-card__hint">{{ autoHint }}</p>
      <div v-if="!disabled" class="stock-link-card__actions">
        <NcButton variant="tertiary" @click="picking = true">{{ strings.linkTo }}</NcButton>
        <NcButton variant="tertiary" data-testid="stock-link-card-add" @click="adding = true">
          {{ strings.addToList }}
        </NcButton>
      </div>
    </template>

    <StockLinkPickerDialog
      v-if="picking"
      v-model:open="picking"
      :house-id="houseId"
      :inventory-id="item.listId"
      @pick="$emit('link', $event)"
    />
    <StockLinkAddDialog
      v-if="adding"
      v-model:open="adding"
      :house-id="houseId"
      :item-name="item.name"
      :restock-checklist-id="restockChecklistId"
      @pick="$emit('add-to-list', $event)"
    />
  </FieldCard>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { n, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import LinkOffIcon from '@icons/LinkOff.vue'
import FieldCard from '@/components/FieldCard'
import type { ChecklistItem, StockLinkRow } from '@/api/types'
import StockLinkAddDialog from './StockLinkAddDialog.vue'
import StockLinkPickerDialog from './StockLinkPickerDialog.vue'
import { candidateText, linkedToParts, splitAround } from './stockLink'

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    houseId: number
    /** Name of the inventory's restock checklist; null when it has none. */
    restockChecklistName?: string | null
    /** Id of the inventory's restock checklist; null when it has none. */
    restockChecklistId?: number | null
    disabled?: boolean
  }>(),
  { restockChecklistName: null, restockChecklistId: null, disabled: false },
)

defineEmits<{
  link: [row: StockLinkRow]
  unlink: []
  'auto-link': [on: boolean]
  /** Add the item to this checklist as a new item, and link it there. */
  'add-to-list': [listId: number]
}>()

const picking = ref(false)
const adding = ref(false)
const candidates = computed(() => props.item.linkCandidates ?? [])
const candidateId = ref('')
watch(
  () => props.item.linkCandidates,
  () => (candidateId.value = ''),
)
const pickedCandidate = computed(
  () => candidates.value.find((r) => String(r.itemId) === candidateId.value) ?? null,
)

const linkedParts = computed(() =>
  props.item.stockLink ? linkedToParts(props.item.stockLink) : ['', ''],
)
const linkedBefore = computed(() => linkedParts.value[0])
const linkedAfter = computed(() => linkedParts.value[1])

const pickCount = computed(() =>
  n('pantry', '%n item matches', '%n items match', candidates.value.length),
)
const pickParts = splitAround(
  // TRANSLATORS: Shopping list card of an inventory item with several same-name checklist items. {matches} is shown bold, e.g. "3 items match on the restock checklist. Which one is this?".
  t('pantry', '{matches} on the restock checklist. Which one is this?', { matches: '{matches}' }),
  '{matches}',
)
const pickBefore = pickParts[0]
const pickAfter = pickParts[1]

const autoHint = computed(() => {
  if (!props.item.autoLink) return strings.autoOffHint
  return props.restockChecklistName
    ? t(
        'pantry',
        'Finds or adds "{item}" on {checklist} when it is needed.',
        { item: props.item.name, checklist: props.restockChecklistName },
        { escape: false },
      )
    : strings.noRestockHint
})

const strings = {
  // TRANSLATORS: Caption of the stock link card of an inventory item.
  caption: t('pantry', 'Shopping list'),
  // TRANSLATORS: Verb, button that removes an inventory item's link to its checklist item.
  unlink: t('pantry', 'Unlink'),
  linkDifferent: t('pantry', 'Link to a different item …'),
  linkTo: t('pantry', 'Link to an item …'),
  addToList: t('pantry', 'Add to a shopping list …'),
  // TRANSLATORS: Verb, button that links an inventory item to the picked checklist item.
  link: t('pantry', 'Link'),
  linkedHint: t(
    'pantry',
    'When it runs low or starts expiring, this item is put back on that list.',
  ),
  autoLink: t('pantry', 'Add to shopping list when low'),
  autoOffHint: t('pantry', 'This item is only marked here when it runs low.'),
  noRestockHint: t(
    'pantry',
    'Pick a restock checklist in the inventory settings to find or add this item there.',
  ),
}
</script>

<style scoped lang="scss">
.stock-link-card {
  &__text {
    margin: 0;
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  &__pick {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--border-radius, 8px);
    border: 1px solid var(--color-element-warning, var(--color-warning));
    background: color-mix(
      in srgb,
      var(--color-element-warning, var(--color-warning)) 12%,
      transparent
    );
  }

  &__hint {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
