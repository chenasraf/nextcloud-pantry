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
          <li v-for="row in rows" :key="row.id" class="shop-history__item">
            <button type="button" class="shop-history__row" @click="openTrip(row)">
              <span class="shop-history__avatars">
                <NcAvatar
                  v-for="uid in row.memberIds"
                  :key="uid"
                  class="shop-history__avatar"
                  :user="uid"
                  :size="32"
                  :show-user-status="false"
                  disable-menu
                />
              </span>
              <span class="shop-history__main">
                <span class="shop-history__stores">{{ storesLabel(row) }}</span>
                <span class="shop-history__meta">
                  <PantryChip class="shop-history__chip" size="sm" :interactive="false">
                    <template #icon>
                      <ClockOutlineIcon :size="14" />
                    </template>
                    {{ timeLabel(row) }}
                  </PantryChip>
                  <PantryChip
                    v-if="row.abortedAt != null"
                    class="shop-history__chip"
                    size="sm"
                    :interactive="false"
                  >
                    <template #icon>
                      <CloseCircleOutlineIcon :size="14" />
                    </template>
                    {{ strings.cancelled }}
                  </PantryChip>
                  <PantryChip v-else class="shop-history__chip" size="sm" :interactive="false">
                    <template #icon>
                      <FormatListChecksIcon :size="14" />
                    </template>
                    {{ itemsLabel(row) }}
                  </PantryChip>
                  <PantryChip
                    v-if="row.undoneAt != null"
                    class="shop-history__chip"
                    size="sm"
                    :interactive="false"
                  >
                    <template #icon>
                      <UndoIcon :size="14" />
                    </template>
                    {{ strings.undone }}
                  </PantryChip>
                </span>
              </span>
              <span v-if="row.grandTotal.length > 0" class="shop-history__total">
                {{ totalLabel(row) }}
              </span>
            </button>
            <span class="shop-history__actions">
              <NcButton
                v-if="canUndo(row)"
                variant="tertiary"
                :aria-label="strings.undoTrip"
                :title="strings.undoTrip"
                @click="undoingRow = row"
              >
                <template #icon><UndoIcon :size="20" /></template>
              </NcButton>
              <NcButton
                v-if="row.userId === currentUid"
                variant="tertiary"
                :aria-label="strings.deleteTrip"
                :title="strings.deleteTrip"
                @click="deletingRow = row"
              >
                <template #icon><DeleteIcon :size="20" /></template>
              </NcButton>
            </span>
          </li>
        </ul>

        <div v-if="canLoadMore" class="shop-history__more">
          <NcButton :disabled="loadingMore" @click="loadMore">{{ strings.loadMore }}</NcButton>
        </div>
      </template>
    </div>

    <ShoppingReviewDialog
      v-if="detailRow"
      :open="detailRow !== null"
      :house-id="houseIdNum"
      :session-id="detailRow.id"
      mode="history"
      :can-edit="detailRow.userId === currentUid"
      @updated="refreshDetailRow"
      @update:open="(v) => !v && (detailRow = null)"
    />

    <NcDialog
      v-if="deletingRow"
      :name="strings.deleteTripTitle"
      :open="!!deletingRow"
      close-on-click-outside
      @update:open="(v) => !v && (deletingRow = null)"
    >
      <p>{{ strings.deleteTripBody }}</p>
      <template #actions>
        <NcButton @click="deletingRow = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" :disabled="deleting" @click="submitDelete">
          {{ strings.delete }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="undoingRow"
      :name="strings.undoTripTitle"
      :open="!!undoingRow"
      close-on-click-outside
      @update:open="(v) => !v && (undoingRow = null)"
    >
      <p>{{ strings.undoTripBody }}</p>
      <template #actions>
        <NcButton @click="undoingRow = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="undoing" @click="submitUndo">
          {{ strings.undoConfirm }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="undoSkipped.length > 0"
      :name="strings.undoSkippedTitle"
      :open="undoSkipped.length > 0"
      close-on-click-outside
      @update:open="(v) => !v && (undoSkipped = [])"
    >
      <p>{{ strings.undoSkippedBody }}</p>
      <ul class="shop-history__skipped">
        <li v-for="skip in undoSkipped" :key="skip.itemId">
          <strong>{{ skip.itemName ?? strings.unnamedItem }}</strong>
          — {{ skip.reason === 'gone' ? strings.skippedGone : strings.skippedChanged }}
        </li>
      </ul>
      <template #actions>
        <NcButton variant="primary" @click="undoSkipped = []">{{ strings.ok }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t, n } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import DeleteIcon from '@icons/Delete.vue'
import HistoryIcon from '@icons/History.vue'
import CartOffIcon from '@icons/CartOff.vue'
import ClockOutlineIcon from '@icons/ClockOutline.vue'
import CloseCircleOutlineIcon from '@icons/CloseCircleOutline.vue'
import FormatListChecksIcon from '@icons/FormatListChecks.vue'
import UndoIcon from '@icons/Undo.vue'
import { ShoppingReviewDialog } from '@/components/ShoppingReview'
import PantryChip from '@/components/PantryChip'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { getCurrentUserId } from '@/utils/currentUser'
import { deleteSession, getHistory, undoSession } from '@/api/shopping'
import { formatMoney } from '@/utils/price'
import { formatDateTime, formatDuration } from '@/utils/datetime'
import type { ShoppingHistoryRow, ShoppingUndoSkip } from '@/api/types'

const router = useRouter()
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)

const PAGE_SIZE = 30

const scope = ref<'mine' | 'house'>('mine')
const rows = ref<ShoppingHistoryRow[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const canLoadMore = ref(false)
const detailRow = ref<ShoppingHistoryRow | null>(null)
const deletingRow = ref<ShoppingHistoryRow | null>(null)
const deleting = ref(false)
const undoingRow = ref<ShoppingHistoryRow | null>(null)
const undoing = ref(false)
const undoSkipped = ref<ShoppingUndoSkip[]>([])
const currentUid = getCurrentUserId()

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
  detailRow.value = row
}

/**
 * Pull the open trip's row back down after its totals were amended, so the list
 * figure matches the dialog. Rows are ordered deterministically, so the row's
 * own position is a stable offset; a shifted position (a trip closed meanwhile)
 * falls back to a full reload.
 */
async function refreshDetailRow() {
  const open = detailRow.value
  if (!open) return
  const index = rows.value.findIndex((r) => r.id === open.id)
  if (index === -1) return
  try {
    const [fetched] = await getHistory(houseIdNum.value, scope.value, 1, index)
    if (fetched?.id === open.id) {
      rows.value.splice(index, 1, fetched)
      detailRow.value = fetched
    } else {
      await load()
    }
  } catch (e) {
    showError((e as Error).message || strings.loadFailed)
  }
}

async function submitDelete() {
  const row = deletingRow.value
  if (!row) return
  deleting.value = true
  try {
    await deleteSession(houseIdNum.value, row.id)
    rows.value = rows.value.filter((r) => r.id !== row.id)
    deletingRow.value = null
  } catch (e) {
    showError((e as Error).message || strings.deleteFailed)
  } finally {
    deleting.value = false
  }
}

function canUndo(row: ShoppingHistoryRow): boolean {
  return (
    row.closedAt != null &&
    row.itemCount > 0 &&
    row.abortedAt == null &&
    row.undoneAt == null &&
    currentUid !== null &&
    row.memberIds.includes(currentUid)
  )
}

async function submitUndo() {
  const row = undoingRow.value
  if (!row) return
  undoing.value = true
  try {
    const outcome = await undoSession(houseIdNum.value, row.id)
    undoingRow.value = null
    if (outcome.status === 'conflict') {
      showError(strings.undoConflict)
    } else if (outcome.result.skipped.length > 0) {
      undoSkipped.value = outcome.result.skipped
    } else {
      showSuccess(strings.undoDone)
    }
    // Either way the row's state moved on: undone now, or by whoever got there first.
    const index = rows.value.findIndex((r) => r.id === row.id)
    if (index !== -1) {
      const [fetched] = await getHistory(houseIdNum.value, scope.value, 1, index)
      if (fetched?.id === row.id) rows.value.splice(index, 1, fetched)
    }
  } catch (e) {
    showError((e as Error).message || strings.undoFailed)
  } finally {
    undoing.value = false
  }
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
  // TRANSLATORS: Adjective, marks a shopping trip in history that was ended without checking anything off
  cancelled: t('pantry', 'Cancelled'),
  empty: t('pantry', 'No shopping history yet'),
  emptyMineBody: t('pantry', 'Trips you shop, alone or with others, will show up here.'),
  emptyHouseBody: t('pantry', 'Finished trips in this house will show up here.'),
  loadMore: t('pantry', 'Load more'),
  loadFailed: t('pantry', 'Could not load shopping history.'),
  deleteTrip: t('pantry', 'Delete trip'),
  deleteTripTitle: t('pantry', 'Delete trip'),
  deleteTripBody: t(
    'pantry',
    'This trip and its totals will be permanently removed from shopping history. This cannot be undone.',
  ),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, button confirming a shopping trip's deletion.
  delete: t('pantry', 'Delete'),
  deleteFailed: t('pantry', 'Could not delete the trip.'),
  // TRANSLATORS: Adjective, marks a finished shopping trip in history whose check-offs were reversed
  undone: t('pantry', 'Undone'),
  undoTrip: t('pantry', 'Undo trip'),
  undoTripTitle: t('pantry', 'Undo this trip?'),
  undoTripBody: t(
    'pantry',
    'Everything checked off on this trip goes back on its list, and what it added to your inventories is taken back out. Items changed since the trip are left as they are.',
  ),
  // TRANSLATORS: Verb, button confirming that a finished shopping trip is reversed
  undoConfirm: t('pantry', 'Undo trip'),
  undoDone: t('pantry', 'Shopping trip undone'),
  undoConflict: t('pantry', 'This trip can no longer be undone'),
  undoFailed: t('pantry', 'Could not undo the trip.'),
  undoSkippedTitle: t('pantry', 'Some items were left as they are'),
  undoSkippedBody: t('pantry', 'The trip was undone, but these items were not put back:'),
  unnamedItem: t('pantry', 'An item'),
  skippedChanged: t('pantry', 'it changed after the trip'),
  skippedGone: t('pantry', 'it was deleted'),
  ok: t('pantry', 'OK'),
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

  &__item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
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

  &__avatars {
    display: flex;
    flex-shrink: 0;
  }

  &__avatar {
    flex-shrink: 0;
    border: 2px solid var(--color-main-background);
    border-radius: 50%;

    & + & {
      margin-inline-start: -12px;
    }
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

  // Room for both row buttons whichever of them a row has, so the cards line up.
  &__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.25rem;
    flex-shrink: 0;
    min-width: calc(2 * var(--default-clickable-area, 34px) + 0.25rem);
  }

  &__skipped {
    margin: 0.5rem 0 0;
    padding-inline-start: 1.25rem;
    list-style: disc;
  }

  &__more {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
  }
}
</style>
