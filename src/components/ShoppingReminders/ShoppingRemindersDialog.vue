<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p class="reminders__intro">{{ strings.intro }}</p>

      <div ref="rootRef" class="reminders__groups">
        <section v-for="group in groups" :key="group.id" class="reminders__group">
          <h4 class="reminders__group-head">{{ group.label }}</h4>

          <p v-if="itemsFor(group.id).length === 0" class="reminders__group-empty">
            {{ strings.groupEmpty }}
          </p>

          <ul v-else class="reminders__list">
            <template v-for="gi in gridItems(group.id)" :key="gi.key">
              <li
                v-if="gi.type === 'placeholder'"
                class="reminders__placeholder"
                @dragover.prevent
                @drop.prevent.stop="commitReorder"
              />
              <li
                v-else
                class="reminders__item"
                :class="{ 'reminders__item--dragging': dragId === gi.reminder.id }"
                :data-drag-id="gi.reminder.id"
                :draggable="dragHandleId === gi.reminder.id"
                @dragstart="onDragStart($event, gi.reminder.id, group.id)"
                @dragend="onDragEnd"
                @dragover.prevent="onDragOver($event, gi.reminder.id, group.id)"
                @drop.prevent.stop="commitReorder"
              >
                <span
                  class="reminders__handle"
                  :aria-label="strings.dragHandle"
                  :title="strings.dragHandle"
                  @mousedown="dragHandleId = gi.reminder.id"
                  @mouseup="dragHandleId = null"
                >
                  <DragVerticalIcon :size="20" />
                </span>
                <NcCheckboxRadioSwitch
                  type="switch"
                  class="reminders__switch"
                  :model-value="gi.reminder.enabled"
                  :aria-label="strings.enabledLabel"
                  @update:model-value="saveEnabled(gi.reminder, $event)"
                />
                <input
                  :value="gi.reminder.text"
                  type="text"
                  class="reminders__text"
                  :aria-label="strings.textLabel"
                  @change="saveText(gi.reminder, $event)"
                />
                <NcSelect
                  class="reminders__moment"
                  :model-value="momentOption(gi.reminder.showOn)"
                  :options="momentOptions"
                  :clearable="false"
                  :searchable="false"
                  label="label"
                  :aria-label="strings.momentLabel"
                  @update:model-value="saveMoment(gi.reminder, $event)"
                />
                <NcButton
                  variant="tertiary"
                  :aria-label="strings.deleteLabel"
                  :disabled="busy"
                  @click="remove(gi.reminder)"
                >
                  <template #icon><DeleteIcon :size="18" /></template>
                </NcButton>
              </li>
            </template>
          </ul>
        </section>
      </div>

      <!-- Inline add form -->
      <form class="reminders__add" @submit.prevent="add">
        <input
          v-model="newText"
          type="text"
          class="reminders__text"
          :placeholder="strings.newPlaceholder"
          :aria-label="strings.newTextLabel"
        />
        <NcSelect
          class="reminders__moment"
          :model-value="momentOption(newMoment)"
          :options="momentOptions"
          :clearable="false"
          :searchable="false"
          label="label"
          :aria-label="strings.momentLabel"
          @update:model-value="(o) => (newMoment = o?.id ?? 'on_start')"
        />
        <NcButton type="submit" variant="primary" :disabled="!newText.trim() || busy">
          <template #icon><PlusIcon :size="20" /></template>
          {{ strings.add }}
        </NcButton>
      </form>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import DragVerticalIcon from '@icons/DragVertical.vue'
import { useReminders } from '@/composables/useReminders'
import { useTouchReorder } from '@/composables/useTouchReorder'
import type { ShoppingReminder, ShoppingReminderMoment } from '@/api/types'

const props = defineProps<{
  open: boolean
  houseId: number
}>()
defineEmits<{
  'update:open': [value: boolean]
}>()

const reminders = useReminders(props.houseId)
const items = reminders.items
const loading = reminders.loading
// Guards concurrent writes so reorder/save/delete don't race each other.
const busy = ref(false)

const newText = ref('')
const newMoment = ref<ShoppingReminderMoment>('on_start')

onMounted(() => void reminders.load())
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void reminders.load()
  },
)

interface MomentOption {
  id: ShoppingReminderMoment
  label: string
}
// The three moments, in fixed display order — each its own drag-reorder group.
const groups: MomentOption[] = [
  // TRANSLATORS: Reminder group — prompts shown on the start / scope screen.
  { id: 'on_start', label: t('pantry', 'At start') },
  // TRANSLATORS: Reminder group — prompts shown when moving to the next store.
  { id: 'on_store_advance', label: t('pantry', 'Between shops') },
  // TRANSLATORS: Reminder group — prompts shown on the review / finish screen.
  { id: 'on_close', label: t('pantry', 'At end') },
]
const momentOptions: MomentOption[] = groups
function momentOption(moment: ShoppingReminderMoment): MomentOption | null {
  return momentOptions.find((o) => o.id === moment) ?? null
}

// The reminders of one moment, in position order (items are pre-sorted).
function itemsFor(moment: ShoppingReminderMoment): ShoppingReminder[] {
  return items.value.filter((r) => r.showOn === moment)
}

async function withBusy(fn: () => Promise<unknown>, failMsg: string) {
  if (busy.value) return
  busy.value = true
  try {
    await fn()
  } catch (e) {
    showError((e as Error).message || failMsg)
  } finally {
    busy.value = false
  }
}

async function add() {
  const text = newText.value.trim()
  if (!text) return
  await withBusy(async () => {
    await reminders.create({ text, showOn: newMoment.value })
    newText.value = ''
  }, strings.addFailed)
}

async function saveText(reminder: ShoppingReminder, e: Event) {
  const text = (e.target as HTMLInputElement).value.trim()
  if (text === '' || text === reminder.text) return
  await withBusy(() => reminders.update(reminder.id, { text }), strings.saveFailed)
}

async function saveEnabled(reminder: ShoppingReminder, enabled: boolean) {
  await withBusy(() => reminders.update(reminder.id, { enabled }), strings.saveFailed)
}

async function remove(reminder: ShoppingReminder) {
  await withBusy(() => reminders.remove(reminder.id), strings.deleteFailed)
}

// Changing a reminder's moment moves it to another group; drop it at the end of
// the target group and renormalise positions so surfacing order stays clean.
async function saveMoment(reminder: ShoppingReminder, option: MomentOption | null) {
  if (!option || option.id === reminder.showOn) return
  const target = option.id
  await withBusy(async () => {
    await reminders.update(reminder.id, { showOn: target })
    const targetIds = itemsFor(target)
      .map((r) => r.id)
      .filter((id) => id !== reminder.id)
    targetIds.push(reminder.id)
    await reminders.reorder(fullOrderWith(target, targetIds))
  }, strings.saveFailed)
}

// -------- Drag & drop reorder (within a group only) --------

type GridItem =
  { type: 'item'; key: string; reminder: ShoppingReminder } | { type: 'placeholder'; key: string }

const dragId = ref<number | null>(null)
const dragMoment = ref<ShoppingReminderMoment | null>(null)
const dropIndex = ref<number | null>(null)
// Set on handle mousedown so only a handle-initiated gesture makes the row
// draggable — the text input and moment select stay interactive.
const dragHandleId = ref<number | null>(null)
const rootRef = ref<HTMLElement | null>(null)

function gridItems(moment: ShoppingReminderMoment): GridItem[] {
  const source = itemsFor(moment)
  if (dragMoment.value !== moment || dragId.value === null || dropIndex.value === null) {
    return source.map((r) => ({ type: 'item' as const, key: 'r-' + r.id, reminder: r }))
  }
  const without = source.filter((r) => r.id !== dragId.value)
  const out: GridItem[] = without.map((r) => ({
    type: 'item' as const,
    key: 'r-' + r.id,
    reminder: r,
  }))
  const clamped = Math.min(dropIndex.value, out.length)
  out.splice(clamped, 0, { type: 'placeholder', key: 'drop-placeholder' })
  return out
}

// Build the full house order with one group's ids overridden — the other groups
// keep their current order. Fixed group sequence keeps positions deterministic.
function fullOrderWith(moment: ShoppingReminderMoment, orderedIds: number[]): number[] {
  const out: number[] = []
  for (const g of groups) {
    if (g.id === moment) out.push(...orderedIds)
    else out.push(...itemsFor(g.id).map((r) => r.id))
  }
  return out
}

function momentOf(id: number): ShoppingReminderMoment | null {
  return items.value.find((r) => r.id === id)?.showOn ?? null
}

function onDragStart(e: DragEvent, id: number, moment: ShoppingReminderMoment) {
  if (!e.dataTransfer) return
  dragId.value = id
  dragMoment.value = moment
  dropIndex.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(id))
}

function onDragEnd() {
  dragId.value = null
  dragMoment.value = null
  dropIndex.value = null
  dragHandleId.value = null
}

function computeDropIndex(hoveredId: number, clientY: number, target: HTMLElement | null) {
  // Only reorder within the dragged item's own group.
  if (dragId.value === null || dragId.value === hoveredId) return
  if (dragMoment.value === null || momentOf(hoveredId) !== dragMoment.value) return
  const without = itemsFor(dragMoment.value)
    .map((r) => r.id)
    .filter((id) => id !== dragId.value)
  const idx = without.findIndex((id) => id === hoveredId)
  if (idx === -1) return
  if (target) {
    const rect = target.getBoundingClientRect()
    dropIndex.value = clientY > rect.top + rect.height / 2 ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onDragOver(e: DragEvent, hoveredId: number, moment: ShoppingReminderMoment) {
  if (moment !== dragMoment.value) return
  computeDropIndex(hoveredId, e.clientY, e.currentTarget as HTMLElement | null)
}

function commitReorder() {
  const id = dragId.value
  const moment = dragMoment.value
  const idx = dropIndex.value
  onDragEnd()
  if (id === null || moment === null || idx === null) return
  const without = itemsFor(moment)
    .map((r) => r.id)
    .filter((x) => x !== id)
  const clamped = Math.min(idx, without.length)
  without.splice(clamped, 0, id)
  void withBusy(() => reminders.reorder(fullOrderWith(moment, without)), strings.saveFailed)
}

useTouchReorder(rootRef, {
  onDragStart: (id) => {
    dragId.value = id
    dragMoment.value = momentOf(id)
    dropIndex.value = null
  },
  onReorderOver(hoveredId, _clientX, clientY) {
    const el = rootRef.value?.querySelector<HTMLElement>(`[data-drag-id="${hoveredId}"]`) ?? null
    computeDropIndex(hoveredId, clientY, el)
  },
  onDrop: commitReorder,
  onCancel: onDragEnd,
})

const strings = {
  // TRANSLATORS: Dialog title for managing user-defined shopping reminders.
  title: t('pantry', 'Shopping reminders'),
  intro: t(
    'pantry',
    'Reminders pop up while you shop — at the start, when changing store, or at the end.',
  ),
  groupEmpty: t('pantry', 'No reminders here yet.'),
  add: t('pantry', 'Add'),
  newPlaceholder: t('pantry', 'e.g. Bring reusable bags'),
  newTextLabel: t('pantry', 'New reminder text'),
  textLabel: t('pantry', 'Reminder text'),
  momentLabel: t('pantry', 'When to show'),
  // TRANSLATORS: Toggle that turns a reminder on or off without deleting it.
  enabledLabel: t('pantry', 'Enabled'),
  deleteLabel: t('pantry', 'Delete'),
  dragHandle: t('pantry', 'Drag to reorder'),
  addFailed: t('pantry', 'Could not add the reminder.'),
  saveFailed: t('pantry', 'Could not save the reminder.'),
  deleteFailed: t('pantry', 'Could not delete the reminder.'),
}
</script>

<style scoped lang="scss">
.pantry-center {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.reminders {
  &__intro {
    color: var(--color-text-maxcontrast);
    margin: 0 0 1rem;
  }

  &__group {
    margin-bottom: 1.25rem;
  }

  &__group-head {
    margin: 0 0 0.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-maxcontrast);
  }

  &__group-empty {
    margin: 0;
    padding: 0.25rem 0 0.5rem;
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
  }

  &__list {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  &__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0;

    &--dragging {
      opacity: 0.4;
    }
  }

  &__handle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    cursor: grab;
    color: var(--color-text-maxcontrast);
    touch-action: none;
  }

  &__switch {
    flex-shrink: 0;
  }

  &__text {
    flex: 1;
    min-width: 0;
  }

  &__moment {
    flex: 0 0 12rem;
    min-width: 10rem;
  }

  &__placeholder {
    min-height: 42px;
    margin: 4px 0;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
  }

  &__add {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 0.5rem;
    margin-bottom: 0.5rem;

    .reminders__text {
      flex: 1;
      min-width: 12rem;
    }
  }
}
</style>
