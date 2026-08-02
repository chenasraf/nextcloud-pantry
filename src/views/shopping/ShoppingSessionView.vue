<template>
  <div class="shop">
    <div v-if="loading" class="shop__center">
      <NcLoadingIcon :size="44" />
    </div>

    <template v-else-if="session">
      <header class="shop__bar">
        <div class="shop__bar-inner">
          <div v-if="sequence.length" class="shop__stores">
            <button
              v-for="(entry, index) in sequence"
              :key="entry.storeId"
              type="button"
              class="shop__store"
              :class="{
                'shop__store--active': entry.storeId === session.activeStoreId,
                'shop__store--past': index < activeIndex,
              }"
              @click="goToStore(entry.storeId)"
            >
              <span class="shop__store-icon" :style="{ color: storeColor(entry.storeId) }">
                <component :is="storeIcon(entry.storeId)" :size="18" />
              </span>
              <span class="shop__store-name">{{ storeName(entry.storeId) }}</span>
              <span v-if="shoppersAt(entry.storeId).length" class="shop__store-avatars">
                <NcAvatar
                  v-for="uid in shoppersAt(entry.storeId)"
                  :key="uid"
                  :user="uid"
                  :size="20"
                  :show-user-status="false"
                  disable-menu
                />
              </span>
            </button>
          </div>
          <div class="shop__progress">
            <span class="shop__progress-label">{{ progressLabel }}</span>
            <div class="shop__progress-track">
              <div class="shop__progress-fill" :style="{ width: progressPct + '%' }" />
            </div>
            <button
              type="button"
              class="shop__privacy"
              :class="{ 'shop__privacy--on': isPrivate }"
              :disabled="savingPrivacy"
              :aria-pressed="isPrivate"
              :aria-label="privacyLabel"
              :title="privacyLabel"
              @click="togglePrivacy"
            >
              <IncognitoIcon v-if="isPrivate" :size="18" />
              <IncognitoOffIcon v-else :size="18" />
            </button>
          </div>
        </div>
      </header>

      <div class="shop__scroll">
        <div class="shop__body">
          <NcEmptyContent v-if="groups.length === 0" :name="emptyTitle" :description="emptyBody">
            <template #icon><CartCheckIcon /></template>
          </NcEmptyContent>

          <ul v-if="groups.length > 0" class="shop__items">
            <template v-for="group in groups" :key="String(group.key)">
              <li
                class="shop__category-header"
                :style="group.category ? { color: group.category.color } : undefined"
              >
                <component :is="categoryIconComponent(group.category?.icon)" :size="18" />
                <span class="shop__category-header-name">
                  {{ group.category?.name ?? strings.uncategorized }}
                </span>
                <span class="shop__category-header-count">{{ group.items.length }}</span>
              </li>
              <ChecklistItemRow
                v-for="item in group.items"
                :key="item.id"
                :item="item"
                :category="group.category"
                :hide-category="true"
                :house-id="houseIdNum"
                :tap-row-to-complete="tapRowToComplete"
                compact
                @toggle="handleToggle"
                @preview="openPreview"
              />
            </template>
          </ul>

          <section v-if="doneItems.length > 0" class="shop__done">
            <button type="button" class="shop__done-toggle" @click="doneOpen = !doneOpen">
              <ChevronDownIcon v-if="doneOpen" :size="20" />
              <ChevronRightIcon v-else :size="20" />
              <span class="shop__done-title">{{ doneTitle }}</span>
              <span class="shop__done-total">{{ doneEstimateText }}</span>
            </button>
            <ul v-if="doneOpen" class="shop__items shop__items--done">
              <ChecklistItemRow
                v-for="item in doneItems"
                :key="item.id"
                :item="item"
                :category="categoryFor(item.categoryId)"
                :house-id="houseIdNum"
                :tap-row-to-complete="tapRowToComplete"
                compact
                @toggle="handleToggle"
                @preview="openPreview"
              />
            </ul>
          </section>
        </div>
      </div>

      <button type="button" class="shop__fab" :disabled="busy" @click="onPrimaryAction">
        <span>{{ hasNextStore ? strings.nextStore : strings.finish }}</span>
        <ArrowRightIcon v-if="hasNextStore" :size="20" />
        <FlagCheckeredIcon v-else :size="20" />
      </button>

      <ChecklistImagePreview
        v-if="previewing"
        :open="!!previewing"
        :item="previewing"
        :house-id="houseIdNum"
        @update:open="(v) => !v && (previewing = null)"
      />

      <ShoppingReviewDialog
        v-if="reviewOpen"
        :open="reviewOpen"
        :house-id="houseIdNum"
        :session-id="session.id"
        :mode="reviewMode"
        :store-id="session.activeStoreId"
        @update:open="reviewOpen = $event"
        @confirm="onReviewConfirm"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t, n } from '@nextcloud/l10n'
import { showError, showInfo } from '@nextcloud/dialogs'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import CartCheckIcon from '@icons/CartCheck.vue'
import ArrowRightIcon from '@icons/ArrowRight.vue'
import FlagCheckeredIcon from '@icons/FlagCheckered.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import ChevronRightIcon from '@icons/ChevronRight.vue'
import IncognitoIcon from '@icons/Incognito.vue'
import IncognitoOffIcon from '@icons/IncognitoOff.vue'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { ShoppingReviewDialog } from '@/components/ShoppingReview'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { useStores } from '@/composables/useStores'
import { useCategories } from '@/composables/useCategories'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useTapRowToComplete } from '@/composables/useTapRowToComplete'
import { formatEstimate } from '@/utils/price'
import { getCurrentUserId } from '@/utils/currentUser'
import {
  getCurrentSession,
  listSessionItems,
  advanceStore,
  closeSession as apiCloseSession,
  checkSessionItem,
  uncheckSessionItem,
  getReview,
  sendHeartbeat,
  setSessionPrivacy,
} from '@/api/shopping'
import type { Category, ChecklistItem, ShoppingPresenceEntry, ShoppingSession } from '@/api/types'

const route = useRoute()
const router = useRouter()

// House id from the route (already numeric); session id coerced from the route
// param — both must be numbers to compare against the server's numeric ids.
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)
const sessionIdNum = computed(() => Number(route.params.sessionId))

const { load: loadStores, findById: findStore } = useStores(houseIdNum.value)
const { load: loadCategories, findById: findCategory } = useCategories(houseIdNum.value)
const { tapRowToComplete } = useTapRowToComplete()

const session = ref<ShoppingSession | null>(null)
const items = ref<ChecklistItem[]>([])
// Items checked during this view; kept so they stay visible and uncheckable
// (the server's item list only returns unchecked items).
const doneItems = ref<ChecklistItem[]>([])
const loading = ref(true)
const busy = ref(false)
const previewing = ref<ChecklistItem | null>(null)
const doneOpen = ref(false)
const reviewOpen = ref(false)
const reviewMode = ref<'advance' | 'close'>('close')
// Derived presence of housemates out shopping (ADR 0004), refreshed on the same
// focused poll as the item list. The caller's own entry is filtered client-side.
const presence = ref<ShoppingPresenceEntry[]>([])
const savingPrivacy = ref(false)

const selfUid = getCurrentUserId()

const isPrivate = computed(() => session.value?.isPrivate ?? false)
const privacyLabel = computed(() => (isPrivate.value ? strings.privacyOn : strings.privacyOff))

// Housemates (not the caller) attributed to each store, for the store-bar avatars.
const presenceByStore = computed(() => {
  const map = new Map<number, string[]>()
  for (const entry of presence.value) {
    if (entry.userId === selfUid || entry.activeStoreId == null) continue
    const list = map.get(entry.activeStoreId) ?? []
    list.push(entry.userId)
    map.set(entry.activeStoreId, list)
  }
  return map
})
function shoppersAt(storeId: number): string[] {
  return presenceByStore.value.get(storeId) ?? []
}

const sequence = computed(() => session.value?.stores ?? [])
const activeIndex = computed(() =>
  sequence.value.findIndex((s) => s.storeId === session.value?.activeStoreId),
)
const hasNextStore = computed(
  () => activeIndex.value >= 0 && activeIndex.value < sequence.value.length - 1,
)

const progressPct = computed(() => {
  const total = doneItems.value.length + items.value.length
  return total === 0 ? 0 : Math.round((doneItems.value.length / total) * 100)
})
const progressLabel = computed(() =>
  n('pantry', '%n in cart', '%n in cart', doneItems.value.length),
)
const doneTitle = computed(() => n('pantry', 'Done (%n)', 'Done (%n)', doneItems.value.length))

// Client-side estimate of what's in the cart this trip, shown in the drawer
// header. Range-aware, per-currency; the authoritative totals live in the review.
const doneEstimateText = computed(() => {
  const byCurrency = new Map<string, { min: number; max: number }>()
  for (const item of doneItems.value) {
    if ((item.priceType !== 'set' && item.priceType !== 'range') || item.priceMin == null) continue
    const cur = item.priceCurrency ?? ''
    const max = item.priceType === 'range' && item.priceMax != null ? item.priceMax : item.priceMin
    const entry = byCurrency.get(cur) ?? { min: 0, max: 0 }
    entry.min += item.priceMin
    entry.max += max
    byCurrency.set(cur, entry)
  }
  return formatEstimate([...byCurrency].map(([currency, r]) => ({ currency, ...r })))
})

interface Group {
  key: number | null
  category: Category | null
  items: ChecklistItem[]
}

// The server returns items already ordered (category sort, then item sort, with
// uncategorized trailing). Group while preserving that order.
const groups = computed<Group[]>(() => {
  const order: (number | null)[] = []
  const buckets = new Map<number | null, ChecklistItem[]>()
  for (const item of items.value) {
    const key = item.categoryId
    if (!buckets.has(key)) {
      buckets.set(key, [])
      order.push(key)
    }
    buckets.get(key)!.push(item)
  }
  return order.map((key) => ({
    key,
    category: key == null ? null : (findCategory(key) ?? null),
    items: buckets.get(key)!,
  }))
})

const emptyTitle = computed(() =>
  hasNextStore.value ? strings.emptyStoreTitle : strings.emptyDoneTitle,
)
const emptyBody = computed(() =>
  hasNextStore.value ? strings.emptyStoreBody : strings.emptyDoneBody,
)

function categoryFor(id: number | null): Category | null {
  return id == null ? null : (findCategory(id) ?? null)
}
function storeName(id: number): string {
  return findStore(id)?.name ?? ''
}
function storeColor(id: number): string {
  return findStore(id)?.color ?? 'var(--color-primary-element)'
}
function storeIcon(id: number) {
  return storeIconComponent(findStore(id)?.icon)
}

function openPreview(item: ChecklistItem) {
  previewing.value = item
}

async function loadItems() {
  if (!session.value) return
  const fetched = await listSessionItems(houseIdNum.value, session.value.id)
  const doneIds = new Set(doneItems.value.map((i) => i.id))
  items.value = fetched.filter((i) => !doneIds.has(i.id))
}

// Whole-row check records the item in the session log against the active store.
// Checked items move into the Done drawer (kept visible so they can be
// un-checked); un-checking moves them back. Optimistic, with a revert on failure.
async function handleToggle(id: number) {
  if (!session.value) return
  const sessionId = session.value.id
  const uncheckedIdx = items.value.findIndex((i) => i.id === id)
  if (uncheckedIdx !== -1) {
    const [item] = items.value.splice(uncheckedIdx, 1)
    item.done = true
    doneItems.value = [item, ...doneItems.value]
    try {
      await checkSessionItem(houseIdNum.value, sessionId, item.id)
    } catch (e) {
      doneItems.value = doneItems.value.filter((i) => i.id !== id)
      item.done = false
      items.value.splice(uncheckedIdx, 0, item)
      showError((e as Error).message || strings.checkFailed)
    }
    return
  }
  const doneIdx = doneItems.value.findIndex((i) => i.id === id)
  if (doneIdx !== -1) {
    const [item] = doneItems.value.splice(doneIdx, 1)
    item.done = false
    items.value = [...items.value, item]
    try {
      await uncheckSessionItem(houseIdNum.value, sessionId, item.id)
    } catch (e) {
      items.value = items.value.filter((i) => i.id !== id)
      item.done = true
      doneItems.value.splice(doneIdx, 0, item)
      showError((e as Error).message || strings.checkFailed)
    }
  }
}

async function goToStore(storeId: number) {
  if (!session.value || storeId === session.value.activeStoreId || busy.value) return
  busy.value = true
  try {
    session.value = await advanceStore(houseIdNum.value, session.value.id, storeId)
    await loadItems()
  } catch (e) {
    showError((e as Error).message || strings.advanceFailed)
  } finally {
    busy.value = false
  }
}

// Heartbeat: stamp our liveness and pull the fresh house presence in one round
// trip. Non-critical — a failure just leaves the last-known avatars in place.
async function pollPresence() {
  if (!session.value) return
  try {
    presence.value = await sendHeartbeat(houseIdNum.value)
  } catch {
    /* keep the last-known presence */
  }
}

// Toggle "shop privately": hide (or re-show) our trip in housemates' presence and
// history. Optimistic on the session flag, then refresh presence to reflect it.
async function togglePrivacy() {
  if (!session.value || savingPrivacy.value) return
  savingPrivacy.value = true
  try {
    session.value = await setSessionPrivacy(houseIdNum.value, session.value.id, !isPrivate.value)
    await pollPresence()
  } catch (e) {
    showError((e as Error).message || strings.privacyFailed)
  } finally {
    savingPrivacy.value = false
  }
}

// The primary action opens the review first: a per-store till summary before
// advancing, or the full grouped review before finishing.
function onPrimaryAction() {
  if (busy.value) return
  reviewMode.value = hasNextStore.value ? 'advance' : 'close'
  reviewOpen.value = true
}

async function onReviewConfirm() {
  reviewOpen.value = false
  if (reviewMode.value === 'advance') {
    const next = sequence.value[activeIndex.value + 1]
    if (next) await goToStore(next.storeId)
  } else {
    await finish()
  }
}

async function finish() {
  if (!session.value) return
  busy.value = true
  try {
    await apiCloseSession(houseIdNum.value, session.value.id)
    showInfo(strings.finished)
    await router.push({ name: 'lists', params: { houseId: String(houseIdNum.value) } })
  } catch (e) {
    showError((e as Error).message || strings.finishFailed)
    busy.value = false
  }
}

// ----- Live refresh: poll while the tab is focused, and on refocus. -----
let pollTimer: ReturnType<typeof setInterval> | null = null
const POLL_MS = 60000

function pollTick() {
  void loadItems()
  void pollPresence()
}

function onVisibility() {
  if (document.visibilityState === 'visible') pollTick()
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([loadStores(), loadCategories()])
    // One live session per user, so the caller's current session IS this one.
    const current = await getCurrentSession()
    if (!current || current.id !== sessionIdNum.value || !current.live) {
      // The trip ended or belongs elsewhere — send the shopper back to setup.
      await router.replace({
        name: 'shopping-start',
        params: { houseId: String(houseIdNum.value) },
      })
      return
    }
    session.value = current
    // Seed the Done drawer from THIS session's checked log, so it survives
    // reloads. Then load the still-to-buy items (excluding anything already done).
    try {
      const review = await getReview(houseIdNum.value, current.id)
      doneItems.value = review.stores.flatMap((s) => s.items)
    } catch {
      // Non-critical; the drawer just starts from this view's checks.
    }
    await loadItems()
    // Seed presence immediately (also stamps our first heartbeat), then poll.
    await pollPresence()
    pollTimer = setInterval(() => {
      if (document.visibilityState === 'visible') pollTick()
    }, POLL_MS)
    document.addEventListener('visibilitychange', onVisibility)
  } catch (e) {
    showError((e as Error).message || strings.loadFailed)
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  if (pollTimer !== null) clearInterval(pollTimer)
  document.removeEventListener('visibilitychange', onVisibility)
})

const strings = {
  uncategorized: t('pantry', 'Uncategorized'),
  nextStore: t('pantry', 'Next store'),
  finish: t('pantry', 'Finish'),
  finished: t('pantry', 'Shopping trip finished'),
  emptyStoreTitle: t('pantry', 'All checked off here'),
  emptyStoreBody: t('pantry', 'Move on to the next store when you are ready.'),
  emptyDoneTitle: t('pantry', 'All done'),
  emptyDoneBody: t('pantry', 'Everything on your list is in the cart.'),
  checkFailed: t('pantry', 'Could not check off the item.'),
  advanceFailed: t('pantry', 'Could not switch store.'),
  finishFailed: t('pantry', 'Could not finish the trip.'),
  loadFailed: t('pantry', 'Could not load the shopping trip.'),
  // TRANSLATORS: Toggle button label; the trip is currently private (hidden from
  // housemates). Activating it makes the trip visible again.
  privacyOn: t('pantry', 'Shopping privately'),
  // TRANSLATORS: Toggle button label; the trip is currently visible to
  // housemates. Activating it hides the trip (shop privately).
  privacyOff: t('pantry', 'Shop privately'),
  privacyFailed: t('pantry', 'Could not change privacy.'),
}
</script>

<style scoped lang="scss">
.shop {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  position: relative;

  &__center {
    display: flex;
    justify-content: center;
    align-items: center;
    flex: 1;
  }

  &__bar {
    flex-shrink: 0;
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
  }

  &__bar-inner {
    max-width: 900px;
    margin: 0 auto;
    padding: 0.5rem 0.75rem;
  }

  &__stores {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
  }

  &__store {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.7rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-pill, 999px);
    background: var(--color-background-hover);
    color: var(--color-main-text);
    cursor: pointer;
    white-space: nowrap;

    &--active {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
      border-color: var(--color-primary-element);

      .shop__store-icon {
        color: var(--color-primary-element-text) !important;
      }
    }

    &--past {
      opacity: 0.5;
    }
  }

  &__store-icon {
    display: inline-flex;
    flex-shrink: 0;
  }

  // Overlapping avatar stack of housemates attributed to this store.
  &__store-avatars {
    display: inline-flex;
    align-items: center;
    margin-inline-start: 0.25rem;

    :deep(.avatardiv) {
      box-shadow: 0 0 0 2px var(--color-main-background);

      &:not(:first-child) {
        margin-inline-start: -8px;
      }
    }
  }

  &__privacy {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    padding: 0;
    border: none;
    border-radius: var(--border-radius, 8px);
    background: transparent;
    color: var(--color-text-maxcontrast);
    cursor: pointer;

    &:hover,
    &:focus-visible {
      background: var(--color-background-hover);
    }

    &--on {
      color: var(--color-primary-element-text);
      background: var(--color-primary-element);

      &:hover,
      &:focus-visible {
        background: var(--color-primary-element-hover);
      }
    }

    &:disabled {
      opacity: 0.6;
      cursor: default;
    }
  }

  &__progress {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__progress-label {
    flex-shrink: 0;
  }

  &__progress-track {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: var(--color-background-dark);
    overflow: hidden;
  }

  &__progress-fill {
    height: 100%;
    background: var(--color-success);
    transition: width 0.2s ease;
  }

  &__scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
  }

  &__body {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 0.5rem 6rem;
  }

  &__items {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;

    &--done {
      opacity: 0.85;
    }
  }

  // Mirrors the checklist detail category header: opaque, sticky, category-colored.
  &__category-header {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: sticky;
    top: 0;
    z-index: 2;
    margin: 0;
    padding: 0.6rem 0.25rem 0.4rem;
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }

  &__category-header-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__category-header-count {
    margin-inline-start: auto;
    font-weight: 700;
  }

  &__done {
    margin-top: 1.5rem;
    border-top: 1px solid var(--color-border);
  }

  &__done-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    padding: 0.6rem 0.25rem;
    border: none;
    background: transparent;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
  }

  &__done-title {
    white-space: nowrap;
  }

  &__done-total {
    margin-inline-start: auto;
    text-transform: none;
    letter-spacing: normal;
    font-variant-numeric: tabular-nums;
  }

  &__fab {
    position: absolute;
    bottom: 1.5rem;
    inset-inline-end: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: none;
    border-radius: var(--border-radius-pill, 999px);
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);

    &:disabled {
      opacity: 0.6;
      cursor: default;
    }
  }
}
</style>
