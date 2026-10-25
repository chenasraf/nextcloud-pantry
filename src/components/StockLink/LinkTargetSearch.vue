<template>
  <div class="link-target-search">
    <NcTextField
      v-model="query"
      :label="strings.searchLabel"
      :placeholder="strings.searchPlaceholder"
      autocomplete="off"
      data-testid="link-target-search"
    />
    <p v-if="loading" class="link-target-search__note">{{ strings.loading }}</p>
    <p v-else-if="results.length === 0" class="link-target-search__note">{{ strings.empty }}</p>
    <ul v-else class="link-target-search__results">
      <li v-for="row in results" :key="row.itemId">
        <NcButton
          variant="tertiary"
          wide
          alignment="start"
          :disabled="disabled"
          data-testid="link-target"
          @click="$emit('pick', row)"
        >
          {{ rowOptionLabel(row) }}
        </NcButton>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { listLinkTargets } from '@/api/lists'
import type { StockLinkRow } from '@/api/types'
import { rowOptionLabel } from './stockLink'

const SEARCH_DELAY_MS = 250

const props = withDefaults(
  defineProps<{
    houseId: number
    inventoryId: number
    disabled?: boolean
  }>(),
  { disabled: false },
)

defineEmits<{
  pick: [row: StockLinkRow]
}>()

const query = ref('')
const results = ref<StockLinkRow[]>([])
const loading = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null
let request = 0

async function search() {
  const mine = ++request
  loading.value = true
  try {
    const rows = await listLinkTargets(props.houseId, props.inventoryId, query.value.trim())
    if (mine === request) results.value = rows
  } catch (e) {
    console.error(e)
    if (mine === request) results.value = []
  } finally {
    if (mine === request) loading.value = false
  }
}

watch(query, () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(search, SEARCH_DELAY_MS)
})

search()

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

const strings = {
  // TRANSLATORS: Accessible name of the field searching checklist items to link an inventory item to.
  searchLabel: t('pantry', 'Search checklist items'),
  searchPlaceholder: t('pantry', 'Search checklist items …'),
  loading: t('pantry', 'Searching …'),
  empty: t('pantry', 'No checklist items found'),
}
</script>

<style scoped lang="scss">
.link-target-search {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__results {
    display: flex;
    flex-direction: column;
    max-height: 12rem;
    overflow-y: auto;
  }

  &__note {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
