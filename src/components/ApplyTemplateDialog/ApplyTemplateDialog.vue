<template>
  <NcDialog
    :name="title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="loading" class="pantry-apply__loading">
      <NcLoadingIcon :size="32" />
    </div>

    <div v-else-if="step === 'pick'" class="pantry-apply__pick">
      <p v-if="pickGroups.length === 0" class="pantry-apply__empty">{{ pickEmpty }}</p>
      <template v-for="group in pickGroups" :key="group.key">
        <h3 class="pantry-apply__group">{{ group.title }}</h3>
        <NcButton
          v-for="list in group.lists"
          :key="list.id"
          class="pantry-apply__pick-button"
          wide
          @click="choose(list)"
        >
          <template #icon>
            <component :is="checklistIconComponent(list.icon)" :size="20" />
          </template>
          {{ list.name }}
        </NcButton>
      </template>
    </div>

    <div v-else class="pantry-apply__review">
      <p class="pantry-apply__summary">{{ reviewSummary }}</p>
      <p v-if="rows.length === 0" class="pantry-apply__empty">{{ strings.noItems }}</p>
      <ul v-else class="pantry-apply__items">
        <li v-for="row in rows" :key="row.item.id" class="pantry-apply__item">
          <NcCheckboxRadioSwitch
            :model-value="selected.has(row.item.id)"
            @update:model-value="(on: boolean) => toggle(row.item.id, on)"
          >
            <span class="pantry-apply__name">{{ row.item.name }}</span>
            <span v-if="row.item.quantity" class="pantry-apply__quantity">
              {{ row.item.quantity }}
            </span>
            <span v-if="row.alreadyOpen" class="pantry-apply__already">
              {{ strings.alreadyOnList }}
            </span>
          </NcCheckboxRadioSwitch>
        </li>
      </ul>
    </div>

    <template #actions>
      <NcButton v-if="step === 'review' && canGoBack" :disabled="submitting" @click="back">
        {{ strings.back }}
      </NcButton>
      <NcButton :disabled="submitting" @click="$emit('update:open', false)">
        {{ strings.cancel }}
      </NcButton>
      <NcButton
        v-if="step === 'review'"
        class="pantry-apply__confirm"
        variant="primary"
        :disabled="submitting || selected.size === 0"
        @click="submit"
      >
        <template v-if="submitting" #icon><NcLoadingIcon :size="20" /></template>
        {{ applyLabel }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { n, t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import * as api from '@/api/lists'
import type { Checklist, ChecklistItem, ListKind } from '@/api/types'
import { checklistIconComponent } from '@/components/ChecklistIconPicker/checklistIcons'
import { listKindStrings } from '@/composables/useListKind'
import { itemNameKey } from '@/utils/string'

/**
 * Applying copies a template's items into a checklist or inventory that
 * already exists. Open it from the template (`template` set, the member picks
 * the list) or from the list (`target` set, the member picks the template);
 * either way the member then reviews the items before they are copied.
 */
const props = defineProps<{
  open: boolean
  houseId: number
  template?: Checklist | null
  target?: Checklist | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  applied: [result: api.BatchCopyResult, target: Checklist]
}>()

interface PickGroup {
  key: string
  title: string
  lists: Checklist[]
}

interface Row {
  item: ChecklistItem
  /** Already on the list, active and unchecked: applying would change nothing. */
  alreadyOpen: boolean
}

const TARGET_KINDS: ListKind[] = ['checklist', 'inventory']

const step = ref<'pick' | 'review'>('pick')
const loading = ref(false)
const submitting = ref(false)
const pickGroups = ref<PickGroup[]>([])
const chosenTemplate = ref<Checklist | null>(null)
const chosenTarget = ref<Checklist | null>(null)
const rows = ref<Row[]>([])
const selected = ref(new Set<number>())

const canGoBack = computed(() => !(props.template && props.target))

watch(
  () => [props.open, props.template?.id, props.target?.id] as const,
  ([isOpen]) => {
    if (isOpen) void start()
  },
  { immediate: true },
)

async function start() {
  chosenTemplate.value = props.template ?? null
  chosenTarget.value = props.target ?? null
  rows.value = []
  selected.value = new Set()
  if (chosenTemplate.value && chosenTarget.value) {
    await review()
    return
  }
  step.value = 'pick'
  await run(async () => {
    pickGroups.value = chosenTemplate.value ? await targetGroups() : await templateGroups()
  })
}

async function targetGroups(): Promise<PickGroup[]> {
  const byKind = await Promise.all(
    TARGET_KINDS.map((kind) => api.listApi(kind).listLists(props.houseId)),
  )
  return TARGET_KINDS.map((kind, i) => ({
    key: kind,
    title: listKindStrings(kind).title,
    lists: byKind[i],
  })).filter((group) => group.lists.length > 0)
}

/** The list's attached templates first, then the rest of the house's. */
async function templateGroups(): Promise<PickGroup[]> {
  const target = chosenTarget.value!
  const [templates, attached] = await Promise.all([
    api.listApi('template').listLists(props.houseId),
    api.listApi(target.kind ?? 'checklist').getAttachedTemplates(props.houseId, target.id),
  ])
  const attachedIds = new Set([...attached.templateIds, ...attached.everyListTemplateIds])
  return [
    {
      key: 'attached',
      title: strings.attachedTemplates,
      lists: templates.filter((tpl) => attachedIds.has(tpl.id)),
    },
    {
      key: 'other',
      title: attachedIds.size > 0 ? strings.otherTemplates : listKindStrings('template').title,
      lists: templates.filter((tpl) => !attachedIds.has(tpl.id)),
    },
  ].filter((group) => group.lists.length > 0)
}

function choose(list: Checklist) {
  if (chosenTemplate.value) {
    chosenTarget.value = list
  } else {
    chosenTemplate.value = list
  }
  void review()
}

function back() {
  if (props.template) {
    chosenTarget.value = null
  } else {
    chosenTemplate.value = null
  }
  step.value = 'pick'
}

async function review() {
  const template = chosenTemplate.value!
  const target = chosenTarget.value!
  step.value = 'review'
  await run(async () => {
    const [templateItems, targetItems] = await Promise.all([
      api.listApi('template').listItems(props.houseId, template.id),
      api.listApi(target.kind ?? 'checklist').listItems(props.houseId, target.id),
    ])
    const open = new Set(targetItems.filter((i) => !i.done).map((i) => itemNameKey(i.name)))
    rows.value = templateItems.map((item) => ({
      item,
      alreadyOpen: open.has(itemNameKey(item.name)),
    }))
    selected.value = new Set(rows.value.filter((r) => !r.alreadyOpen).map((r) => r.item.id))
  })
}

async function run(load: () => Promise<void>) {
  loading.value = true
  try {
    await load()
  } catch (e) {
    showError((e as Error).message)
    emit('update:open', false)
  } finally {
    loading.value = false
  }
}

function toggle(id: number, on: boolean) {
  const next = new Set(selected.value)
  if (on) next.add(id)
  else next.delete(id)
  selected.value = next
}

async function submit() {
  const target = chosenTarget.value
  if (!target || selected.value.size === 0 || submitting.value) return
  submitting.value = true
  try {
    const ids = rows.value.map((r) => r.item.id).filter((id) => selected.value.has(id))
    const result = await api.batchCopyItems(props.houseId, ids, target.id)
    showSuccess(appliedMessage(result, target))
    emit('applied', result, target)
    emit('update:open', false)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    submitting.value = false
  }
}

function appliedMessage(result: api.BatchCopyResult, target: Checklist): string {
  const reused = result.reused.length
  const added = result.items.length - reused
  if (reused === 0) {
    // TRANSLATORS: Toast after applying a template. {list} is the list the items went into.
    return n('pantry', 'Added %n item to {list}', 'Added %n items to {list}', added, {
      list: target.name,
    })
  }
  if (added === 0) {
    return n(
      'pantry',
      '%n item was already on {list} and was brought back',
      '%n items were already on {list} and were brought back',
      reused,
      { list: target.name },
    )
  }
  // TRANSLATORS: Toast after applying a template. {added} counts new items, {reused} counts items already on the list {list} that were brought back.
  return t('pantry', '{list}: {added} added, {reused} brought back', {
    list: target.name,
    added: String(added),
    reused: String(reused),
  })
}

const title = computed(() =>
  props.template ? strings.applyToListTitle : strings.applyTemplateTitle,
)

const pickEmpty = computed(() =>
  chosenTemplate.value ? strings.noTargets : listKindStrings('template').emptyTitle,
)

const reviewSummary = computed(() =>
  // TRANSLATORS: Shown above the template's items while applying it. {template} and {list} are names.
  t('pantry', 'Copy the ticked items from {template} into {list}.', {
    template: chosenTemplate.value?.name ?? '',
    list: chosenTarget.value?.name ?? '',
  }),
)

const applyLabel = computed(() =>
  // TRANSLATORS: Verb, dialog button that copies the ticked template items into the list. %n is how many.
  n('pantry', 'Apply %n item', 'Apply %n items', selected.value.size),
)

const strings = {
  // TRANSLATORS: Dialog title, opened from a template, for choosing the checklist or inventory to copy its items into.
  applyToListTitle: t('pantry', 'Apply to list'),
  // TRANSLATORS: Dialog title, opened from a checklist or inventory, for choosing a template whose items to copy in.
  applyTemplateTitle: t('pantry', 'Apply template'),
  // TRANSLATORS: Heading above the templates attached to this list.
  attachedTemplates: t('pantry', 'Attached to this list'),
  // TRANSLATORS: Heading above the templates not attached to this list.
  otherTemplates: t('pantry', 'Other templates'),
  noTargets: t('pantry', 'There are no checklists or inventories to apply this template to.'),
  noItems: t('pantry', 'This template has no items.'),
  // TRANSLATORS: Tag beside a template item whose name is already an unchecked item on the list.
  alreadyOnList: t('pantry', 'Already on list'),
  back: t('pantry', 'Back'),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-apply {
  &__loading {
    display: flex;
    justify-content: center;
    padding: 2rem 0;
  }

  &__pick {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0;
  }

  &__group {
    margin: 0.5rem 0 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);

    &:first-child {
      margin-top: 0;
    }
  }

  &__summary,
  &__empty {
    margin: 0.5rem 0;
    color: var(--color-text-maxcontrast);
  }

  &__items {
    display: flex;
    flex-direction: column;
    max-height: 50vh;
    overflow-y: auto;
    padding-bottom: 0.5rem;
  }

  &__quantity,
  &__already {
    margin-inline-start: 0.5rem;
    color: var(--color-text-maxcontrast);
  }

  &__already {
    font-size: 0.8125rem;
    font-style: italic;
  }
}
</style>
