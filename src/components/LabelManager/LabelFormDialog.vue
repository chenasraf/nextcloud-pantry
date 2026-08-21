<template>
  <NcDialog
    :name="dialogName"
    :open="open"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form class="pantry-label-form" autocomplete="off" @submit.prevent="submit">
      <NcTextField
        v-model="nameValue"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <div>
        <label class="pantry-label-form__sub">{{ strings.listLabel }}</label>
        <NcSelect
          v-model="listSelection"
          :options="listOptions"
          :clearable="false"
          :input-label="''"
          label="label"
          :aria-label-combobox="strings.listLabel"
          :calculate-position="ncSelectCalculatePosition"
        />
        <p class="pantry-label-form__hint">{{ strings.listHint }}</p>
      </div>
      <div>
        <label class="pantry-label-form__sub">{{ strings.iconLabel }}</label>
        <div class="pantry-label-form__icon-grid">
          <button
            v-for="opt in LABEL_ICONS"
            :key="opt.key"
            type="button"
            class="pantry-label-form__icon-button"
            :class="{ 'pantry-label-form__icon-button--active': iconValue === opt.key }"
            :title="opt.label"
            :style="{ color: colorValue }"
            @click="iconValue = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
      </div>
      <div>
        <label class="pantry-label-form__sub">{{ strings.colorLabel }}</label>
        <div class="pantry-label-form__color-grid">
          <button
            v-for="c in LABEL_COLORS"
            :key="c"
            type="button"
            class="pantry-label-form__color-swatch"
            :class="{ 'pantry-label-form__color-swatch--active': colorValue === c }"
            :style="{ backgroundColor: c }"
            :aria-label="c"
            @click="colorValue = c"
          />
        </div>
      </div>
      <p v-if="error" class="pantry-label-form__error">{{ error }}</p>
    </form>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="saving || !nameValue.trim()" @click="submit">
        {{ saving ? strings.saving : label ? strings.save : strings.create }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { Label } from '@/api/types'
import { useChecklists } from '@/composables/useChecklist'
import { ncSelectCalculatePosition } from '@/utils/ncSelectPosition'
import {
  LABEL_COLORS,
  LABEL_ICONS,
  DEFAULT_LABEL_ICON_KEY,
} from '@/components/LabelPicker/labelIcons'

const props = defineProps<{
  open: boolean
  houseId: number
  /** Existing label to edit, or null/undefined to create a new one. */
  label?: Label | null
  /** Default list scope for a new label (e.g. the list currently in context). */
  defaultListId?: number | null
  saving?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [data: { name: string; icon: string; color: string; listId: number | null }]
}>()

// Declared before the list-scope refs below because a ref() initializer and the
// immediate watch read `strings` synchronously during setup.
const strings = {
  createTitle: t('pantry', 'New label'),
  editTitle: t('pantry', 'Edit label'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Urgent, On sale'),
  iconLabel: t('pantry', 'Icon:'),
  colorLabel: t('pantry', 'Color:'),
  listLabel: t('pantry', 'List:'),
  // TRANSLATORS: Option in a dropdown; a global label is offered on every list
  globalOption: t('pantry', 'All lists (global)'),
  listHint: t('pantry', 'Choose a list to show this label only there, or keep it global.'),
  cancel: t('pantry', 'Cancel'),
  create: t('pantry', 'Create'),
  save: t('pantry', 'Save'),
  saving: t('pantry', 'Saving …'),
}

const nameValue = ref('')
const iconValue = ref<string>(DEFAULT_LABEL_ICON_KEY)
const colorValue = ref<string>(LABEL_COLORS[0]!)

// ----- List scope -----
interface ListOption {
  label: string
  listId: number | null
}
const { lists, load: loadLists } = useChecklists(props.houseId)

onMounted(() => {
  void loadLists()
})

const globalOption = computed<ListOption>(() => ({ label: strings.globalOption, listId: null }))
const listOptions = computed<ListOption[]>(() => [
  globalOption.value,
  ...lists.value.map((l) => ({ label: l.name, listId: l.id })),
])
const listSelection = ref<ListOption>(globalOption.value)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (props.label) {
        nameValue.value = props.label.name
        iconValue.value = props.label.icon
        colorValue.value = props.label.color
        listSelection.value = optionForListId(props.label.listId)
      } else {
        nameValue.value = ''
        iconValue.value = DEFAULT_LABEL_ICON_KEY
        colorValue.value = LABEL_COLORS[0]!
        listSelection.value = optionForListId(props.defaultListId ?? null)
      }
    }
  },
  { immediate: true },
)

function optionForListId(listId: number | null): ListOption {
  if (listId == null) return globalOption.value
  const list = lists.value.find((l) => l.id === listId)
  return { label: list?.name ?? String(listId), listId }
}

const dialogName = computed(() => (props.label ? strings.editTitle : strings.createTitle))

function submit() {
  const name = nameValue.value.trim()
  if (!name) return
  emit('save', {
    name,
    icon: iconValue.value,
    color: colorValue.value,
    listId: listSelection.value?.listId ?? null,
  })
}
</script>

<style scoped lang="scss">
.pantry-label-form {
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

  &__hint {
    font-size: 0.8rem;
    color: var(--color-text-maxcontrast);
    margin: 0.35rem 0 0 0;
  }

  &__error {
    color: var(--color-error);
    margin: 0;
  }
}

@media (max-width: 500px) {
  .pantry-label-form {
    min-width: 0;
  }
}
</style>
