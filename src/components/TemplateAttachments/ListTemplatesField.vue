<template>
  <div>
    <label class="pantry-attach__label">{{ strings.label }}</label>
    <NcSelect
      class="pantry-attach__select"
      :model-value="selected"
      :options="options"
      :multiple="true"
      :close-on-select="false"
      :placeholder="strings.placeholder"
      label="label"
      autocomplete="off"
      @update:model-value="(opts: Option[]) => (model = opts.map((o) => o.id))"
    />
    <p class="pantry-attach__hint">
      {{ everyListNames ? strings.everyListHint(everyListNames) : strings.hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useChecklists } from '@/composables/useChecklist'

const props = defineProps<{
  houseId: number
  /** Templates attached to every list, shown as attached and not offered. */
  everyListTemplateIds: number[]
}>()

/** Ids of the templates attached to this list by name. */
const model = defineModel<number[]>({ required: true })

interface Option {
  id: number
  label: string
}

const templates = useChecklists(props.houseId, 'template')
onMounted(() => void templates.load())

const options = computed<Option[]>(() =>
  templates.lists.value
    .filter((tpl) => !props.everyListTemplateIds.includes(tpl.id))
    .map((tpl) => ({ id: tpl.id, label: tpl.name })),
)

const selected = computed(() => options.value.filter((o) => model.value.includes(o.id)))

const everyListNames = computed(() =>
  templates.lists.value
    .filter((tpl) => props.everyListTemplateIds.includes(tpl.id))
    .map((tpl) => tpl.name)
    .join(', '),
)

const strings = {
  // TRANSLATORS: Section label in the checklist or inventory settings: the templates whose items its add box suggests.
  label: t('pantry', 'Templates:'),
  placeholder: t('pantry', 'No templates'),
  hint: t('pantry', 'Typing a new item suggests matching items from these templates.'),
  everyListHint: (names: string) =>
    // TRANSLATORS: {templates} is a comma-separated list of template names, e.g. "Weekly staples, Party"
    t(
      'pantry',
      'Typing a new item suggests matching items from these templates, and from {templates}, which every list uses.',
      { templates: names },
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
