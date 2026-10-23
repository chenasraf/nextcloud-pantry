<template>
  <div>
    <label class="pantry-attach__label">{{ strings.label }}</label>
    <NcCheckboxRadioSwitch v-model="everyList">
      {{ strings.everyList }}
    </NcCheckboxRadioSwitch>
    <NcSelect
      v-if="!everyList"
      class="pantry-attach__select"
      :model-value="selected"
      :options="options"
      :multiple="true"
      :close-on-select="false"
      :placeholder="strings.placeholder"
      label="label"
      autocomplete="off"
      @update:model-value="(opts: Option[]) => setListIds(opts.map((o) => o.id))"
    />
    <p class="pantry-attach__hint">{{ strings.hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { TemplateLists } from '@/api/types'
import { useAllKindLists } from '@/composables/useChecklist'
import { listKindStrings } from '@/composables/useListKind'

const props = defineProps<{ houseId: number }>()

const model = defineModel<TemplateLists>({ required: true })

interface Option {
  id: number
  label: string
}

const allLists = useAllKindLists(props.houseId)
onMounted(() => void allLists.load())

// Templates never attach to templates. Each option names its kind, since a
// checklist and an inventory can share a name.
const options = computed<Option[]>(() =>
  allLists.lists.value
    .filter((l) => l.kind !== 'template')
    .map((l) => ({
      id: l.id,
      // TRANSLATORS: Option in a list picker. {list} is the list's name, {kind} is "Checklist" or "Inventory".
      label: t('pantry', '{list} ({kind})', {
        list: l.name,
        kind: listKindStrings(l.kind ?? 'checklist').name,
      }),
    })),
)

const selected = computed(() => options.value.filter((o) => model.value.listIds.includes(o.id)))

const everyList = computed({
  get: () => model.value.everyList,
  set: (value: boolean) => (model.value = { ...model.value, everyList: value }),
})

function setListIds(listIds: number[]) {
  model.value = { ...model.value, listIds }
}

const strings = {
  // TRANSLATORS: Section label in the template settings: the checklists and inventories whose add box suggests this template's items.
  label: t('pantry', 'Suggest in:'),
  // TRANSLATORS: Switch in the template settings that attaches the template to every checklist and inventory in the house.
  everyList: t('pantry', 'Every list'),
  placeholder: t('pantry', 'No lists'),
  hint: t(
    'pantry',
    'Typing a new item on these lists suggests matching items from this template. Selecting "Every list" includes lists made later.',
  ),
}
</script>

<style scoped lang="scss">
.pantry-attach__label {
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 600;
}

.pantry-attach__select {
  width: 100%;
}

.pantry-attach__hint {
  margin-top: 0.25rem;
  color: var(--color-text-maxcontrast);
  font-size: 0.875rem;
}
</style>
