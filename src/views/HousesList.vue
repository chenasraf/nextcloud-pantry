<template>
  <div class="pantry-houses">
    <header class="pantry-houses__header">
      <h2>{{ strings.title }}</h2>
      <NcButton variant="primary" @click="showCreate = true">
        <template #icon>
          <PlusIcon :size="20" />
        </template>
        {{ strings.createButton }}
      </NcButton>
    </header>

    <div v-if="loading && !loaded" class="pantry-houses__loading">
      <NcLoadingIcon :size="48" />
    </div>

    <NcEmptyContent
      v-else-if="loaded && houses.length === 0"
      :name="strings.emptyTitle"
      :description="strings.emptyBody"
    >
      <template #icon>
        <HomeIcon />
      </template>
      <template #action>
        <NcButton variant="primary" @click="showCreate = true">
          {{ strings.createButton }}
        </NcButton>
      </template>
    </NcEmptyContent>

    <ul v-else class="pantry-houses__grid">
      <li v-for="house in houses" :key="house.id">
        <router-link
          class="pantry-house-card"
          :to="{ name: 'lists', params: { houseId: String(house.id) } }"
        >
          <div class="pantry-house-card__icon">
            <HomeIcon :size="32" />
          </div>
          <div class="pantry-house-card__body">
            <h3 class="pantry-house-card__name">{{ house.name }}</h3>
            <p v-if="house.description" class="pantry-house-card__desc">
              {{ house.description }}
            </p>
            <span class="pantry-house-card__role">{{ roleLabel(house.role) }}</span>
          </div>
        </router-link>
      </li>
    </ul>

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
        <p v-if="createError" class="pantry-form-error">{{ createError }}</p>
      </form>
      <template #actions>
        <NcButton @click="showCreate = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="creating || !newName.trim()" @click="submitCreate">
          {{ creating ? strings.creatingLabel : strings.createButton }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import HomeIcon from '@icons/Home.vue'
import { useHouses } from '@/composables/useHouses'
import type { HouseRole } from '@/api/types'

const router = useRouter()
const { houses, loaded, loading, load, create } = useHouses()

const showCreate = ref(false)
const newName = ref('')
const newDescription = ref('')
const creating = ref(false)
const createError = ref<string | null>(null)

onMounted(() => {
  void load()
})

async function submitCreate() {
  const name = newName.value.trim()
  if (!name) return
  creating.value = true
  createError.value = null
  try {
    const house = await create(name, newDescription.value.trim() || null)
    showCreate.value = false
    newName.value = ''
    newDescription.value = ''
    await router.push({ name: 'lists', params: { houseId: String(house.id) } })
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create house.')
  } finally {
    creating.value = false
  }
}

function roleLabel(role: HouseRole): string {
  switch (role) {
    case 'owner':
      return t('pantry', 'Owner')
    case 'admin':
      return t('pantry', 'Administrator')
    default:
      return t('pantry', 'Member')
  }
}

const strings = {
  title: t('pantry', 'Your houses'),
  createButton: t('pantry', 'New house'),
  creatingLabel: t('pantry', 'Creating …'),
  createDialogTitle: t('pantry', 'Create a house'),
  nameLabel: t('pantry', 'Name:'),
  namePlaceholder: t('pantry', 'e.g. Home, Beach house'),
  descriptionLabel: t('pantry', 'Description (optional):'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  cancel: t('pantry', 'Cancel'),
  emptyTitle: t('pantry', 'No houses yet'),
  emptyBody: t(
    'pantry',
    'Create a house to start organizing your shopping lists, photos and notes.',
  ),
}
</script>

<style scoped lang="scss">
.pantry-houses {
  max-width: 1100px;
  margin: 0 auto;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;

    h2 {
      margin: 0;
    }
  }

  &__loading {
    display: flex;
    justify-content: center;
    padding: 2rem;
  }

  &__grid {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }
}

.pantry-house-card {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  background: var(--color-main-background);
  color: inherit;
  text-decoration: none;
  transition: background-color 0.15s ease;

  &:hover,
  &:focus-visible {
    background: var(--color-background-hover);
  }

  &__icon {
    color: var(--color-primary-element);
    display: flex;
    align-items: flex-start;
  }

  &__body {
    flex: 1;
    min-width: 0;
  }

  &__name {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
  }

  &__desc {
    margin: 0 0 6px 0;
    color: var(--color-text-maxcontrast);
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  &__role {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.pantry-create-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}

.pantry-form-error {
  color: var(--color-error);
  margin: 0;
}
</style>
