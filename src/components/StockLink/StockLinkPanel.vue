<template>
  <div class="stock-link-panel">
    <span class="stock-link-panel__caption">{{ strings.caption }}</span>

    <template v-if="item.stockLink">
      <p class="stock-link-panel__linked" data-testid="stock-link-linked">
        {{ linkedParts[0] }}<strong>{{ item.stockLink.name }}</strong
        >{{ linkedParts[1] }}
      </p>
      <NcButton :disabled="disabled" data-testid="stock-link-unlink" @click="$emit('unlink')">
        <template #icon>
          <LinkOffIcon :size="18" />
        </template>
        {{ strings.unlink }}
      </NcButton>
    </template>

    <template v-else>
      <template v-if="candidates.length > 1">
        <p class="stock-link-panel__question">{{ strings.whichOne }}</p>
        <NcButton
          v-for="row in candidates"
          :key="row.itemId"
          wide
          alignment="start"
          :disabled="disabled"
          data-testid="stock-link-candidate"
          @click="$emit('link', row)"
        >
          {{ candidateText(row) }}
        </NcButton>
      </template>
      <NcCheckboxRadioSwitch
        type="switch"
        :model-value="item.autoLink"
        :disabled="disabled"
        data-testid="stock-link-auto"
        @update:model-value="$emit('auto-link', $event)"
      >
        {{ strings.autoLink }}
      </NcCheckboxRadioSwitch>
    </template>

    <template v-if="!disabled">
      <span class="stock-link-panel__caption">{{ strings.byHand }}</span>
      <LinkTargetSearch
        :house-id="houseId"
        :inventory-id="item.listId"
        @pick="$emit('link', $event)"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import LinkOffIcon from '@icons/LinkOff.vue'
import type { ChecklistItem, StockLinkRow } from '@/api/types'
import LinkTargetSearch from './LinkTargetSearch.vue'
import { candidateText, linkedRowParts } from './stockLink'

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    houseId: number
    disabled?: boolean
  }>(),
  { disabled: false },
)

defineEmits<{
  link: [row: StockLinkRow]
  unlink: []
  'auto-link': [on: boolean]
}>()

const candidates = computed(() => props.item.linkCandidates ?? [])

const linkedParts = computed(() =>
  props.item.stockLink ? linkedRowParts(props.item.stockLink) : ['', ''],
)

const strings = {
  // TRANSLATORS: Caption of the stock link controls of an inventory item.
  caption: t('pantry', 'Shopping list'),
  // TRANSLATORS: Verb, button that removes an inventory item's link to its checklist item.
  unlink: t('pantry', 'Unlink'),
  whichOne: t('pantry', 'Which one is it?'),
  autoLink: t('pantry', 'Add to shopping list when low'),
  // TRANSLATORS: Caption above the search for a checklist item to link an inventory item to.
  byHand: t('pantry', 'Link by hand'),
}
</script>

<style scoped lang="scss">
.stock-link-panel {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
  width: min(22rem, 90vw);

  &__caption {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast);
  }

  &__linked,
  &__question {
    margin: 0;
  }
}
</style>
