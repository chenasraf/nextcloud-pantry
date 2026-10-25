<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form v-if="list" class="inventory-settings" @submit.prevent="save">
      <FieldCard :label="strings.nameLabel">
        <NcTextField
          v-model="name"
          :label="strings.nameLabel"
          label-outside
          :maxlength="255"
          autocomplete="off"
          data-testid="inventory-settings-name"
        />
        <p class="inventory-settings__hint">{{ strings.nameHint }}</p>
      </FieldCard>

      <FieldCard :label="strings.restockLabel">
        <NcSelect
          v-model="restockSelection"
          :options="restockOptions"
          :clearable="false"
          :input-label="''"
          label="label"
          :aria-label-combobox="strings.restockLabel"
          :calculate-position="ncSelectCalculatePosition"
          data-testid="inventory-settings-restock"
        />
        <p class="inventory-settings__hint">
          {{ restockSelection.listId !== null ? strings.restockSetHint : strings.restockUnsetHint }}
        </p>
      </FieldCard>

      <FieldCard :label="strings.expiryLabel">
        <div class="inventory-settings__sentence">
          <span>{{ warnBefore }}</span>
          <div class="inventory-settings__days">
            <NcTextField
              :model-value="leadDays"
              :label="strings.leadDaysLabel"
              label-outside
              type="number"
              inputmode="numeric"
              min="0"
              :max="MAX_LEAD_DAYS"
              autocomplete="off"
              data-testid="inventory-settings-lead-days"
              @update:model-value="leadDays = String($event)"
            />
          </div>
          <span>{{ warnAfter }}</span>
        </div>
        <p class="inventory-settings__hint">{{ strings.expiryHint }}</p>
      </FieldCard>

      <FieldCard :label="strings.notificationsLabel">
        <NcCheckboxRadioSwitch
          v-model="stockSummary"
          type="switch"
          data-testid="inventory-settings-stock-summary"
        >
          {{ strings.stockSummaryLabel }}
        </NcCheckboxRadioSwitch>
        <p class="inventory-settings__hint">{{ strings.stockSummaryHint }}</p>
      </FieldCard>
    </form>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton
        variant="primary"
        :disabled="!canSave || saving"
        data-testid="inventory-settings-save"
        @click="save"
      >
        {{ strings.save }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FieldCard from '@/components/FieldCard'
import { listApi } from '@/api/lists'
import type { Checklist } from '@/api/types'
import { DEFAULT_EXPIRY_LEAD_DAYS } from '@/utils/stock'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'

interface RestockOption {
  listId: number | null
  label: string
}

const MAX_LEAD_DAYS = 365

const strings = {
  // TRANSLATORS: Title of the dialog with an inventory's settings.
  title: t('pantry', 'Inventory settings'),
  // TRANSLATORS: Noun, the inventory's name.
  nameLabel: t('pantry', 'Name'),
  nameHint: t('pantry', 'Icon, colour and sharing work as they do on checklists.'),
  // TRANSLATORS: Caption of the inventory setting naming the checklist low stock is added to.
  restockLabel: t('pantry', 'Restock checklist'),
  // TRANSLATORS: Option in the restock checklist picker for having no restock checklist.
  restockNone: t('pantry', 'None'),
  restockSetHint: t('pantry', 'Items that run low or start expiring are added to this checklist.'),
  restockUnsetHint: t('pantry', 'Without one, low and expiring items are only marked here.'),
  // TRANSLATORS: Caption of the inventory setting for how early items count as expiring.
  expiryLabel: t('pantry', 'Expiry warning'),
  // TRANSLATORS: Accessible name of the number of days in "Warn [2] days before expiry".
  leadDaysLabel: t('pantry', 'Days before expiry'),
  expiryHint: t('pantry', 'Set 0 to warn on the expiry date itself.'),
  // TRANSLATORS: Caption of the inventory's notification settings.
  notificationsLabel: t('pantry', 'Notifications'),
  stockSummaryLabel: t('pantry', 'Daily stock summary'),
  stockSummaryHint: t(
    'pantry',
    'Once a day, tell members what newly ran low or started expiring here. Each member can also turn it off in their personal settings.',
  ),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  saveFailed: t('pantry', 'Could not save the inventory settings'),
}

const props = defineProps<{
  open: boolean
  list: Checklist | null
  /** The house's active checklists, any of which can be the restock checklist. */
  checklists: Checklist[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  saved: [list: Checklist]
}>()

const name = ref('')
const leadDays = ref('')
const stockSummary = ref(true)
const saving = ref(false)

const noneOption = computed<RestockOption>(() => ({ listId: null, label: strings.restockNone }))
const restockOptions = computed<RestockOption[]>(() => [
  noneOption.value,
  ...props.checklists.map((c) => ({ listId: c.id, label: c.name })),
])
const restockSelection = ref<RestockOption>(noneOption.value)

function restockOptionFor(listId: number | null | undefined): RestockOption {
  return restockOptions.value.find((o) => o.listId === (listId ?? null)) ?? noneOption.value
}

watch(
  () => props.open,
  (open) => {
    if (!open || !props.list) return
    name.value = props.list.name
    leadDays.value = String(props.list.expiryLeadDays ?? DEFAULT_EXPIRY_LEAD_DAYS)
    restockSelection.value = restockOptionFor(props.list.restockChecklistId)
    stockSummary.value = props.list.stockSummary ?? true
  },
  { immediate: true },
)

const parsedLeadDays = computed(() => {
  const days = Number.parseInt(leadDays.value, 10)
  return Number.isFinite(days) && days >= 0 && days <= MAX_LEAD_DAYS ? days : null
})

const canSave = computed(
  () => !!props.list?.canEdit && name.value.trim() !== '' && parsedLeadDays.value !== null,
)

async function save() {
  const list = props.list
  if (!list || !canSave.value) return
  const patch: {
    name?: string
    expiryLeadDays?: number
    restockChecklistId?: number
    stockSummary?: boolean
  } = {}
  if (name.value.trim() !== list.name) patch.name = name.value.trim()
  const restockId = restockSelection.value.listId
  if (restockId !== (list.restockChecklistId ?? null)) patch.restockChecklistId = restockId ?? 0
  if (parsedLeadDays.value !== (list.expiryLeadDays ?? DEFAULT_EXPIRY_LEAD_DAYS)) {
    patch.expiryLeadDays = parsedLeadDays.value!
  }
  if (stockSummary.value !== (list.stockSummary ?? true)) patch.stockSummary = stockSummary.value
  if (Object.keys(patch).length === 0) {
    emit('update:open', false)
    return
  }
  saving.value = true
  try {
    const updated = await listApi('inventory').updateList(list.houseId, list.id, patch)
    emit('saved', updated)
    emit('update:open', false)
  } catch (e) {
    showError(strings.saveFailed)
    console.error(e)
  } finally {
    saving.value = false
  }
}

// TRANSLATORS: Inventory setting, read as a sentence with a number input in place of {days}, e.g. "Warn [2] days before expiry".
const warnSentence = t('pantry', 'Warn {days} days before expiry')
const [warnBefore, warnAfter = ''] = warnSentence.split('{days}').map((part) => part.trim())
</script>

<style scoped lang="scss">
.inventory-settings {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  &__sentence {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }

  &__days {
    width: 6rem;
    flex: 0 0 auto;
  }

  &__hint {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
