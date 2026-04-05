<template>
  <NcAppNavigation>
    <template #list>
      <template v-if="currentHouseId !== null">
        <li class="pantry-nav__house-label" :title="house?.name">
          {{ house?.name ?? '' }}
        </li>

        <NcAppNavigationItem
          :name="strings.lists"
          :to="{ name: 'lists', params: { houseId: String(currentHouseId) } }"
        >
          <template #icon>
            <CartIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          :name="strings.photos"
          :to="{ name: 'photos', params: { houseId: String(currentHouseId) } }"
        >
          <template #icon>
            <ImageIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          :name="strings.notes"
          :to="{ name: 'notes', params: { houseId: String(currentHouseId) } }"
        >
          <template #icon>
            <NoteIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          :name="strings.members"
          :to="{ name: 'members', params: { houseId: String(currentHouseId) } }"
        >
          <template #icon>
            <AccountGroupIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          v-if="canAdmin"
          :name="strings.houseSettings"
          :to="{ name: 'house-settings', params: { houseId: String(currentHouseId) } }"
        >
          <template #icon>
            <CogIcon :size="20" />
          </template>
        </NcAppNavigationItem>
      </template>

      <li v-else class="pantry-nav__welcome">
        {{ strings.welcomeHint }}
      </li>
    </template>

    <template #footer>
      <div class="pantry-switcher">
        <button
          ref="triggerRef"
          type="button"
          class="pantry-switcher__trigger"
          :aria-expanded="menuOpen"
          aria-haspopup="menu"
          @click="toggleMenu"
        >
          <HomeIcon :size="20" class="pantry-switcher__icon" />
          <span class="pantry-switcher__label">
            {{ house?.name ?? strings.pickHouse }}
          </span>
          <ChevronUpIcon v-if="menuOpen" :size="18" />
          <ChevronDownIcon v-else :size="18" />
        </button>

        <ul v-if="menuOpen" class="pantry-switcher__menu" role="menu">
          <li
            v-for="h in houses"
            :key="h.id"
            role="menuitem"
            class="pantry-switcher__item"
            :class="{ 'pantry-switcher__item--active': h.id === currentHouseId }"
            @click="pickHouse(h.id)"
          >
            <HomeIcon :size="18" />
            <span class="pantry-switcher__item-name">{{ h.name }}</span>
            <CheckIcon v-if="h.id === currentHouseId" :size="16" />
          </li>

          <li v-if="houses.length > 0" class="pantry-switcher__separator" role="separator"></li>

          <li
            role="menuitem"
            class="pantry-switcher__item pantry-switcher__item--action"
            @click="openCreate"
          >
            <PlusIcon :size="18" />
            <span>{{ strings.createHouse }}</span>
          </li>
        </ul>
      </div>
    </template>
  </NcAppNavigation>

  <NcDialog
    v-if="showCreate"
    :name="strings.createDialogTitle"
    :open="showCreate"
    @update:open="showCreate = $event"
  >
    <form class="pantry-create-form" @submit.prevent="submitCreate">
      <NcTextField
        v-model="newName"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
      />
      <NcTextField
        v-model="newDescription"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
      />
      <p v-if="createError" class="pantry-create-form__error">{{ createError }}</p>
    </form>
    <template #actions>
      <NcButton @click="showCreate = false">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="creating || !newName.trim()" @click="submitCreate">
        {{ creating ? strings.creating : strings.create }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import HomeIcon from '@icons/Home.vue'
import CartIcon from '@icons/Cart.vue'
import ImageIcon from '@icons/Image.vue'
import NoteIcon from '@icons/Note.vue'
import AccountGroupIcon from '@icons/AccountGroup.vue'
import CogIcon from '@icons/Cog.vue'
import ChevronUpIcon from '@icons/ChevronUp.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import CheckIcon from '@icons/Check.vue'
import PlusIcon from '@icons/Plus.vue'
import { useHouses } from '@/composables/useHouses'

const route = useRoute()
const router = useRouter()
const { houses, load, create, findById } = useHouses()

const currentHouseId = computed<number | null>(() => {
  const raw = route.params.houseId
  if (!raw) return null
  const id = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(id) ? id : null
})

const house = computed(() =>
  currentHouseId.value !== null ? findById(currentHouseId.value) : undefined,
)
const canAdmin = computed(() => {
  const role = house.value?.role
  return role === 'owner' || role === 'admin'
})

onMounted(() => {
  void load()
})

// -------- Dropup menu --------
const menuOpen = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

function closeMenu() {
  menuOpen.value = false
}

function onDocumentClick(e: MouseEvent) {
  if (!menuOpen.value) return
  const target = e.target as Node | null
  if (triggerRef.value && target && triggerRef.value.parentElement?.contains(target)) return
  closeMenu()
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))

async function pickHouse(id: number) {
  closeMenu()
  if (id === currentHouseId.value) return
  await router.push({ name: 'lists', params: { houseId: String(id) } })
}

// -------- Create house dialog --------
const showCreate = ref(false)
const newName = ref('')
const newDescription = ref('')
const creating = ref(false)
const createError = ref<string | null>(null)

function openCreate() {
  closeMenu()
  showCreate.value = true
}

async function submitCreate() {
  const name = newName.value.trim()
  if (!name) return
  creating.value = true
  createError.value = null
  try {
    const h = await create(name, newDescription.value.trim() || null)
    showCreate.value = false
    newName.value = ''
    newDescription.value = ''
    await router.push({ name: 'lists', params: { houseId: String(h.id) } })
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create house.')
  } finally {
    creating.value = false
  }
}

// Close the menu on route change so it does not linger after navigation.
watch(currentHouseId, closeMenu)

const strings = {
  lists: t('pantry', 'Shopping lists'),
  photos: t('pantry', 'Photo board'),
  notes: t('pantry', 'Notes wall'),
  members: t('pantry', 'Members'),
  houseSettings: t('pantry', 'House settings'),
  pickHouse: t('pantry', 'Pick a house'),
  createHouse: t('pantry', 'New house …'),
  welcomeHint: t('pantry', 'Pick or create a house to get started.'),
  createDialogTitle: t('pantry', 'Create a house'),
  nameLabel: t('pantry', 'Name:'),
  namePlaceholder: t('pantry', 'e.g. Home, Beach house'),
  descriptionLabel: t('pantry', 'Description (optional):'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  create: t('pantry', 'Create'),
  creating: t('pantry', 'Creating …'),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-nav__house-label {
  padding: 8px 16px 4px;
  font-weight: 600;
  color: var(--color-text-maxcontrast);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pantry-nav__welcome {
  padding: 16px;
  color: var(--color-text-maxcontrast);
  font-size: 0.9rem;
  line-height: 1.4;
}

.pantry-switcher {
  position: relative;
  padding: 8px;
  border-top: 1px solid var(--color-border);

  &__trigger {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: transparent;
    color: var(--color-main-text);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 12px);
    cursor: pointer;
    font-size: 0.95rem;
    text-align: left;

    &:hover,
    &:focus-visible {
      background: var(--color-background-hover);
    }
  }

  &__icon {
    color: var(--color-primary-element);
    flex-shrink: 0;
  }

  &__label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
  }

  &__menu {
    position: absolute;
    bottom: calc(100% + 4px);
    left: 8px;
    right: 8px;
    list-style: none;
    padding: 6px;
    margin: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 12px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    max-height: 60vh;
    overflow-y: auto;
    z-index: 20;
  }

  &__item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: var(--border-radius, 8px);
    cursor: pointer;
    color: var(--color-main-text);
    font-size: 0.9rem;

    &:hover,
    &:focus-visible {
      background: var(--color-background-hover);
    }

    &--active {
      background: var(--color-primary-element-light);
      color: var(--color-primary-element-light-text);
    }

    &--action {
      color: var(--color-primary-element);
      font-weight: 500;
    }
  }

  &__item-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__separator {
    height: 1px;
    background: var(--color-border);
    margin: 4px 2px;
  }
}

.pantry-create-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;

  &__error {
    color: var(--color-error);
    margin: 0;
  }
}
</style>
