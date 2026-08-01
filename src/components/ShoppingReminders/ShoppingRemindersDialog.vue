<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="28" />
    </div>
    <template v-else>
      <p class="reminders__intro">{{ strings.intro }}</p>

      <p v-if="items.length === 0" class="reminders__hint">{{ strings.emptyHint }}</p>

      <ul v-else class="reminders__list">
        <li v-for="(reminder, index) in items" :key="reminder.id" class="reminders__item">
          <div class="reminders__reorder">
            <NcButton
              variant="tertiary"
              :aria-label="strings.moveUp"
              :disabled="index === 0 || busy"
              @click="move(index, -1)"
            >
              <template #icon><MenuUpIcon :size="20" /></template>
            </NcButton>
            <NcButton
              variant="tertiary"
              :aria-label="strings.moveDown"
              :disabled="index === items.length - 1 || busy"
              @click="move(index, 1)"
            >
              <template #icon><MenuDownIcon :size="20" /></template>
            </NcButton>
          </div>

          <div class="reminders__body">
            <input
              :value="reminder.text"
              type="text"
              class="reminders__text"
              :aria-label="strings.textLabel"
              @change="saveText(reminder, $event)"
            />
            <div class="reminders__meta">
              <NcSelect
                class="reminders__moment"
                :model-value="momentOption(reminder.showOn)"
                :options="momentOptions"
                :clearable="false"
                :searchable="false"
                label="label"
                :aria-label="strings.momentLabel"
                @update:model-value="saveMoment(reminder, $event)"
              />
              <NcCheckboxRadioSwitch
                type="switch"
                :model-value="reminder.enabled"
                :aria-label="strings.enabledLabel"
                @update:model-value="saveEnabled(reminder, $event)"
              >
                {{ strings.enabledLabel }}
              </NcCheckboxRadioSwitch>
            </div>
          </div>

          <NcButton
            variant="tertiary"
            :aria-label="strings.deleteLabel"
            :disabled="busy"
            @click="remove(reminder)"
          >
            <template #icon><DeleteIcon :size="18" /></template>
          </NcButton>
        </li>
      </ul>

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
import MenuUpIcon from '@icons/MenuUp.vue'
import MenuDownIcon from '@icons/MenuDown.vue'
import { useReminders } from '@/composables/useReminders'
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
const momentOptions: MomentOption[] = [
  // TRANSLATORS: Reminder timing — shown on the start/scope screen.
  { id: 'on_start', label: t('pantry', 'At trip start') },
  // TRANSLATORS: Reminder timing — shown when moving to the next store.
  { id: 'on_store_advance', label: t('pantry', 'When changing store') },
  // TRANSLATORS: Reminder timing — shown on the review/finish screen.
  { id: 'on_close', label: t('pantry', 'At trip end') },
]
function momentOption(moment: ShoppingReminderMoment): MomentOption | null {
  return momentOptions.find((o) => o.id === moment) ?? null
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

async function saveMoment(reminder: ShoppingReminder, option: MomentOption | null) {
  if (!option || option.id === reminder.showOn) return
  await withBusy(() => reminders.update(reminder.id, { showOn: option.id }), strings.saveFailed)
}

async function saveEnabled(reminder: ShoppingReminder, enabled: boolean) {
  await withBusy(() => reminders.update(reminder.id, { enabled }), strings.saveFailed)
}

async function remove(reminder: ShoppingReminder) {
  await withBusy(() => reminders.remove(reminder.id), strings.deleteFailed)
}

// Reorder by swapping with the neighbor, then persisting the full id order.
async function move(index: number, delta: number) {
  const target = index + delta
  if (target < 0 || target >= items.value.length) return
  const order = items.value.map((r) => r.id)
  ;[order[index], order[target]] = [order[target], order[index]]
  await withBusy(() => reminders.reorder(order), strings.saveFailed)
}

const strings = {
  // TRANSLATORS: Dialog title for managing user-defined shopping reminders.
  title: t('pantry', 'Shopping reminders'),
  intro: t(
    'pantry',
    'Reminders pop up while you shop — at the start, when changing store, or at the end.',
  ),
  emptyHint: t(
    'pantry',
    'No reminders yet. Add prompts like "Bring bags" or "Check the freezer aisle".',
  ),
  add: t('pantry', 'Add'),
  newPlaceholder: t('pantry', 'e.g. Bring reusable bags'),
  newTextLabel: t('pantry', 'New reminder text'),
  textLabel: t('pantry', 'Reminder text'),
  momentLabel: t('pantry', 'When to show'),
  // TRANSLATORS: Toggle that turns a reminder on or off without deleting it.
  enabledLabel: t('pantry', 'Enabled'),
  deleteLabel: t('pantry', 'Delete'),
  moveUp: t('pantry', 'Move up'),
  moveDown: t('pantry', 'Move down'),
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

  &__hint {
    color: var(--color-text-maxcontrast);
    margin: 0 0 1rem;
  }

  &__list {
    list-style: none;
    padding: 0;
    margin: 0 0 1rem;
  }

  &__item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--color-border);
  }

  &__reorder {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
  }

  &__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }

  &__text {
    width: 100%;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  &__moment {
    flex: 0 0 14rem;
    min-width: 12rem;
  }

  &__add {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 0.5rem;

    .reminders__text {
      flex: 1;
      min-width: 12rem;
      width: auto;
    }
  }
}
</style>
