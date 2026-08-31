<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div class="cf-manager">
      <div v-if="loading" class="cf-manager__loading">
        <NcLoadingIcon :size="32" />
      </div>

      <p v-else-if="groups.length === 0 && !creating" class="cf-manager__empty">
        {{ strings.emptyHint }}
      </p>

      <ul v-else ref="listRef" class="cf-acc">
        <template v-for="group in displayGroups" :key="group.key">
          <li class="cf-acc__group">{{ group.title }}</li>
          <template
            v-for="row in group.rows"
            :key="row.type === 'field' ? 'f' + row.field.id : 'drop-' + group.key"
          >
            <li
              v-if="row.type === 'placeholder'"
              class="cf-acc__placeholder"
              @dragover.prevent
              @drop.prevent.stop="commitReorder"
            />
            <li
              v-else
              class="cf-acc__item"
              :class="{ 'cf-acc__item--dragging': draggingId === row.field.id }"
              :data-drag-id="row.field.id"
              @dragover.prevent="onDragOver($event, row.field.id, group.key)"
              @drop.prevent.stop="commitReorder"
            >
              <div class="cf-acc__row">
                <span
                  v-if="canReorder"
                  class="cf-acc__handle"
                  draggable="true"
                  :aria-label="strings.dragHandle"
                  :title="strings.dragHandle"
                  @dragstart="onDragStart($event, row.field.id, group.key)"
                  @dragend="onDragEnd"
                >
                  <DragVerticalIcon :size="18" />
                </span>
                <button
                  type="button"
                  class="cf-acc__head"
                  :aria-expanded="openId === row.field.id"
                  @click="toggle(row.field)"
                >
                  <component
                    :is="fieldTypeIconComponent(row.field.type)"
                    :size="18"
                    class="cf-acc__ticon"
                  />
                  <span class="cf-acc__name">{{ row.field.name }}</span>
                  <span class="cf-acc__type">{{ fieldTypeLabel(row.field.type) }}</span>
                  <BellIcon
                    v-if="row.field.type === 'date' && row.field.notifyDefault"
                    :size="14"
                    class="cf-acc__bell"
                    :title="strings.reminderOn"
                  />
                  <ChevronDownIcon
                    :size="18"
                    class="cf-acc__chev"
                    :class="{ 'cf-acc__chev--open': openId === row.field.id }"
                  />
                </button>
              </div>
              <div v-if="openId === row.field.id" class="cf-acc__body">
                <CustomFieldEditor
                  v-model="draft"
                  :lists="lists"
                  :saving="saving"
                  :error="error"
                  :is-new="false"
                  @save="submit"
                  @delete="confirmDelete(row.field)"
                  @cancel="collapse"
                  @remove-option="onRemoveOption"
                />
              </div>
            </li>
          </template>
        </template>

        <li v-if="creating" class="cf-acc__item cf-acc__item--new">
          <div class="cf-acc__body">
            <CustomFieldEditor
              v-model="draft"
              :lists="lists"
              :saving="saving"
              :error="error"
              :is-new="true"
              @save="submit"
              @delete="collapse"
              @cancel="collapse"
              @remove-option="onRemoveOption"
            />
          </div>
        </li>
      </ul>

      <NcButton
        v-if="!loading"
        variant="secondary"
        class="cf-manager__add"
        :disabled="creating || openId !== null"
        @click="startCreate"
      >
        <template #icon><PlusIcon :size="18" /></template>
        {{ strings.addField }}
      </NcButton>
    </div>

    <NcDialog
      :open="deleting !== null"
      :name="strings.deleteTitle"
      size="small"
      @update:open="
        (v: boolean) => {
          if (!v) deleting = null
        }
      "
    >
      <p>{{ strings.deleteConfirm }}</p>
      <template #actions>
        <NcButton @click="deleting = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" :disabled="saving" @click="submitDelete">
          {{ strings.delete }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      :open="optionDelete !== null"
      :name="strings.remapTitle"
      size="small"
      @update:open="
        (v: boolean) => {
          if (!v) optionDelete = null
        }
      "
    >
      <div v-if="optionDelete" class="cf-remap">
        <p>{{ remapPrompt }}</p>
        <NcSelect
          v-model="remapChoice"
          :options="remapChoices"
          :clearable="false"
          label="label"
          :input-label="''"
          :aria-label-combobox="strings.remapTitle"
          :calculate-position="ncSelectCalculatePosition"
        />
      </div>
      <template #actions>
        <NcButton @click="optionDelete = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" :disabled="saving" @click="submitOptionDelete">
          {{ strings.removeOption }}
        </NcButton>
      </template>
    </NcDialog>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t, n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PlusIcon from '@icons/Plus.vue'
import BellIcon from '@icons/Bell.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import DragVerticalIcon from '@icons/DragVertical.vue'
import type { FieldDefinition } from '@/api/types'
import type { CreateFieldInput, OptionDeleteAction, UpdateFieldPatch } from '@/api/customFields'
import { useCustomFields } from '@/composables/useCustomFields'
import { useChecklists } from '@/composables/useChecklist'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'
import { fieldTypeIconComponent, fieldTypeLabel } from './fieldTypeIcons'
import CustomFieldEditor from './CustomFieldEditor.vue'
import type { DraftOption, FieldDraft } from './draft'
import { blankDraft, draftFromField, draftToCreate, draftToPatch } from './draft'

const props = defineProps<{ open: boolean; houseId: number }>()
defineEmits<{ 'update:open': [value: boolean] }>()

const fields = useCustomFields(props.houseId)
const items = computed(() => fields.items.value)
const loading = computed(() => fields.loading.value)
const { lists, load: loadLists } = useChecklists(props.houseId)

const openId = ref<number | null>(null)
const creating = ref(false)
const draft = ref<FieldDraft>(blankDraft())
const saving = ref(false)
const error = ref<string | null>(null)
const deleting = ref<FieldDefinition | null>(null)

/** A `select` option removal awaiting a remap-or-clear choice. */
const optionDelete = ref<{ index: number; option: DraftOption } | null>(null)

interface RemapChoice {
  value: number | 'clear'
  label: string
}
const remapChoice = ref<RemapChoice | null>(null)

const strings = {
  title: t('pantry', 'Custom fields'),
  emptyHint: t('pantry', 'No custom fields yet. Add one to attach extra info to items.'),
  addField: t('pantry', 'Add field'),
  dragHandle: t('pantry', 'Drag to reorder'),
  // TRANSLATORS: Header for fields available on every list (not tied to one list)
  allLists: t('pantry', 'All lists'),
  reminderOn: t('pantry', 'Reminder on'),
  deleteTitle: t('pantry', 'Delete field'),
  deleteConfirm: t('pantry', 'Delete this field? Values already set on items are kept but hidden.'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
  remapTitle: t('pantry', 'Option in use'),
  removeOption: t('pantry', 'Remove option'),
  // TRANSLATORS: Choice; empties the option from every item that had it.
  clearValues: t('pantry', 'Clear it from those items'),
}

interface Group {
  key: string
  title: string
  fields: FieldDefinition[]
}

const groups = computed<Group[]>(() => {
  const out: Group[] = []
  const globals = items.value.filter((f) => f.listId == null)
  if (globals.length) {
    out.push({ key: 'all', title: strings.allLists, fields: globals })
  }
  for (const list of lists.value) {
    const scoped = items.value.filter((f) => f.listId === list.id)
    if (scoped.length) {
      out.push({ key: `list-${list.id}`, title: list.name, fields: scoped })
    }
  }
  // Fields whose list isn't loaded still need a home.
  const knownListIds = new Set(lists.value.map((l) => l.id))
  const orphans = items.value.filter((f) => f.listId != null && !knownListIds.has(f.listId))
  if (orphans.length) {
    out.push({ key: 'orphans', title: t('pantry', 'Other lists'), fields: orphans })
  }
  return out
})

// -------- Drag reorder --------
// Fields carry a single house-wide sort_order but are displayed grouped by scope,
// so a drag is confined to its own group (moving across scopes would change the
// field's list, which reorder does not do). On drop the whole sequence is
// renumbered with the reordered group substituted in, keeping groups intact.
// Reordering is disabled while an editor is expanded — the head is a toggle.
const listRef = ref<HTMLElement | null>(null)
const draggingId = ref<number | null>(null)
const draggingGroupKey = ref<string | null>(null)
const dropIndex = ref<number | null>(null)

const canReorder = computed(() => openId.value === null && !creating.value)

type FieldRow = { type: 'field'; field: FieldDefinition } | { type: 'placeholder' }
interface DisplayGroup {
  key: string
  title: string
  rows: FieldRow[]
}

const displayGroups = computed<DisplayGroup[]>(() =>
  groups.value.map((g) => {
    if (draggingId.value === null || dropIndex.value === null || draggingGroupKey.value !== g.key) {
      return {
        key: g.key,
        title: g.title,
        rows: g.fields.map((f) => ({ type: 'field', field: f })),
      }
    }
    const rows: FieldRow[] = g.fields
      .filter((f) => f.id !== draggingId.value)
      .map((f) => ({ type: 'field' as const, field: f }))
    rows.splice(Math.min(dropIndex.value, rows.length), 0, { type: 'placeholder' })
    return { key: g.key, title: g.title, rows }
  }),
)

function groupKeyOf(fieldId: number): string | null {
  for (const g of groups.value) {
    if (g.fields.some((f) => f.id === fieldId)) return g.key
  }
  return null
}

function onDragStart(e: DragEvent, id: number, groupKey: string): void {
  if (!canReorder.value || !e.dataTransfer) return
  draggingId.value = id
  draggingGroupKey.value = groupKey
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(id))
  const row = (e.currentTarget as HTMLElement).closest<HTMLElement>('.cf-acc__item')
  if (row) e.dataTransfer.setDragImage(row, 0, 0)
}

function onDragEnd(): void {
  draggingId.value = null
  draggingGroupKey.value = null
  dropIndex.value = null
}

function computeDropIndex(
  hoveredId: number,
  hoveredGroupKey: string | null,
  clientY: number,
  target: HTMLElement | null,
): void {
  const dragId = draggingId.value
  if (!dragId || dragId === hoveredId) return
  if (hoveredGroupKey !== draggingGroupKey.value) return
  const group = groups.value.find((g) => g.key === draggingGroupKey.value)
  if (!group) return
  const without = group.fields.filter((f) => f.id !== dragId)
  const idx = without.findIndex((f) => f.id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    dropIndex.value = clientY > rect.top + rect.height / 2 ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onDragOver(e: DragEvent, hoveredId: number, hoveredGroupKey: string): void {
  computeDropIndex(hoveredId, hoveredGroupKey, e.clientY, e.currentTarget as HTMLElement | null)
}

async function commitReorder(): Promise<void> {
  const dragId = draggingId.value
  const idx = dropIndex.value
  const gk = draggingGroupKey.value
  draggingId.value = null
  draggingGroupKey.value = null
  dropIndex.value = null
  if (dragId === null || idx === null) return

  const group = groups.value.find((g) => g.key === gk)
  const dragged = group?.fields.find((f) => f.id === dragId)
  if (!group || !dragged) return

  const without = group.fields.filter((f) => f.id !== dragId)
  without.splice(Math.min(idx, without.length), 0, dragged)

  // Renumber the whole house-wide sequence with the reordered group substituted,
  // so sort_order stays one coherent order that also keeps groups intact.
  const flat: FieldDefinition[] = []
  for (const g of groups.value) {
    flat.push(...(g.key === gk ? without : g.fields))
  }
  const entries = flat.map((f, i) => ({ id: f.id, sortOrder: i }))
  try {
    await fields.reorder(entries)
  } catch (e) {
    error.value = (e as Error).message
  }
}

// dragend on the source can be missed when the placeholder shuffles the DOM;
// a capture listener on the list guarantees the drag state is cleared.
watch(listRef, (el, old) => {
  old?.removeEventListener('dragend', onDragEnd, true)
  el?.addEventListener('dragend', onDragEnd, true)
})
onBeforeUnmount(() => listRef.value?.removeEventListener('dragend', onDragEnd, true))

useTouchReorder(
  listRef,
  {
    onDragStart: (id) => {
      draggingId.value = id
      draggingGroupKey.value = groupKeyOf(id)
      dropIndex.value = null
    },
    onReorderOver: (hoveredId, _clientX, clientY) => {
      const el = listRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
      computeDropIndex(hoveredId, groupKeyOf(hoveredId), clientY, el)
    },
    onDrop: commitReorder,
    onCancel: onDragEnd,
  },
  canReorder,
)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      collapse()
      // useChecklists.load() always refetches (no loaded-guard) and mutates the
      // shared list state, which flashes the checklist index. We only need the
      // lists for scope grouping, so reuse what the index already loaded.
      if (lists.value.length === 0) void loadLists()
      void fields.load()
    }
  },
  { immediate: true },
)

function collapse(): void {
  openId.value = null
  creating.value = false
  error.value = null
}

function toggle(field: FieldDefinition): void {
  if (openId.value === field.id) {
    collapse()
    return
  }
  creating.value = false
  error.value = null
  openId.value = field.id
  draft.value = draftFromField(field)
}

function startCreate(): void {
  openId.value = null
  error.value = null
  creating.value = true
  draft.value = blankDraft()
}

async function submit(): Promise<void> {
  const name = draft.value.name.trim()
  if (!name) {
    error.value = t('pantry', 'Name cannot be empty')
    return
  }
  saving.value = true
  error.value = null
  try {
    if (creating.value) {
      const input: CreateFieldInput = draftToCreate(draft.value)
      await fields.create(input)
    } else if (openId.value !== null) {
      const patch: UpdateFieldPatch = draftToPatch(draft.value)
      await fields.update(openId.value, patch)
    }
    collapse()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    saving.value = false
  }
}

function confirmDelete(field: FieldDefinition): void {
  deleting.value = field
}

async function submitDelete(): Promise<void> {
  if (!deleting.value) return
  saving.value = true
  try {
    await fields.remove(deleting.value.id)
    deleting.value = null
    collapse()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    saving.value = false
  }
}

// A persisted option that already carries values can't just vanish — its item
// values must be moved to another option or cleared first. Fresh (unsaved) or
// unused options drop straight out of the draft.
function onRemoveOption(index: number): void {
  const opt = draft.value.options[index]
  if (!opt) return
  if (!creating.value && openId.value !== null && opt.id !== null && opt.valueCount > 0) {
    remapChoice.value = remapChoices.value[0] ?? null
    optionDelete.value = { index, option: opt }
  } else {
    draft.value.options.splice(index, 1)
  }
}

const remapChoices = computed<RemapChoice[]>(() => {
  const removing = optionDelete.value?.option
  const targets = draft.value.options
    .filter((o) => o.id !== null && o.label.trim() !== '' && o.id !== removing?.id)
    .map<RemapChoice>((o) => ({
      value: o.id as number,
      // TRANSLATORS: Choice; moves item values onto another option. {label} is that option's name.
      label: t('pantry', 'Move it to {label}', { label: o.label.trim() }),
    }))
  return [...targets, { value: 'clear', label: strings.clearValues }]
})

const remapPrompt = computed(() => {
  const opt = optionDelete.value?.option
  if (!opt) return ''
  return n(
    'pantry',
    '%n item uses the option "{label}". What should happen to it?',
    '%n items use the option "{label}". What should happen to them?',
    opt.valueCount,
    { label: opt.label.trim() },
  )
})

async function submitOptionDelete(): Promise<void> {
  const pending = optionDelete.value
  const choice = remapChoice.value
  if (!pending || !choice || openId.value === null || pending.option.id === null) return
  const action: OptionDeleteAction = choice.value === 'clear' ? 'clear' : 'remap'
  const remapToId = choice.value === 'clear' ? undefined : choice.value
  saving.value = true
  error.value = null
  try {
    const updated = await fields.removeOption(openId.value, pending.option.id, action, remapToId)
    draft.value.options.splice(pending.index, 1)
    if (draft.value.defaultOptionId === pending.option.id) {
      draft.value.defaultOptionId = remapToId ?? null
    }
    syncOptionCounts(updated)
    optionDelete.value = null
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    saving.value = false
  }
}

// Refresh the surviving draft options' counts from the server's fresh totals
// (a remap bumps the target's count) without disturbing unsaved label edits.
function syncOptionCounts(updated: FieldDefinition): void {
  const counts = new Map(updated.options.map((o) => [o.id, o.valueCount]))
  for (const opt of draft.value.options) {
    if (opt.id !== null && counts.has(opt.id)) {
      opt.valueCount = counts.get(opt.id)!
    }
  }
}
</script>

<style scoped lang="scss">
.cf-manager {
  min-width: 340px;
  padding: 0.25rem 0;

  &__loading {
    display: flex;
    justify-content: center;
    padding: 2rem 0;
  }

  &__empty {
    color: var(--color-text-maxcontrast);
    margin: 0 0 0.75rem 0;
  }

  &__add {
    margin-top: 0.25rem;
  }
}

.cf-acc {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0;

  &__group {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-maxcontrast);
    padding: 0.9rem 0 0.35rem;
  }

  &__item {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    margin-bottom: 0.4rem;
    overflow: hidden;

    &--new {
      border-color: var(--color-primary-element);
    }

    &--dragging {
      opacity: 0.4;
    }
  }

  &__row {
    display: flex;
    align-items: stretch;
  }

  &__handle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    padding-left: 8px;
    cursor: grab;
    color: var(--color-text-maxcontrast);
    background: var(--color-main-background);
    touch-action: none;
  }

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 9px 11px;
    background: var(--color-main-background);
    border: none;
    cursor: pointer;
    text-align: start;

    &:hover {
      background: var(--color-background-hover);
    }
  }

  &__placeholder {
    min-height: 42px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    margin-bottom: 0.4rem;
  }

  &__ticon {
    color: var(--color-primary-element);
    flex-shrink: 0;
  }

  &__name {
    flex: 1;
    font-weight: 500;
  }

  &__type {
    font-size: 0.78rem;
    color: var(--color-text-maxcontrast);
  }

  &__bell {
    color: var(--color-primary-element);
  }

  &__chev {
    color: var(--color-text-maxcontrast);
    transition: transform 0.15s ease;

    &--open {
      transform: rotate(180deg);
    }
  }

  &__body {
    padding: 0.75rem 0.9rem 0.9rem;
    border-top: 1px solid var(--color-border);
  }
}

@media (max-width: 500px) {
  .cf-manager {
    min-width: 0;
  }
}
</style>
