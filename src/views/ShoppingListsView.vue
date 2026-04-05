<template>
  <div class="pantry-lists">
    <header class="pantry-lists__header">
      <h2>{{ strings.title }}</h2>
      <NcButton variant="primary" @click="showCreate = true">
        <template #icon>
          <PlusIcon :size="20" />
        </template>
        {{ strings.newList }}
      </NcButton>
    </header>

    <div v-if="loading" class="pantry-center">
      <NcLoadingIcon :size="36" />
    </div>

    <NcEmptyContent
      v-else-if="lists.length === 0"
      :name="strings.emptyTitle"
      :description="strings.emptyBody"
    >
      <template #icon>
        <CartIcon />
      </template>
      <template #action>
        <NcButton variant="primary" @click="showCreate = true">
          {{ strings.newList }}
        </NcButton>
      </template>
    </NcEmptyContent>

    <ul v-else class="pantry-lists__grid">
      <li v-for="list in lists" :key="list.id">
        <router-link
          :to="{
            name: 'list-detail',
            params: { houseId: String(houseIdNum), listId: String(list.id) },
          }"
          class="pantry-list-card"
        >
          <CartIcon :size="28" class="pantry-list-card__icon" />
          <div class="pantry-list-card__body">
            <h3>{{ list.name }}</h3>
            <p v-if="list.description">{{ list.description }}</p>
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
      <form class="pantry-form" @submit.prevent="submitCreate">
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
      </form>
      <template #actions>
        <NcButton @click="showCreate = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="!newName.trim()" @click="submitCreate">
          {{ strings.create }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import CartIcon from '@icons/Cart.vue'
import { useShoppingLists } from '@/composables/useShoppingList'

const props = defineProps<{ houseId: string }>()
const router = useRouter()

const houseIdNum = computed(() => Number(props.houseId))
const { lists, loading, load, create } = useShoppingLists(houseIdNum.value)

onMounted(load)
watch(
  () => props.houseId,
  () => load(),
)

const showCreate = ref(false)
const newName = ref('')
const newDescription = ref('')

async function submitCreate() {
  const name = newName.value.trim()
  if (!name) return
  const list = await create(name, newDescription.value.trim() || null)
  showCreate.value = false
  newName.value = ''
  newDescription.value = ''
  await router.push({
    name: 'list-detail',
    params: { houseId: String(houseIdNum.value), listId: String(list.id) },
  })
}

const strings = {
  title: t('pantry', 'Shopping lists'),
  newList: t('pantry', 'New list'),
  create: t('pantry', 'Create'),
  cancel: t('pantry', 'Cancel'),
  createDialogTitle: t('pantry', 'Create a shopping list'),
  nameLabel: t('pantry', 'Name:'),
  namePlaceholder: t('pantry', 'e.g. Weekly groceries'),
  descriptionLabel: t('pantry', 'Description (optional):'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  emptyTitle: t('pantry', 'No lists yet'),
  emptyBody: t('pantry', 'Create your first shopping list to start adding items.'),
}
</script>

<style scoped lang="scss">
.pantry-lists {
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

  &__grid {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }
}

.pantry-list-card {
  display: flex;
  gap: 0.75rem;
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
  }

  &__body {
    flex: 1;
    min-width: 0;

    h3 {
      margin: 0 0 4px 0;
      font-size: 1.05rem;
    }

    p {
      margin: 0;
      color: var(--color-text-maxcontrast);
      font-size: 0.9rem;
    }
  }
}

.pantry-center {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}
</style>
