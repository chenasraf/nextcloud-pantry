<template>
  <div class="pantry-house-settings">
    <h2>{{ strings.title }}</h2>

    <form v-if="house" class="pantry-form" @submit.prevent="save">
      <NcTextField
        v-model="name"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
      />
      <NcTextField
        v-model="description"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
      />
      <div class="pantry-form__actions">
        <NcButton type="submit" variant="primary" :disabled="saving || !name.trim()">
          {{ saving ? strings.saving : strings.save }}
        </NcButton>
      </div>
    </form>

    <hr v-if="isOwner" class="pantry-divider" />

    <section v-if="isOwner" class="pantry-danger">
      <h3>{{ strings.dangerTitle }}</h3>
      <p>{{ strings.dangerBody }}</p>
      <NcButton variant="error" @click="confirmingDelete = true">
        {{ strings.deleteButton }}
      </NcButton>
    </section>

    <NcDialog
      v-if="confirmingDelete"
      :name="strings.deleteDialogTitle"
      :open="confirmingDelete"
      @update:open="confirmingDelete = $event"
    >
      <p>{{ strings.deleteConfirmBody }}</p>
      <template #actions>
        <NcButton @click="confirmingDelete = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="deleteHouse">{{ strings.deleteButton }}</NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useHouses } from '@/composables/useHouses'

const router = useRouter()
const { house, isOwner, refresh } = useCurrentHouse()
const { update, remove } = useHouses()

const name = ref('')
const description = ref('')
const saving = ref(false)
const confirmingDelete = ref(false)

watch(
  house,
  (h) => {
    if (h) {
      name.value = h.name
      description.value = h.description ?? ''
    }
  },
  { immediate: true },
)

async function save() {
  if (!house.value) return
  saving.value = true
  try {
    await update(house.value.id, {
      name: name.value.trim(),
      description: description.value.trim() || null,
    })
    await refresh()
  } finally {
    saving.value = false
  }
}

async function deleteHouse() {
  if (!house.value) return
  await remove(house.value.id)
  confirmingDelete.value = false
  await router.push({ name: 'home' })
}

const strings = {
  title: t('pantry', 'House settings'),
  nameLabel: t('pantry', 'Name:'),
  namePlaceholder: t('pantry', 'House name'),
  descriptionLabel: t('pantry', 'Description:'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  save: t('pantry', 'Save changes'),
  saving: t('pantry', 'Saving …'),
  dangerTitle: t('pantry', 'Danger zone'),
  dangerBody: t(
    'pantry',
    'Deleting a house permanently removes all of its lists, items, and membership records. This cannot be undone.',
  ),
  deleteButton: t('pantry', 'Delete house'),
  deleteDialogTitle: t('pantry', 'Delete this house?'),
  deleteConfirmBody: t(
    'pantry',
    'All lists, items and member records for this house will be permanently deleted.',
  ),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-house-settings {
  max-width: 640px;
  margin: 0 auto;

  h2 {
    margin-top: 0;
  }
}

.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  &__actions {
    display: flex;
    justify-content: flex-end;
  }
}

.pantry-divider {
  margin: 2rem 0;
  border: none;
  border-top: 1px solid var(--color-border);
}

.pantry-danger {
  h3 {
    margin-top: 0;
    color: var(--color-error);
  }

  p {
    color: var(--color-text-maxcontrast);
  }
}
</style>
