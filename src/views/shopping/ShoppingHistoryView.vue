<template>
  <div class="shop-history">
    <div class="shop-history__inner">
      <header class="shop-history__head">
        <NcButton variant="tertiary" :aria-label="strings.back" @click="goBack">
          <template #icon><ArrowLeftIcon :size="20" /></template>
        </NcButton>
        <HistoryIcon :size="26" />
        <h2>{{ strings.title }}</h2>
      </header>

      <div class="shop-history__scope">
        <NcCheckboxRadioSwitch
          v-model="scope"
          value="mine"
          name="shop-history-scope"
          type="radio"
          button-variant
          button-variant-grouped="horizontal"
        >
          {{ strings.mine }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          v-model="scope"
          value="house"
          name="shop-history-scope"
          type="radio"
          button-variant
          button-variant-grouped="horizontal"
        >
          {{ strings.house }}
        </NcCheckboxRadioSwitch>
      </div>

      <div v-if="loading" class="shop-history__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent v-else-if="rows.length === 0" :name="strings.empty" :description="emptyBody">
        <template #icon><CartOffIcon /></template>
      </NcEmptyContent>

      <template v-else>
        <ul class="shop-history__list">
          <li v-for="row in rows" :key="row.id">
            <button type="button" class="shop-history__row" @click="openTrip(row)">
              <NcAvatar
                class="shop-history__avatar"
                :user="row.userId"
                :size="32"
                :show-user-status="false"
              />
              <span class="shop-history__main">
                <span class="shop-history__stores">{{ storesLabel(row) }}</span>
                <span class="shop-history__meta">
                  <span class="shop-history__chip">
                    <ClockOutlineIcon :size="14" />
                    {{ timeLabel(row) }}
                  </span>
                  <span class="shop-history__chip">
                    <FormatListChecksIcon :size="14" />
                    {{ itemsLabel(row) }}
                  </span>
                </span>
              </span>
              <span v-if="row.grandTotal.length > 0" class="shop-history__total">
                {{ totalLabel(row) }}
              </span>
            </button>
          </li>
        </ul>

        <div v-if="canLoadMore" class="shop-history__more">
          <NcButton :disabled="loadingMore" @click="loadMore">{{ strings.loadMore }}</NcButton>
        </div>
      </template>
    </div>

    <ShoppingReviewDialog
      v-if="detailId !== null"
      :open="detailId !== null"
      :house-id="houseIdNum"
      :session-id="detailId"
      mode="history"
      @update:open="(v) => !v && (detailId = null)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t, n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import HistoryIcon from '@icons/History.vue'
import CartOffIcon from '@icons/CartOff.vue'
import ClockOutlineIcon from '@icons/ClockOutline.vue'
import FormatListChecksIcon from '@icons/FormatListChecks.vue'
import { ShoppingReviewDialog } from '@/components/ShoppingReview'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { getHistory } from '@/api/shopping'
import { formatMoney } from '@/utils/price'
import { formatDateTime, formatDuration } from '@/utils/datetime'
import type { ShoppingHistoryRow } from '@/api/types'

const router = useRouter()
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)

const PAGE_SIZE = 30

const scope = ref<'mine' | 'house'>('mine')
const rows = ref<ShoppingHistoryRow[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const canLoadMore = ref(false)
const detailId = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    const fetched = await getHistory(houseIdNum.value, scope.value, PAGE_SIZE, 0)
    rows.value = fetched
    canLoadMore.value = fetched.length === PAGE_SIZE
  } catch (e) {
    showError((e as Error).message || strings.loadFailed)
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  loadingMore.value = true
  try {
    const fetched = await getHistory(houseIdNum.value, scope.value, PAGE_SIZE, rows.value.length)
    rows.value = [...rows.value, ...fetched]
    canLoadMore.value = fetched.length === PAGE_SIZE
  } catch (e) {
    showError((e as Error).message || strings.loadFailed)
  } finally {
    loadingMore.value = false
  }
}

// Reload from the top whenever the scope toggle changes.
watch(scope, load)
onMounted(load)

function openTrip(row: ShoppingHistoryRow) {
  detailId.value = row.id
}

function goBack() {
  router.push({ name: 'shopping-start', params: { houseId: String(houseIdNum.value) } })
}

function storesLabel(row: ShoppingHistoryRow): string {
  return row.stores.length > 0 ? row.stores.join(' → ') : strings.noStore
}

function timeLabel(row: ShoppingHistoryRow): string {
  const when = formatDateTime(row.createdAt)
  if (row.closedAt == null) return when
  return `${when} · ${formatDuration(row.closedAt - row.createdAt)}`
}

function itemsLabel(row: ShoppingHistoryRow): string {
  return n('pantry', '%n item', '%n items', row.itemCount)
}

function totalLabel(row: ShoppingHistoryRow): string {
  if (row.grandTotal.length === 0) return '—'
  return row.grandTotal.map((e) => formatMoney(e.amount, e.currency)).join(' · ')
}

const emptyBody = computed(() =>
  scope.value === 'house' ? strings.emptyHouseBody : strings.emptyMineBody,
)

const strings = {
  title: t('pantry', 'Shopping history'),
  back: t('pantry', 'Back'),
  // TRANSLATORS: History filter showing only the current user's own trips.
  mine: t('pantry', 'Mine'),
  // TRANSLATORS: History filter showing everyone in the house's trips.
  house: t('pantry', 'House'),
  noStore: t('pantry', 'No store'),
  empty: t('pantry', 'No shopping history yet'),
  emptyMineBody: t('pantry', 'Trips you finish will show up here.'),
  emptyHouseBody: t('pantry', 'Finished trips in this house will show up here.'),
  loadMore: t('pantry', 'Load more'),
  loadFailed: t('pantry', 'Could not load shopping history.'),
}
</script>

<style scoped lang="scss">
.shop-history {
  height: 100%;
  overflow-y: auto;

  &__inner {
    max-width: 720px;
    margin: 0 auto;
    padding: 1rem 1rem 3rem;
  }

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;

    h2 {
      margin: 0;
    }
  }

  &__scope {
    display: flex;
    margin-bottom: 1rem;
  }

  &__center {
    display: flex;
    justify-content: center;
    padding: 3rem;
  }

  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  // Mirrors the checklist item row: bordered, rounded card on the main
  // background, with meta rendered as pill chips.
  &__row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    text-align: start;
    cursor: pointer;

    &:hover,
    &:focus-visible {
      background: var(--color-background-hover);

      // Keep the chips distinct from the now-hovered row surface.
      .shop-history__chip {
        background: var(--color-background-dark);
      }
    }
  }

  &__avatar {
    flex-shrink: 0;
  }

  &__main {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
  }

  &__stores {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }

  // The trip total as a filled counter chip.
  &__total {
    margin-inline-start: auto;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }

  &__more {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
  }
}
</style>
