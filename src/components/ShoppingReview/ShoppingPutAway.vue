<template>
  <section class="shop-putaway">
    <h4 class="shop-putaway__head">
      <CupboardIcon :size="18" class="shop-putaway__head-icon" />
      <span>{{ strings.title }}</span>
    </h4>
    <p class="shop-putaway__hint">{{ strings.hint }}</p>
    <ul class="shop-putaway__items">
      <li v-for="item in items" :key="item.id" class="shop-putaway__item" :data-item="item.id">
        <div class="shop-putaway__item-head">
          <span class="shop-putaway__item-name">{{ item.name }}</span>
          <span v-if="item.quantity" class="shop-putaway__bought">{{ item.quantity }}</span>
        </div>
        <div class="shop-putaway__storages">
          <div
            v-for="(storage, index) in storagesOf(item)"
            :key="storage.listId"
            class="shop-putaway__storage"
            :data-list="storage.listId"
          >
            <span class="shop-putaway__storage-name">{{ inventoryName(storage.listId) }}</span>
            <input
              type="number"
              min="0"
              step="any"
              class="shop-putaway__amount"
              :value="storage.amount ?? ''"
              :placeholder="strings.full"
              :aria-label="amountLabel(item, storage.listId)"
              @change="onAmount(item, index, $event)"
            />
            <NcButton
              variant="tertiary"
              :aria-label="strings.remove"
              :title="strings.remove"
              @click="removeStorage(item, index)"
            >
              <template #icon>
                <CloseIcon :size="18" />
              </template>
            </NcButton>
          </div>
          <span v-if="storagesOf(item).length === 0" class="shop-putaway__none">
            {{ strings.notPutAway }}
          </span>
          <NcSelect
            v-if="freeInventories(item).length > 0"
            class="shop-putaway__add"
            :model-value="null"
            :options="freeInventories(item)"
            :clearable="false"
            :placeholder="strings.addStorage"
            :aria-label-combobox="strings.addStorage"
            label="label"
            @update:model-value="(option) => option && addStorage(item, option.listId)"
          />
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import CupboardIcon from '@icons/Cupboard.vue'
import CloseIcon from '@icons/Close.vue'
import { useChecklists } from '@/composables/useChecklist'
import { amountLeft, type PutAwayDraft } from './putAway'
import type { ChecklistItem, PutAwayStorage } from '@/api/types'

const props = defineProps<{
  houseId: number
  /** The items ticked on the trip. */
  items: ChecklistItem[]
}>()

const draft = defineModel<PutAwayDraft>({ required: true })

const { lists: inventories, load: loadInventories } = useChecklists(props.houseId, 'inventory')
onMounted(() => void loadInventories())

interface InventoryOption {
  listId: number
  label: string
}

function storagesOf(item: ChecklistItem): PutAwayStorage[] {
  return draft.value[item.id] ?? []
}

function setStorages(item: ChecklistItem, storages: PutAwayStorage[]) {
  draft.value = { ...draft.value, [item.id]: storages }
}

function inventoryName(listId: number): string {
  return inventories.value.find((l) => l.id === listId)?.name ?? strings.unknownInventory
}

function freeInventories(item: ChecklistItem): InventoryOption[] {
  const used = new Set(storagesOf(item).map((s) => s.listId))
  return inventories.value
    .filter((l) => !used.has(l.id))
    .map((l) => ({ listId: l.id, label: l.name }))
}

/** A new storage takes what is left to put away, or the whole purchase again. */
function addStorage(item: ChecklistItem, listId: number) {
  const storages = storagesOf(item)
  const left = amountLeft(item, storages)
  setStorages(item, [...storages, { listId, amount: left > 0 ? left : amountLeft(item, []) }])
}

function removeStorage(item: ChecklistItem, index: number) {
  setStorages(
    item,
    storagesOf(item).filter((_, i) => i !== index),
  )
}

/** An emptied field fills the item to its target; a typed 0 drops the storage. */
function onAmount(item: ChecklistItem, index: number, event: Event) {
  const raw = (event.target as HTMLInputElement).value.trim().replace(',', '.')
  const amount = raw === '' ? null : Number(raw)
  if (amount !== null && (!Number.isFinite(amount) || amount < 0)) return
  if (amount === 0) {
    removeStorage(item, index)
    return
  }
  setStorages(
    item,
    storagesOf(item).map((s, i) => (i === index ? { ...s, amount } : s)),
  )
}

function amountLabel(item: ChecklistItem, listId: number): string {
  return t('pantry', 'Amount of {item} put away in {storage}', {
    item: item.name,
    storage: inventoryName(listId),
  })
}

const strings = {
  // TRANSLATORS: Heading of the trip review section where bought items are assigned to inventories (fridge, freezer …).
  title: t('pantry', 'Put away'),
  hint: t('pantry', 'Choose where what you bought goes. Each item starts where it usually goes.'),
  // TRANSLATORS: Placeholder of an amount left empty, which fills the inventory item to its target.
  full: t('pantry', 'Full'),
  remove: t('pantry', 'Remove from this storage'),
  addStorage: t('pantry', 'Add to a storage …'),
  notPutAway: t('pantry', 'Not put away'),
  unknownInventory: t('pantry', 'Unknown inventory'),
}
</script>

<style scoped lang="scss">
.shop-putaway {
  margin-top: 1rem;

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.25rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--color-border);
    font-weight: 600;
  }

  &__head-icon {
    display: inline-flex;
    color: var(--color-primary-element);
  }

  &__hint {
    margin: 0.25rem 0 0.5rem;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  &__item-head {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
  }

  &__item-name {
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__bought {
    color: var(--color-text-maxcontrast);
    font-variant-numeric: tabular-nums;
  }

  &__storages {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.25rem 0.75rem;
    margin-top: 0.25rem;
  }

  &__storage {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }

  &__storage-name {
    color: var(--color-text-maxcontrast);
  }

  &__amount {
    width: 5rem;
  }

  &__none {
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
  }

  &__add {
    flex: 0 0 13rem;
    min-width: 11rem;
  }
}
</style>
