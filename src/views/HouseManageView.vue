<template>
  <div class="pantry-manage">
    <PageToolbar :title="strings.title">
      <template #actions>
        <span v-if="saved" class="pantry-manage__saved">{{ strings.saved }}</span>
        <NcButton
          variant="primary"
          :disabled="!configDirty || !configValid || saving"
          @click="saveAll"
        >
          {{ saving ? strings.saving : strings.save }}
        </NcButton>
      </template>
    </PageToolbar>

    <div class="pantry-manage__body">
      <section class="pantry-card">
        <h3 class="pantry-card__title">{{ strings.generalSection }}</h3>
        <div class="pantry-form">
          <NcTextField
            v-model="name"
            :label="strings.nameLabel"
            :placeholder="strings.namePlaceholder"
            autocomplete="off"
          />
          <NcTextField
            v-model="description"
            :label="strings.descriptionLabel"
            :placeholder="strings.descriptionPlaceholder"
            autocomplete="off"
          />
        </div>
      </section>

      <section class="pantry-card">
        <h3 class="pantry-card__title">{{ strings.automationSection }}</h3>
        <div class="pantry-form pantry-form--wide">
          <div class="pantry-field">
            <NcDateTimePickerNative
              v-model="recurrenceTimeValue"
              type="time"
              :label="strings.recurrenceTimeLabel"
            />
            <p class="pantry-hint pantry-hint--small">{{ recurrenceTimeSummary }}</p>
          </div>
          <div class="pantry-field">
            <NcDateTimePickerNative
              v-model="fieldReminderTimeValue"
              type="time"
              :label="strings.fieldReminderTimeLabel"
            />
            <p class="pantry-hint pantry-hint--small">{{ fieldReminderTimeSummary }}</p>
          </div>
        </div>
      </section>

      <section class="pantry-card">
        <h3 class="pantry-card__title">{{ strings.trashSection }}</h3>
        <p class="pantry-hint">{{ strings.trashRetentionHint }}</p>
        <div class="pantry-form">
          <NcTextField
            v-model="trashRetentionInput"
            type="number"
            :label="strings.trashRetentionLabel"
            :min="0"
            :max="3650"
            autocomplete="off"
          />
          <p class="pantry-hint pantry-hint--small">{{ trashRetentionSummary }}</p>
        </div>
      </section>

      <section v-if="canEditReminders" class="pantry-card">
        <h3 class="pantry-card__title">{{ strings.shoppingSection }}</h3>
        <p class="pantry-hint">{{ strings.remindersHint }}</p>
        <NcButton @click="remindersOpen = true">
          <template #icon><BellRingIcon :size="20" /></template>
          {{ strings.manageReminders }}
        </NcButton>
      </section>

      <section class="pantry-card">
        <h3 class="pantry-card__title">{{ strings.membersRolesSection }}</h3>
        <p class="pantry-hint">{{ strings.membersRolesHint }}</p>
        <div class="pantry-button-row">
          <NcButton @click="showMembers = true">
            <template #icon>
              <AccountMultipleIcon :size="20" />
            </template>
            {{ strings.manageMembers }}
          </NcButton>
          <NcButton @click="showRoles = true">
            <template #icon>
              <ShieldAccountIcon :size="20" />
            </template>
            {{ strings.manageRoles }}
          </NcButton>
        </div>
      </section>

      <section v-if="isOwner" class="pantry-card pantry-card--danger">
        <h3 class="pantry-card__title">{{ strings.dangerSection }}</h3>
        <p class="pantry-hint">{{ strings.dangerBody }}</p>
        <NcButton variant="error" @click="confirmingDelete = true">
          {{ strings.deleteButton }}
        </NcButton>
      </section>
    </div>

    <HouseMembersDialog
      v-if="showMembers && houseIdNum !== null"
      :open="showMembers"
      :house-id="houseIdNum"
      @update:open="showMembers = $event"
    />

    <HouseRolesDialog
      v-if="showRoles && houseIdNum !== null"
      :open="showRoles"
      :house-id="houseIdNum"
      @update:open="showRoles = $event"
    />

    <NcDialog
      v-if="confirmingDelete"
      :name="strings.deleteDialogTitle"
      :open="confirmingDelete"
      close-on-click-outside
      @update:open="confirmingDelete = $event"
    >
      <p>{{ strings.deleteConfirmBody }}</p>
      <template #actions>
        <NcButton @click="confirmingDelete = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="deleteHouse">{{ strings.deleteButton }}</NcButton>
      </template>
    </NcDialog>

    <ShoppingRemindersDialog
      v-if="remindersOpen && houseIdNum !== null"
      :open="remindersOpen"
      :house-id="houseIdNum"
      @update:open="remindersOpen = $event"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import BellRingIcon from '@icons/BellRing.vue'
import AccountMultipleIcon from '@icons/AccountMultiple.vue'
import ShieldAccountIcon from '@icons/ShieldAccount.vue'
import PageToolbar from '@/components/PageToolbar'
import { ShoppingRemindersDialog } from '@/components/ShoppingReminders'
import HouseMembersDialog from '@/components/HouseMembersDialog'
import HouseRolesDialog from '@/components/HouseRolesDialog'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useHouses } from '@/composables/useHouses'

const router = useRouter()
const { house, isOwner, canAdmin, refresh } = useCurrentHouse()
const { update, remove } = useHouses()

const houseIdNum = computed(() => house.value?.id ?? null)

// Redirect non-admins away — this surface is admin-only. HouseLayout has already
// resolved the house by the time this view mounts, so canAdmin is reliable.
watch(
  [house, canAdmin],
  () => {
    if (house.value && !canAdmin.value && houseIdNum.value !== null) {
      void router.replace({ name: 'lists', params: { houseId: String(houseIdNum.value) } })
    }
  },
  { immediate: true },
)

// Shopping reminders are house shopping-config; gate the manager on list-edit.
const remindersOpen = ref(false)
const canEditReminders = computed(() => house.value?.permissions?.canEditLists ?? false)

// -------- General / Automation / Trash (batched under one page Save) --------
const name = ref('')
const description = ref('')
const trashRetentionInput = ref<string>('30')
// Backing value for the native time picker. It is a Date whose wall-clock time
// (local getHours/getMinutes) is the configured reopen time; the date part is
// irrelevant. null means the field was cleared.
const recurrenceTimeValue = ref<Date | null>(null)
const fieldReminderTimeValue = ref<Date | null>(null)
const saving = ref(false)
const saved = ref(false)

function minutesToDate(minutes: number): Date {
  const clamped = Math.min(1439, Math.max(0, Math.round(minutes)))
  const d = new Date()
  d.setHours(Math.floor(clamped / 60), clamped % 60, 0, 0)
  return d
}

function dateToMinutes(date: Date): number {
  return date.getHours() * 60 + date.getMinutes()
}

function minutesToHHMM(minutes: number): string {
  const clamped = Math.min(1439, Math.max(0, Math.round(minutes)))
  const h = Math.floor(clamped / 60)
  const m = clamped % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function syncFromHouse() {
  if (house.value) {
    name.value = house.value.name
    description.value = house.value.description ?? ''
    trashRetentionInput.value = String(house.value.trashRetentionDays ?? 30)
    recurrenceTimeValue.value = minutesToDate(house.value.recurrenceTime ?? 480)
    fieldReminderTimeValue.value = minutesToDate(house.value.fieldReminderTime ?? 480)
  }
}

watch(house, syncFromHouse, { immediate: true })

const trashRetentionParsed = computed(() => {
  const raw = trashRetentionInput.value.trim()
  if (raw === '') return null
  if (!/^\d+$/.test(raw)) return null
  const n = Number(raw)
  if (!Number.isFinite(n) || n < 0 || n > 3650) return null
  return n
})

const isTrashRetentionValid = computed(() => trashRetentionParsed.value !== null)

const trashRetentionSummary = computed(() => {
  const n = trashRetentionParsed.value
  if (n === null) return strings.trashRetentionInvalid
  if (n === 0) return strings.trashRetentionDisabled
  return t('pantry', 'Items in the trash are permanently deleted after {n} day(s).', { n })
})

// Minutes since midnight for the picked time; null when the field is cleared.
const recurrenceTimeParsed = computed<number | null>(() =>
  recurrenceTimeValue.value ? dateToMinutes(recurrenceTimeValue.value) : null,
)
const isRecurrenceTimeValid = computed(() => recurrenceTimeParsed.value !== null)
const recurrenceTimeSummary = computed(() => {
  if (recurrenceTimeParsed.value === null) return strings.recurrenceTimeInvalid
  // TRANSLATORS: The placeholder is a time of day, e.g. "08:00".
  return t(
    'pantry',
    'Recurring items become due again around {time}, in the server timezone. A background job checks every 15 minutes.',
    { time: minutesToHHMM(recurrenceTimeParsed.value) },
  )
})

const fieldReminderTimeParsed = computed<number | null>(() =>
  fieldReminderTimeValue.value ? dateToMinutes(fieldReminderTimeValue.value) : null,
)
const isFieldReminderTimeValid = computed(() => fieldReminderTimeParsed.value !== null)
const fieldReminderTimeSummary = computed(() => {
  if (fieldReminderTimeParsed.value === null) return strings.fieldReminderTimeInvalid
  // TRANSLATORS: The placeholder is a time of day, e.g. "08:00".
  return t(
    'pantry',
    'Date custom-field reminders are sent around {time}, in the server timezone. A background job checks every 15 minutes.',
    { time: minutesToHHMM(fieldReminderTimeParsed.value) },
  )
})

const configValid = computed(
  () =>
    name.value.trim() !== '' &&
    isTrashRetentionValid.value &&
    isRecurrenceTimeValid.value &&
    isFieldReminderTimeValid.value,
)

const configDirty = computed(() => {
  const h = house.value
  if (!h) return false
  return (
    name.value.trim() !== h.name ||
    description.value.trim() !== (h.description ?? '') ||
    (trashRetentionParsed.value !== null && trashRetentionParsed.value !== h.trashRetentionDays) ||
    (recurrenceTimeParsed.value !== null && recurrenceTimeParsed.value !== h.recurrenceTime) ||
    (fieldReminderTimeParsed.value !== null &&
      fieldReminderTimeParsed.value !== h.fieldReminderTime)
  )
})

async function saveAll() {
  const id = houseIdNum.value
  if (id === null || !configValid.value || !configDirty.value || saving.value) return
  saving.value = true
  saved.value = false
  try {
    await update(id, {
      name: name.value.trim(),
      description: description.value.trim(),
      trashRetentionDays: trashRetentionParsed.value!,
      recurrenceTime: recurrenceTimeParsed.value!,
      fieldReminderTime: fieldReminderTimeParsed.value!,
    })
    await refresh()
    flashSaved()
  } finally {
    saving.value = false
  }
}

let savedTimer: ReturnType<typeof setTimeout> | null = null
function flashSaved() {
  saved.value = true
  if (savedTimer) clearTimeout(savedTimer)
  savedTimer = setTimeout(() => {
    saved.value = false
  }, 2000)
}

// -------- Unsaved-changes guard (config fields only) --------
onBeforeRouteLeave(() => {
  if (configDirty.value && !window.confirm(strings.unsavedConfirm)) {
    return false
  }
})

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (configDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload))

// -------- Members & roles (managed in their own dialogs) --------
const showMembers = ref(false)
const showRoles = ref(false)

// -------- Danger zone --------
const confirmingDelete = ref(false)

async function deleteHouse() {
  const id = houseIdNum.value
  if (id === null) return
  await remove(id)
  confirmingDelete.value = false
  await router.push({ name: 'home' })
}

const strings = {
  title: t('pantry', 'Manage house'),
  save: t('pantry', 'Save changes'),
  saving: t('pantry', 'Saving …'),
  saved: t('pantry', 'Saved.'),
  unsavedConfirm: t('pantry', 'You have unsaved changes. Leave without saving?'),
  generalSection: t('pantry', 'General'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'House name'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  automationSection: t('pantry', 'Automation'),
  shoppingSection: t('pantry', 'Shopping'),
  remindersHint: t(
    'pantry',
    'Reminders pop up while you shop — at the start, when changing store, or at the end.',
  ),
  manageReminders: t('pantry', 'Manage reminders'),
  trashSection: t('pantry', 'Trash'),
  trashRetentionLabel: t('pantry', 'Days to keep items in the trash'),
  trashRetentionHint: t(
    'pantry',
    'A daily background job permanently deletes checklists, items, notes and photos whose deleted-at timestamp is older than this many days. Set to 0 to never auto-delete — items stay in the trash until removed manually.',
  ),
  trashRetentionDisabled: t(
    'pantry',
    'Auto-delete disabled. Items stay in the trash until removed manually.',
  ),
  trashRetentionInvalid: t('pantry', 'Enter a whole number between 0 and 3650.'),
  recurrenceTimeLabel: t('pantry', 'Recurring items reopen time:'),
  recurrenceTimeInvalid: t('pantry', 'Enter a valid time.'),
  fieldReminderTimeLabel: t('pantry', 'Custom field reminder time:'),
  fieldReminderTimeInvalid: t('pantry', 'Enter a valid time.'),
  membersRolesSection: t('pantry', 'Members & roles'),
  membersRolesHint: t(
    'pantry',
    'Add or remove members and assign their roles, and define what each role can do.',
  ),
  manageMembers: t('pantry', 'Manage members'),
  manageRoles: t('pantry', 'Manage roles'),
  dangerSection: t('pantry', 'Danger zone'),
  dangerBody: t(
    'pantry',
    'Deleting a house permanently removes all of its lists, items, and membership records. This cannot be undone.',
  ),
  deleteButton: t('pantry', 'Delete house'),
  deleteDialogTitle: t('pantry', 'Delete this house?'),
  deleteConfirmBody: t(
    'pantry',
    'All lists, items and member records for this house will be permanently deleted.',
  ),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-manage {
  position: relative;
  min-height: 100%;

  &__body {
    max-width: 900px;
    margin: 0 auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  &__saved {
    color: var(--color-success);
    font-size: 0.85rem;
    align-self: center;
  }
}

.pantry-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  padding: 1rem 1.25rem 1.25rem;

  &--danger {
    border-color: var(--color-error);
  }

  &__title {
    margin: 0 0 0.75rem;
    font-size: 1.05rem;
    font-weight: 600;
  }
}

.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  &--wide {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 1.5rem;
  }
}

.pantry-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.pantry-button-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.pantry-hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;

  &--small {
    font-size: 0.85em;
    margin: 0;
  }
}
</style>
