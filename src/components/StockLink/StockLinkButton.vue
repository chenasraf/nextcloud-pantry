<template>
  <NcPopover
    class="stock-link-button"
    :shown="open"
    popup-role="dialog"
    @update:shown="open = $event"
  >
    <template #trigger>
      <NcButton
        variant="tertiary"
        :aria-label="tooltip"
        :title="tooltip"
        data-testid="stock-link-button"
        @click.stop
      >
        <template #icon>
          <span class="stock-link-button__icon" :class="`stock-link-button__icon--${state}`">
            <AlertCircleOutlineIcon v-if="state === 'pick'" :size="20" />
            <LinkOffIcon v-else-if="state === 'off'" :size="20" />
            <LinkVariantIcon v-else :size="20" />
          </span>
        </template>
      </NcButton>
    </template>
    <StockLinkPanel
      :item="item"
      :house-id="houseId"
      :disabled="disabled"
      @link="onLink"
      @unlink="onUnlink"
      @auto-link="$emit('auto-link', $event)"
    />
  </NcPopover>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import AlertCircleOutlineIcon from '@icons/AlertCircleOutline.vue'
import LinkOffIcon from '@icons/LinkOff.vue'
import LinkVariantIcon from '@icons/LinkVariant.vue'
import type { ChecklistItem, StockLinkRow } from '@/api/types'
import StockLinkPanel from './StockLinkPanel.vue'
import { stockLinkState, stockLinkTooltip } from './stockLink'

const props = withDefaults(
  defineProps<{
    item: ChecklistItem
    houseId: number
    disabled?: boolean
  }>(),
  { disabled: false },
)

const emit = defineEmits<{
  link: [row: StockLinkRow]
  unlink: []
  'auto-link': [on: boolean]
}>()

const open = ref(false)
const state = computed(() => stockLinkState(props.item))
const tooltip = computed(() => stockLinkTooltip(props.item))

function onLink(row: StockLinkRow) {
  open.value = false
  emit('link', row)
}

function onUnlink() {
  open.value = false
  emit('unlink')
}
</script>

<style scoped lang="scss">
.stock-link-button__icon {
  display: inline-flex;

  &--linked {
    color: var(--color-primary-element);
  }

  &--pick {
    color: var(--color-element-warning, var(--color-warning));
  }

  &--auto,
  &--off {
    color: var(--color-text-maxcontrast);
  }
}
</style>
