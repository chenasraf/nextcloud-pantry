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
            @click="iconValue = opt.key"
          >
            <component :is="opt.component" :size="20" />
          </button>
        </div>
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
import type { Checklist } from '@/api/types'
import { CHECKLIST_ICONS, DEFAULT_CHECKLIST_ICON_KEY } from './checklistIcons'

const props = defineProps<{
  open: boolean
  list?: Checklist | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [data: { name: string; description: string; icon: string }]
}>()

const formId = 'pantry-checklist-form-dialog'
const nameValue = ref('')
const descriptionValue = ref('')
const iconValue = ref(DEFAULT_CHECKLIST_ICON_KEY)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (props.list) {
        nameValue.value = props.list.name
        descriptionValue.value = props.list.description ?? ''
        iconValue.value = props.list.icon ?? DEFAULT_CHECKLIST_ICON_KEY
      } else {
        nameValue.value = ''
        descriptionValue.value = ''
        iconValue.value = DEFAULT_CHECKLIST_ICON_KEY
      }
    }
  },
  { immediate: true },
)

const dialogName = computed(() => (props.list ? strings.editTitle : strings.createTitle))

function submit() {
  const name = nameValue.value.trim()
  if (!name) return
  emit('save', { name, description: descriptionValue.value.trim(), icon: iconValue.value })
}

const strings = {
  createTitle: t('pantry', 'Create a checklist'),
  editTitle: t('pantry', 'Edit checklist'),
  nameLabel: t('pantry', 'Name'),
  namePlaceholder: t('pantry', 'e.g. Weekly groceries'),
  descriptionLabel: t('pantry', 'Description (optional)'),
  descriptionPlaceholder: t('pantry', 'A short description'),
  iconLabel: t('pantry', 'Icon:'),
  cancel: t('pantry', 'Cancel'),
  create: t('pantry', 'Create'),
  save: t('pantry', 'Save'),
}
</script>
