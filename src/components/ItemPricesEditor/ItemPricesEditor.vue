<template>
  <div class="item-prices">
    <div class="item-prices__entry">
      <span class="item-prices__sublabel">
        <EarthIcon class="item-prices__sublabel-icon" :size="16" />
        {{ strings.anyStore }}
      </span>
      <PriceInput
        v-model="storelessValue"
        :default-currency="defaultCurrency"
        @update:model-value="emitAll"
      />
    </div>

    <div v-for="(row, index) in rows" :key="row.key" class="item-prices__entry">
      <div class="item-prices__head">
        <NcSelect
          class="item-prices__store"
          :model-value="storeOption(row.storeId)"
          :options="storeOptionsFor(index)"
          :clearable="false"
          :searchable="true"
          label="label"
          :input-label="strings.store"
          :aria-label="strings.store"
          :placeholder="strings.pickStore"
          @update:model-value="(o) => onStoreSelected(index, o)"
        >
          <template #option="option">
            <div class="item-prices__store-option">
              <span
                v-if="option.store"
                class="item-prices__store-icon"
                :style="{ color: option.store.color }"
              >
                <component :is="iconFor(option.store.icon)" :size="18" />
              </span>
              <span>{{ option.label }}</span>
            </div>
          </template>
          <template #selected-option="option">
            <div class="item-prices__store-option">
              <span
                v-if="option.store"
                class="item-prices__store-icon"
                :style="{ color: option.store.color }"
              >
                <component :is="iconFor(option.store.icon)" :size="16" />
              </span>
              <span>{{ option.label }}</span>
            </div>
          </template>
        </NcSelect>
        <NcButton
          variant="tertiary"
          type="button"
          class="item-prices__remove"
          :aria-label="strings.remove"
          @click="removeRow(index)"
        >
          <template #icon>
            <DeleteIcon :size="18" />
          </template>
        </NcButton>
      </div>
      <PriceInput
        v-model="row.value"
        :clearable="false"
        :default-currency="defaultCurrency"
        @update:model-value="emitAll"
      />
    </div>

    <NcButton
      variant="tertiary"
      type="button"
      class="item-prices__add"
      :disabled="!availableStores.length"
      @click="addRow"
    >
      <template #icon>
        <PlusIcon :size="18" />
      </template>
      {{ strings.addPrice }}
    </NcButton>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from '@icons/Plus.vue'
import DeleteIcon from '@icons/Delete.vue'
import EarthIcon from '@icons/Earth.vue'
import PriceInput from '@/components/PriceInput'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { useStores } from '@/composables/useStores'
import { DEFAULT_CURRENCY } from '@/utils/currencies'
import type { PriceValue } from '@/utils/price'
import type { ItemPrice, Store } from '@/api/types'

const props = withDefaults(
  defineProps<{
    modelValue: ItemPrice[]
    houseId: number
    /** Currency preselected when a price has none yet (house's last-used). */
    defaultCurrency?: string
  }>(),
  { defaultCurrency: DEFAULT_CURRENCY },
)

const emit = defineEmits<{
  'update:modelValue': [value: ItemPrice[]]
}>()

const { items: stores, load } = useStores(props.houseId)

onMounted(() => void load())
watch(
  () => props.houseId,
  () => void useStores(props.houseId).load(),
)

interface Row {
  key: number
  storeId: number
  value: PriceValue
}

let rowKey = 0

function emptyPrice(): PriceValue {
  return { priceType: null, priceMin: null, priceMax: null, priceCurrency: props.defaultCurrency }
}

function toValue(p: ItemPrice): PriceValue {
  return {
    priceType: p.priceType,
    priceMin: p.priceMin,
    priceMax: p.priceMax,
    priceCurrency: p.priceCurrency ?? props.defaultCurrency,
  }
}

const storelessValue = ref<PriceValue>(emptyPrice())
const rows = ref<Row[]>([])

function seedFromModel(model: ItemPrice[]): void {
  const storeless = model.find((p) => p.storeId == null)
  storelessValue.value = storeless ? toValue(storeless) : emptyPrice()
  rows.value = model
    .filter((p): p is ItemPrice & { storeId: number } => p.storeId != null)
    .map((p) => ({ key: rowKey++, storeId: p.storeId, value: toValue(p) }))
}

// Ignore the parent echoing our own update; re-seed only when it swaps in a
// genuinely different value (e.g. the dialog reopens on another item).
let lastEmitted: ItemPrice[] | null = null

function compose(): ItemPrice[] {
  const out: ItemPrice[] = []
  const s = storelessValue.value
  if (s.priceType === 'set' || s.priceType === 'range') {
    out.push({
      storeId: null,
      priceType: s.priceType,
      priceMin: s.priceMin,
      priceMax: s.priceMax,
      priceCurrency: s.priceCurrency,
    })
  }
  for (const row of rows.value) {
    const v = row.value
    if (v.priceType === 'set' || v.priceType === 'range') {
      out.push({
        storeId: row.storeId,
        priceType: v.priceType,
        priceMin: v.priceMin,
        priceMax: v.priceMax,
        priceCurrency: v.priceCurrency,
      })
    }
  }
  return out
}

function emitAll(): void {
  const out = compose()
  lastEmitted = out
  emit('update:modelValue', out)
}

seedFromModel(props.modelValue)

watch(
  () => props.modelValue,
  (model) => {
    if (lastEmitted && JSON.stringify(model) === JSON.stringify(lastEmitted)) return
    seedFromModel(model)
  },
)

// Stores not already used by a row (the store-less "row" is separate).
const availableStores = computed<Store[]>(() => {
  const used = new Set(rows.value.map((r) => r.storeId))
  return stores.value.filter((s) => !used.has(s.id))
})

interface StoreOption {
  label: string
  id: number
  store: Store
}

function toOption(store: Store): StoreOption {
  return { label: store.name, id: store.id, store }
}

function storeOption(storeId: number): StoreOption | null {
  const store = stores.value.find((s) => s.id === storeId)
  return store ? toOption(store) : null
}

// A row may keep its own store plus any not used elsewhere.
function storeOptionsFor(index: number): StoreOption[] {
  const own = rows.value[index]?.storeId
  return stores.value
    .filter((s) => s.id === own || availableStores.value.some((a) => a.id === s.id))
    .map(toOption)
}

function onStoreSelected(index: number, option: StoreOption | null): void {
  if (!option) return
  rows.value[index].storeId = option.id
  emitAll()
}

function addRow(): void {
  const next = availableStores.value[0]
  if (!next) return
  rows.value.push({ key: rowKey++, storeId: next.id, value: emptyPrice() })
  emitAll()
}

function removeRow(index: number): void {
  rows.value.splice(index, 1)
  emitAll()
}

function iconFor(key: string) {
  return storeIconComponent(key)
}

const strings = {
  // TRANSLATORS: Label for the default price that applies when no specific store is chosen
  anyStore: t('pantry', 'Any store'),
  store: t('pantry', 'Store'),
  pickStore: t('pantry', 'Pick a store'),
  addPrice: t('pantry', 'Add price'),
  remove: t('pantry', 'Remove price'),
}
</script>

<style scoped lang="scss">
.item-prices {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  // One stacked block per price: its store header sits above the amount/currency.
  &__entry {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

    & + & {
      padding-top: 1rem;
      border-top: 1px solid var(--color-border);
    }
  }

  &__sublabel {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast);
  }

  &__sublabel-icon {
    flex-shrink: 0;
  }

  // Store select and its remove button share the header row.
  &__head {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
  }

  &__store {
    flex: 1 1 auto;
    min-width: 0;
  }

  &__store-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  &__store-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &__remove {
    flex: 0 0 auto;
  }

  &__add {
    align-self: flex-start;
  }
}
</style>
