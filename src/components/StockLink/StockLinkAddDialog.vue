<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="small"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div class="stock-link-add">
      <p class="stock-link-add__hint">{{ hint }}</p>
      <NcSelect
        v-model="selected"
        :options="options"
        :loading="loading"
        :clearable="false"
        :input-label="strings.label"
        label="label"
        :get-option-key="(option: ListOption) => option.listId"
        :calculate-position="ncSelectCalculatePosition"
        data-testid="stock-link-add-list"
      />
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton
        variant="primary"
        :disabled="!selected"
        data-testid="stock-link-add-confirm"
        @click="confirm"
      >
        {{ strings.add }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useChecklists } from '@/composables/useChecklist'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'

const props = withDefaults(
  defineProps<{
    open: boolean
    houseId: number
    /** The inventory item, named in the dialog. */
    itemName: string
    /** The inventory's restock checklist, offered first; null when it has none. */
    restockChecklistId?: number | null
  }>(),
  { restockChecklistId: null },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  pick: [listId: number]
}>()

/** NcSelect shows an option's `label`. */
interface ListOption {
  listId: number
  label: string
}

const { lists, loading, load } = useChecklists(props.houseId, 'checklist')

/** Every checklist, the restock checklist first. */
const options = computed<ListOption[]>(() => {
  const all = lists.value.map((l) => ({ listId: l.id, label: l.name }))
  const restock = all.find((o) => o.listId === props.restockChecklistId)
  return restock ? [restock, ...all.filter((o) => o !== restock)] : all
})

const selected = ref<ListOption | null>(null)

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    selected.value = null
    await load()
    selected.value = options.value[0] ?? null
  },
  { immediate: true },
)

function confirm() {
  if (!selected.value) return
  emit('pick', selected.value.listId)
  emit('update:open', false)
}

const hint = computed(() =>
  t(
    'pantry',
    'Adds "{item}" to the list you pick and links it, so it goes back on it when it runs low.',
    { item: props.itemName },
    { escape: false },
  ),
)

const strings = {
  // TRANSLATORS: Title of the dialog that adds an inventory item to a shopping list and links it there.
  title: t('pantry', 'Add to a shopping list'),
  // TRANSLATORS: Label of the picker of checklists to add an inventory item to.
  label: t('pantry', 'Checklist'),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, button that adds the inventory item to the picked checklist.
  add: t('pantry', 'Add'),
}
</script>

<style scoped lang="scss">
.stock-link-add {
  padding-block: 0.5rem;
  min-height: 10rem;

  &__hint {
    margin: 0 0 0.5rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
