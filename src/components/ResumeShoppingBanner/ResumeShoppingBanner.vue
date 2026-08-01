<template>
  <div v-if="show" class="resume-banner">
    <span class="resume-banner__icon"><CartIcon :size="20" /></span>
    <span class="resume-banner__text">{{ bannerText }}</span>
    <NcButton variant="primary" @click="resume">{{ strings.resume }}</NcButton>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import CartIcon from '@icons/Cart.vue'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useStores } from '@/composables/useStores'
import { getCurrentSession } from '@/api/shopping'
import { formatDuration } from '@/utils/datetime'
import type { ShoppingSession } from '@/api/types'

// The list surfaces the resume banner targets (ADR 0009). Suppressed everywhere
// else — including the shopping routes, where a "resume" prompt is nonsense.
const LIST_ROUTES = ['lists', 'list-detail', 'all-lists']

const route = useRoute()
const router = useRouter()
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)

const session = ref<ShoppingSession | null>(null)
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

const onListRoute = computed(() => LIST_ROUTES.includes(String(route.name ?? '')))

// Same-house only: the global `current` session is cross-house, but the banner
// is about shopping *these* lists — a trip in another house shows nothing here.
const show = computed(
  () =>
    onListRoute.value &&
    session.value != null &&
    session.value.live &&
    session.value.houseId === houseIdNum.value,
)

function resolveStoreName(id: number): string {
  return useStores(houseIdNum.value).findById(id)?.name ?? ''
}

function storePosition(s: ShoppingSession): { index: number; total: number } | null {
  if (s.stores.length === 0 || s.activeStoreId == null) return null
  const ordered = [...s.stores].sort((a, b) => a.position - b.position)
  const idx = ordered.findIndex((st) => st.storeId === s.activeStoreId)
  if (idx === -1) return null
  return { index: idx + 1, total: ordered.length }
}

const bannerText = computed(() => {
  const s = session.value
  if (!s) return ''
  const elapsed = formatDuration(Math.floor((now.value - s.createdAt * 1000) / 1000))
  const store = s.activeStoreId != null ? resolveStoreName(s.activeStoreId) : ''
  if (!store) {
    // Storeless trip (or store not yet resolved): elapsed only.
    // TRANSLATORS: Resume banner for a shopping trip with no store. {elapsed} is a duration like "15 min".
    return t('pantry', 'Shopping · {elapsed}', { elapsed })
  }
  const pos = storePosition(s)
  if (pos) {
    // TRANSLATORS: Resume banner. {store} is a store name, {index}/{total} the position in the planned store sequence, {elapsed} a duration.
    return t('pantry', 'Shopping at {store} (Store {index}/{total}) · {elapsed}', {
      store,
      index: String(pos.index),
      total: String(pos.total),
      elapsed,
    })
  }
  // TRANSLATORS: Resume banner. {store} is a store name, {elapsed} a duration like "15 min".
  return t('pantry', 'Shopping at {store} · {elapsed}', { store, elapsed })
})

// Fetch `current` on mount and on route changes into a list view — no continuous
// poll (a shopper browsing lists isn't concurrently checking items). Off-list
// navigations no-op. Elapsed ticks via the local timer only.
async function refresh() {
  if (!onListRoute.value) return
  try {
    session.value = await getCurrentSession()
    if (session.value && session.value.houseId === houseIdNum.value) {
      await useStores(houseIdNum.value).load()
    }
  } catch {
    session.value = null
  }
}

watch(
  () => route.fullPath,
  () => void refresh(),
)

onMounted(() => {
  void refresh()
  timer = setInterval(() => (now.value = Date.now()), 30000)
})

onBeforeUnmount(() => {
  if (timer !== null) clearInterval(timer)
})

function resume() {
  const s = session.value
  if (!s) return
  void router.push({
    name: 'shopping-session',
    params: { houseId: String(s.houseId), sessionId: String(s.id) },
  })
}

const strings = {
  resume: t('pantry', 'Resume'),
}
</script>

<style scoped lang="scss">
.resume-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  padding: 0.5rem 1rem;
  background: var(--color-primary-element-light, var(--color-background-hover));
  border-bottom: 1px solid var(--color-border);

  &__icon {
    display: inline-flex;
    flex-shrink: 0;
    color: var(--color-primary-element);
  }

  &__text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
  }
}
</style>
