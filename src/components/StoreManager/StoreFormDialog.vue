<template>
  <NcDialog
    :name="dialogName"
    :open="open"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form class="pantry-store-form" autocomplete="off" @submit.prevent="submit">
      <NcTextField
        v-model="nameValue"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <div>
        <label class="pantry-store-form__sub">{{ strings.iconLabel }}</label>
        <div class="pantry-store-form__icon-grid">
          <button
            v-for="opt in STORE_ICONS"
            :key="opt.key"
            type="button"
            class="pantry-store-form__icon-button"
            :class="{ 'pantry-store-form__icon-button--active': iconValue === opt.key }"
            :title="opt.label"
            :style="{ color: colorValue }"
            @click="iconValue = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
      </div>
      <div>
        <label class="pantry-store-form__sub">{{ strings.colorLabel }}</label>
        <div class="pantry-store-form__color-grid">
          <button
            v-for="c in STORE_COLORS"
            :key="c"
            type="button"
            class="pantry-store-form__color-swatch"
            :class="{ 'pantry-store-form__color-swatch--active': colorValue === c }"
            :style="{ backgroundColor: c }"
            :aria-label="c"
            @click="colorValue = c"
          />
        </div>
      </div>
      <p v-if="error" class="pantry-store-form__error">{{ error }}</p>
    </form>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="saving || !nameValue.trim()" @click="submit">
        {{ saving ? strings.saving : store ? strings.save : strings.create }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { Store } from '@/api/types'
import {
  STORE_COLORS,
  STORE_ICONS,
  DEFAULT_STORE_ICON_KEY,
} from '@/components/StoreMultiPicker/storeIcons'

const props = defineProps<{
  open: boolean
  /** Existing store to edit, or null/undefined to create a new one. */
  store?: Store | null
  saving?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [data: { name: string; icon: string; color: string }]
}>()

const nameValue = ref('')
const iconValue = ref<string>(DEFAULT_STORE_ICON_KEY)
const colorValue = ref<string>(STORE_COLORS[3]!)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (props.store) {
        nameValue.value = props.store.name
        iconValue.value = props.store.icon
        colorValue.value = props.store.color
      } else {
        nameValue.value = ''
        iconValue.value = DEFAULT_STORE_ICON_KEY
        colorValue.value = STORE_COLORS[3]!
      }
    }
  },
  { immediate: true },
)

const dialogName = computed(() => (props.store ? strings.editTitle : strings.createTitle))

function submit() {
  const name = nameValue.value.trim()
  if (!name) return
  emit('save', { name, icon: iconValue.value, color: colorValue.value })
}

const strings = {
  // TRANSLATORS: Noun, a shop where items are bought. Dialog title.
  createTitle: t('pantry', 'New store'),
  // TRANSLATORS: Noun, a shop where items are bought. Dialog title.
  editTitle: t('pantry', 'Edit store'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Supermarket, Pharmacy'),
  iconLabel: t('pantry', 'Icon:'),
  colorLabel: t('pantry', 'Color:'),
  cancel: t('pantry', 'Cancel'),
  create: t('pantry', 'Create'),
  save: t('pantry', 'Save'),
  saving: t('pantry', 'Saving …'),
}
</script>

<style scoped lang="scss">
.pantry-store-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
  min-width: 340px;

  &__sub {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    margin-bottom: 0.35rem;
  }

  &__icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 0.35rem;
  }

  &__icon-button {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
      background: var(--color-background-hover);
    }

    &--active {
      border-color: currentColor;
      box-shadow: 0 0 0 2px currentColor;
    }
  }

  &__color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  &__color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease;

    &:hover {
      transform: scale(1.08);
    }

    &--active {
      border-color: var(--color-main-text);
      transform: scale(1.1);
    }
  }

  &__error {
    color: var(--color-error);
    margin: 0;
  }
}

@media (max-width: 500px) {
  .pantry-store-form {
    min-width: 0;
  }
}
</style>
