<template>
  <div class="pantry-category-picker">
    <label v-if="label" class="pantry-category-picker__label">{{ label }}</label>
    <NcSelect
      v-model="selected"
      :options="options"
      :clearable="true"
      :placeholder="placeholder ?? strings.placeholder"
      :input-label="''"
      label="label"
      @option:selected="onSelect"
    >
      <template #option="option">
        <div class="pantry-category-option">
          <template v-if="option.create">
            <PlusIcon
              :size="18"
              class="pantry-category-option__icon pantry-category-option__icon--create"
            />
            <span class="pantry-category-option__name">{{ option.label }}</span>
          </template>
          <template v-else-if="option.category">
            <span class="pantry-category-option__icon" :style="{ color: option.category.color }">
              <component :is="iconFor(option.category.icon)" :size="18" />
            </span>
            <span class="pantry-category-option__name">{{ option.category.name }}</span>
          </template>
          <span v-else>{{ option.label }}</span>
        </div>
      </template>

      <template #selected-option="option">
        <div class="pantry-category-option">
          <span
            v-if="option.category"
            class="pantry-category-option__icon"
            :style="{ color: option.category.color }"
          >
            <component :is="iconFor(option.category.icon)" :size="18" />
          </span>
          <span>{{ option.label }}</span>
        </div>
      </template>
    </NcSelect>

    <NcDialog
      v-if="showCreate"
      :name="strings.createTitle"
      :open="showCreate"
      @update:open="showCreate = $event"
    >
      <form class="pantry-create-cat" @submit.prevent="submitCreate">
        <NcTextField
          v-model="newName"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
        />

        <div>
          <label class="pantry-create-cat__sub">{{ strings.iconLabel }}</label>
          <div class="pantry-create-cat__icon-grid">
            <button
              v-for="opt in CATEGORY_ICONS"
              :key="opt.key"
              type="button"
              class="pantry-create-cat__icon-button"
              :class="{ 'pantry-create-cat__icon-button--active': newIcon === opt.key }"
              :title="opt.label"
              :style="{ color: newColor }"
              @click="newIcon = opt.key"
            >
              <component :is="opt.component" :size="20" />
            </button>
          </div>
        </div>

        <div>
          <label class="pantry-create-cat__sub">{{ strings.colorLabel }}</label>
          <div class="pantry-create-cat__color-grid">
            <button
              v-for="c in CATEGORY_COLORS"
              :key="c"
              type="button"
              class="pantry-create-cat__color-swatch"
              :class="{ 'pantry-create-cat__color-swatch--active': newColor === c }"
              :style="{ backgroundColor: c }"
              :aria-label="c"
              @click="newColor = c"
            />
          </div>
        </div>

        <p v-if="createError" class="pantry-create-cat__error">{{ createError }}</p>
      </form>
      <template #actions>
        <NcButton @click="showCreate = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" :disabled="saving || !newName.trim()" @click="submitCreate">
          {{ saving ? strings.saving : strings.create }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from '@icons/Plus.vue'
import { useCategories } from '@/composables/useCategories'
import {
  CATEGORY_COLORS,
  CATEGORY_ICONS,
  DEFAULT_CATEGORY_ICON_KEY,
  categoryIconComponent,
} from './categoryIcons'
import type { Category } from '@/api/types'

const props = defineProps<{
  houseId: number
  modelValue: number | null
  label?: string
  placeholder?: string
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: number | null): void
}>()

const { items, load, create } = useCategories(props.houseId)

interface SelectOption {
  label: string
  id?: number
  category?: Category
  create?: boolean
}

onMounted(() => {
  void load()
})

watch(
  () => props.houseId,
  () => {
    // different house's composable; trigger a load on the fresh one
    void useCategories(props.houseId).load()
  },
)

const options = computed<SelectOption[]>(() => {
  const categoryOptions: SelectOption[] = items.value.map((c) => ({
    label: c.name,
    id: c.id,
    category: c,
  }))
  return [...categoryOptions, { label: t('pantry', 'Create new category …'), create: true }]
})

const selected = computed<SelectOption | null>({
  get() {
    if (props.modelValue == null) return null
    const cat = items.value.find((c) => c.id === props.modelValue)
    if (!cat) return null
    return { label: cat.name, id: cat.id, category: cat }
  },
  set(v) {
    if (!v) {
      emit('update:modelValue', null)
      return
    }
    if (v.create) {
      // Handled in onSelect (v-model would flash the "create" label).
      return
    }
    emit('update:modelValue', v.id ?? null)
  },
})

function onSelect(opt: SelectOption | SelectOption[] | null): void {
  const picked = Array.isArray(opt) ? opt[0] : opt
  if (picked && picked.create) {
    openCreate()
  }
}

// ----- create dialog -----
const showCreate = ref(false)
const newName = ref('')
const newIcon = ref<string>(DEFAULT_CATEGORY_ICON_KEY)
const newColor = ref<string>(CATEGORY_COLORS[3]!)
const saving = ref(false)
const createError = ref<string | null>(null)

function openCreate() {
  // Reset the NcSelect so it doesn't stay on the "Create new …" ghost option.
  selected.value = selected.value?.category ? selected.value : null
  newName.value = ''
  newIcon.value = DEFAULT_CATEGORY_ICON_KEY
  newColor.value = CATEGORY_COLORS[3]!
  createError.value = null
  showCreate.value = true
}

async function submitCreate() {
  const name = newName.value.trim()
  if (!name) return
  saving.value = true
  createError.value = null
  try {
    const created = await create({
      name,
      icon: newIcon.value,
      color: newColor.value,
    })
    emit('update:modelValue', created.id)
    showCreate.value = false
  } catch (e) {
    createError.value = (e as Error).message || t('pantry', 'Could not create category.')
  } finally {
    saving.value = false
  }
}

function iconFor(key: string) {
  return categoryIconComponent(key)
}

const strings = {
  placeholder: t('pantry', 'Pick a category'),
  createTitle: t('pantry', 'New category'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Produce, Dairy'),
  iconLabel: t('pantry', 'Icon'),
  colorLabel: t('pantry', 'Color'),
  create: t('pantry', 'Create'),
  saving: t('pantry', 'Saving …'),
  cancel: t('pantry', 'Cancel'),
}
</script>

<style scoped lang="scss">
.pantry-category-picker {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }
}

.pantry-category-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  &__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &--create {
      color: var(--color-primary-element);
    }
  }

  &__name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.pantry-create-cat {
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
    margin: 0;
    color: var(--color-error);
  }
}

@media (max-width: 500px) {
  .pantry-create-cat {
    min-width: 0;
  }
}
</style>
