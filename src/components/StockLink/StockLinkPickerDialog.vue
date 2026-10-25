<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="small"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div class="stock-link-picker">
      <NcSelect
        v-model="selected"
        :options="options"
        :loading="loading"
        :filterable="false"
        :input-label="strings.label"
        :placeholder="strings.placeholder"
        label="label"
        :get-option-key="(option: LinkOption) => option.itemId"
        :calculate-position="ncSelectCalculatePosition"
        data-testid="stock-link-picker"
        @search="onSearch"
      >
        <template #no-options>{{ loading ? strings.loading : strings.empty }}</template>
      </NcSelect>
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton
        variant="primary"
        :disabled="!selected"
        data-testid="stock-link-picker-confirm"
        @click="confirm"
      >
        {{ strings.link }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { listLinkTargets } from '@/api/lists'
import type { StockLinkRow } from '@/api/types'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'
import { rowOptionLabel } from './stockLink'

const SEARCH_DELAY_MS = 250

const props = defineProps<{
  open: boolean
  houseId: number
  inventoryId: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  pick: [row: StockLinkRow]
}>()

/** NcSelect shows an option's `label`. */
type LinkOption = StockLinkRow & { label: string }

const selected = ref<LinkOption | null>(null)
const options = ref<LinkOption[]>([])
const loading = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null
let request = 0

async function load(query: string) {
  const mine = ++request
  loading.value = true
  try {
    const rows = await listLinkTargets(props.houseId, props.inventoryId, query.trim())
    if (mine === request)
      options.value = rows.map((row) => ({ ...row, label: rowOptionLabel(row) }))
  } catch (e) {
    console.error(e)
    if (mine === request) options.value = []
  } finally {
    if (mine === request) loading.value = false
  }
}

function onSearch(query: string) {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => load(query), SEARCH_DELAY_MS)
}

watch(
  () => props.open,
  (open) => {
    if (!open) return
    selected.value = null
    load('')
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

function confirm() {
  if (!selected.value) return
  const { label: _label, ...row } = selected.value
  emit('pick', row)
  emit('update:open', false)
}

const strings = {
  // TRANSLATORS: Title of the dialog for linking an inventory item to a checklist item by hand.
  title: t('pantry', 'Link to a checklist item'),
  // TRANSLATORS: Label of the picker of checklist items to link an inventory item to.
  label: t('pantry', 'Checklist item'),
  placeholder: t('pantry', 'Search checklist items …'),
  loading: t('pantry', 'Searching …'),
  empty: t('pantry', 'No checklist items found'),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, button that links an inventory item to the picked checklist item.
  link: t('pantry', 'Link'),
}
</script>

<style scoped lang="scss">
.stock-link-picker {
  padding-block: 0.5rem;
  min-height: 12rem;
}
</style>
