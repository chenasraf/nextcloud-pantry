<template>
  <NcDialog
    :name="dialogName"
    :open="open"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form :id="formId" class="pantry-form" autocomplete="off" @submit.prevent="submit">
      <NcTextField
        v-model="nameValue"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <NcTextField
        v-model="descriptionValue"
        :label="strings.descriptionLabel"
        :placeholder="strings.descriptionPlaceholder"
        autocomplete="off"
      />
      <div>
        <label class="pantry-icon-picker__label">{{ strings.iconLabel }}</label>
        <div class="pantry-icon-picker__grid">
          <button
            v-for="opt in CHECKLIST_ICONS"
            :key="opt.key"
            type="button"
            class="pantry-icon-picker__button"
            :class="{ 'pantry-icon-picker__button--active': iconValue === opt.key }"
            :title="opt.label"
            :style="iconValue === opt.key && colorValue ? activeIconStyle : undefined"
            @click="iconValue = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
      </div>
      <div>
        <label class="pantry-icon-picker__label">{{ strings.colorLabel }}</label>
        <div class="pantry-color-picker__grid">
          <button
            type="button"
            class="pantry-color-picker__swatch pantry-color-picker__swatch--none"
            :class="{ 'pantry-color-picker__swatch--active': !colorValue }"
            :title="strings.noColor"
            @click="colorValue = ''"
          >
            <CloseIcon :size="14" />
          </button>
          <button
            v-for="c in checklistColorOptions"
            :key="c"
            type="button"
            class="pantry-color-picker__swatch"
            :class="{ 'pantry-color-picker__swatch--active': colorValue === c }"
            :style="{ background: c }"
            :title="c"
            @click="colorValue = colorValue === c ? '' : c"
          />
        </div>
      </div>
      <div v-if="showAccess">
        <label class="pantry-icon-picker__label">{{ strings.accessLabel }}</label>
        <NcSelect
          :model-value="selectedAccessRoles"
          :options="accessRoleOptions"
          :multiple="true"
          :close-on-select="false"
          :placeholder="strings.accessEveryone"
          label="label"
          autocomplete="off"
          @update:model-value="(opts: AccessOption[]) => changeAccess(opts)"
        />
        <p class="pantry-access-hint">{{ strings.accessHint }}</p>
      </div>
    </form>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton :form="formId" type="submit" variant="primary" :disabled="!nameValue.trim()">
        {{ list ? strings.save : strings.create }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import CloseIcon from '@icons/Close.vue'
import type { Checklist } from '@/api/types'
import { CHECKLIST_ICONS, DEFAULT_CHECKLIST_ICON_KEY } from './checklistIcons'
import { checklistColorOptions, contrastColor } from './checklistColors'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useRoles } from '@/composables/useRoles'
import { getListRoles, setListRoles } from '@/api/roles'

const props = defineProps<{
  open: boolean
  list?: Checklist | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [data: { name: string; description: string; icon: string; color: string }]
}>()

const formId = 'pantry-checklist-form-dialog'
const nameValue = ref('')
const descriptionValue = ref('')
const iconValue = ref(DEFAULT_CHECKLIST_ICON_KEY)
const colorValue = ref('')

// -------- Access (admin-only, edit mode) --------
const { isAdmin, houseId } = useCurrentHouse()
const showAccess = computed(() => !!props.list && isAdmin.value)
interface AccessOption {
  id: number
  label: string
}
// Resolve the roles composable only when the house id changes — calling
// useRoles() triggers a fetch, so it must NOT live inside the options getter
// (that would re-fetch on every reactive read and loop endlessly).
const rolesApi = computed(() => (houseId.value !== null ? useRoles(houseId.value) : null))
const accessRoleOptions = computed<AccessOption[]>(
  () => rolesApi.value?.roles.value.map((r) => ({ id: r.id, label: r.name })) ?? [],
)
const selectedAccessRoles = ref<AccessOption[]>([])

async function loadListAccess() {
  if (!props.list) {
    selectedAccessRoles.value = []
    return
  }
  const ids = await getListRoles(props.list.houseId, props.list.id)
  selectedAccessRoles.value = accessRoleOptions.value.filter((o) => ids.includes(o.id))
}

async function changeAccess(options: AccessOption[]) {
  if (!props.list) return
  selectedAccessRoles.value = options
  await setListRoles(
    props.list.houseId,
    props.list.id,
    options.map((o) => o.id),
  )
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (props.list) {
        nameValue.value = props.list.name
        descriptionValue.value = props.list.description ?? ''
        iconValue.value = props.list.icon ?? DEFAULT_CHECKLIST_ICON_KEY
        colorValue.value = props.list.color ?? ''
        if (isAdmin.value) void loadListAccess()
      } else {
        nameValue.value = ''
        descriptionValue.value = ''
        iconValue.value = DEFAULT_CHECKLIST_ICON_KEY
        colorValue.value = ''
      }
    }
  },
  { immediate: true },
)

const dialogName = computed(() => (props.list ? strings.editTitle : strings.createTitle))

const activeIconStyle = computed(() =>
  colorValue.value
    ? { background: colorValue.value, color: contrastColor(colorValue.value) }
    : undefined,
)

function submit() {
  const name = nameValue.value.trim()
  if (!name) return
  emit('save', {
    name,
    description: descriptionValue.value.trim(),
    icon: iconValue.value,
    color: colorValue.value,
  })
}

const strings = {
  createTitle: t('pantry', 'Create a checklist'),
  editTitle: t('pantry', 'Edit checklist'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Weekly groceries'),
  descriptionLabel: t('pantry', 'Description (optional)'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  iconLabel: t('pantry', 'Icon:'),
  colorLabel: t('pantry', 'Color:'),
  noColor: t('pantry', 'Default (no color)'),
  accessLabel: t('pantry', 'Access:'),
  accessEveryone: t('pantry', 'Everyone'),
  accessHint: t(
    'pantry',
    'Leave empty to let every role access this list. Select roles to restrict access to their members (admins always have access).',
  ),
  cancel: t('pantry', 'Cancel'),
  create: t('pantry', 'Create'),
  save: t('pantry', 'Save'),
}
</script>

<style scoped lang="scss">
.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}

.pantry-access-hint {
  margin: 0.35rem 0 0;
  font-size: 0.85em;
  color: var(--color-text-maxcontrast);
}

.pantry-icon-picker {
  &__label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.35rem;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 0.35rem;
  }

  &__button {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid transparent;
    border-radius: var(--border-radius, 4px);
    background: var(--color-background-dark);
    color: var(--color-main-text);
    cursor: pointer;
    transition:
      border-color 0.15s,
      background 0.15s;

    &:hover {
      background: var(--color-background-hover);
    }

    &--active {
      border-color: var(--color-primary-element);
      background: var(--color-primary-element-light);
    }
  }
}

.pantry-color-picker {
  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(28px, 1fr));
    gap: 0.4rem;
  }

  &__swatch {
    aspect-ratio: 1;
    border: 2px solid transparent;
    border-radius: 50%;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.1s ease;

    &:hover {
      transform: scale(1.1);
    }

    &--active {
      border-color: var(--color-main-text);
      box-shadow: 0 0 0 2px var(--color-main-background);
    }

    &--none {
      background: var(--color-background-dark);
      color: var(--color-text-maxcontrast);
      border: 1px dashed var(--color-border);

      &.pantry-color-picker__swatch--active {
        border-style: solid;
        border-color: var(--color-main-text);
      }
    }
  }
}
</style>
