<template>
  <div v-if="show" class="join-banner">
    <span class="join-banner__icon"><CartIcon :size="20" /></span>
    <span class="join-banner__text">{{ bannerText }}</span>
    <NcButton variant="primary" :disabled="joining" @click="onJoin">{{ strings.join }}</NcButton>

    <NcDialog
      :open="confirmOpen"
      :name="strings.confirmTitle"
      :message="confirmMessage"
      @update:open="confirmOpen = $event"
    >
      <template #actions>
        <NcButton variant="tertiary" @click="confirmOpen = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="joining" @click="endMineAndJoin">
          {{ strings.endAndJoin }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { n, t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import CartIcon from '@icons/Cart.vue'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useHouseMembers } from '@/composables/useHouseMembers'
import { useStores } from '@/composables/useStores'
import { closeSession, getCurrentSession, getPresence, joinSession } from '@/api/shopping'
import { getCurrentUserId } from '@/utils/currentUser'
import type { ShoppingPresenceEntry, ShoppingSession } from '@/api/types'

// Mirrors the resume banner: the list surfaces are where a housemate is when a
// trip they could join starts. Suppressed on the shopping routes themselves.
const LIST_ROUTES = ['lists', 'list-detail', 'all-lists']

// A housemate's trip is discovered by polling presence, so the banner needs a
// cadence of its own — unlike resume, which only ever reflects the caller's own
// state and can refresh on navigation alone.
const POLL_MS = 60000

const route = useRoute()
const router = useRouter()
const { houseId } = useCurrentHouse()
const houseIdNum = computed(() => houseId.value ?? 0)
const me = getCurrentUserId()

const joinable = ref<ShoppingPresenceEntry | null>(null)
const mine = ref<ShoppingSession | null>(null)
const joining = ref(false)
const confirmOpen = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

const onListRoute = computed(() => LIST_ROUTES.includes(String(route.name ?? '')))

const show = computed(() => onListRoute.value && joinable.value !== null)

function resolveStoreName(id: number): string {
  return useStores(houseIdNum.value).findById(id)?.name ?? ''
}

const displayNames = computed(() => useHouseMembers(houseIdNum.value).displayNameByUid.value)

function shopperName(entry: ShoppingPresenceEntry): string {
  return displayNames.value[entry.userId] ?? entry.userId
}

const bannerText = computed(() => {
  const entry = joinable.value
  if (!entry) return ''
  const name = shopperName(entry)
  // A trip several housemates already share reads as the group, so the banner
  // does not imply the starter is out alone. The store is dropped in that case
  // to keep the line short.
  const others = entry.memberIds.length - 1
  if (others > 0) {
    // TRANSLATORS: Join banner for a trip several housemates share. {name} is the housemate who started it, %n the number of others already shopping with them.
    return n(
      'pantry',
      '{name} and %n other are shopping',
      '{name} and %n others are shopping',
      others,
      { name },
    )
  }
  const store = entry.activeStoreId != null ? resolveStoreName(entry.activeStoreId) : ''
  if (!store) {
    // TRANSLATORS: Join banner for a housemate's trip with no store chosen. {name} is the housemate's display name.
    return t('pantry', '{name} is shopping', { name })
  }
  // TRANSLATORS: Join banner. {name} is the housemate's display name, {store} the store they are at.
  return t('pantry', '{name} is shopping at {store}', { name, store })
})

const confirmMessage = computed(() => {
  const entry = joinable.value
  if (!entry) return ''
  // TRANSLATORS: Confirm dialog shown when joining a housemate's trip would end the caller's own. {name} is the housemate's display name.
  return t(
    'pantry',
    'Your current shopping trip will be ended and saved to your history, then you will join {name}.',
    { name: shopperName(entry) },
  )
})

// Presence is house-wide and includes the caller's own trip; the banner wants
// the trips they are *not* already in. Most recent heartbeat first, so the head
// of the list is the freshest trip to offer.
function pickJoinable(entries: ShoppingPresenceEntry[]): ShoppingPresenceEntry | null {
  return entries.find((e) => e.userId !== me && !e.memberIds.includes(me ?? '')) ?? null
}

async function refresh() {
  if (!onListRoute.value || houseIdNum.value === 0) return
  try {
    const [entries, current] = await Promise.all([
      getPresence(houseIdNum.value),
      getCurrentSession(),
    ])
    mine.value = current
    joinable.value = pickJoinable(entries)
    if (joinable.value?.activeStoreId != null) {
      await useStores(houseIdNum.value).load()
    }
  } catch {
    joinable.value = null
  }
}

function onJoin() {
  // Only the caller's *own* trip blocks a join; a trip they merely joined is
  // left by the join itself, so it needs no confirmation.
  if (mine.value?.live && mine.value.userId === me) {
    confirmOpen.value = true
    return
  }
  void doJoin()
}

async function endMineAndJoin() {
  const own = mine.value
  if (!own) {
    confirmOpen.value = false
    return
  }
  joining.value = true
  try {
    await closeSession(own.houseId, own.id)
    mine.value = null
    confirmOpen.value = false
    await doJoin()
  } catch (e) {
    showError((e as Error).message || strings.joinFailed)
  } finally {
    joining.value = false
  }
}

async function doJoin() {
  const entry = joinable.value
  if (!entry) return
  joining.value = true
  try {
    const result = await joinSession(houseIdNum.value, entry.sessionId)
    if (result.status === 'conflict') {
      // Another device started a trip for us between refresh and tap.
      mine.value = result.session
      confirmOpen.value = true
      return
    }
    joinable.value = null
    void router.push({
      name: 'shopping-session',
      params: { houseId: String(houseIdNum.value), sessionId: String(entry.sessionId) },
    })
  } catch (e) {
    showError((e as Error).message || strings.joinFailed)
  } finally {
    joining.value = false
  }
}

watch(
  () => route.fullPath,
  () => void refresh(),
)

onMounted(() => {
  void refresh()
  timer = setInterval(() => {
    if (document.visibilityState === 'visible') void refresh()
  }, POLL_MS)
})

onBeforeUnmount(() => {
  if (timer !== null) clearInterval(timer)
})

const strings = {
  // TRANSLATORS: Verb, button that joins the caller to a housemate's shopping trip
  join: t('pantry', 'Join'),
  cancel: t('pantry', 'Cancel'),
  confirmTitle: t('pantry', 'Join this shopping trip?'),
  endAndJoin: t('pantry', 'End mine and join'),
  joinFailed: t('pantry', 'Could not join the shopping trip'),
}
</script>

<style scoped lang="scss">
.join-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  padding: 0.5rem 1rem;
  background: var(--color-primary-element-light, var(--color-background-hover));
  border-bottom: 1px solid var(--color-border);
  // Matches the resume banner: clears the NC sidebar toggle that overlays the
  // top-left of the content area.
  padding-inline-start: calc(var(--default-clickable-area, 44px) + 0.75rem);

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
