<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form :id="formId" class="pantry-duplicate-form" autocomplete="off" @submit.prevent="submit">
      <NcTextField v-model="nameValue" :label="strings.nameLabel" autocomplete="off" />
      <NcCheckboxRadioSwitch v-model="resetDone">
        {{ strings.resetDoneLabel }}
      </NcCheckboxRadioSwitch>
    </form>
    <template #actions>
      <NcButton :disabled="submitting" @click="$emit('update:open', false)">
        {{ strings.cancel }}
      </NcButton>
      <NcButton
        :form="formId"
        type="submit"
        variant="primary"
        :disabled="submitting || !nameValue.trim()"
      >
        <template v-if="submitting" #icon><NcLoadingIcon :size="20" /></template>
        {{ strings.duplicate }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import type { Checklist } from '@/api/types'

const props = defineProps<{
  open: boolean
  list: Checklist | null
  submitting?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  duplicate: [data: { name: string; resetDone: boolean }]
}>()

const formId = 'pantry-checklist-duplicate-dialog'
const nameValue = ref('')
const resetDone = ref(true)

watch(
  () => [props.open, props.list?.id] as const,
  ([isOpen]) => {
    if (!isOpen) return
    nameValue.value = props.list
      ? // TRANSLATORS: Default name offered for a copy of a list. {name} is the original list's name.
        t('pantry', 'Duplicate of {name}', { name: props.list.name })
      : ''
    resetDone.value = true
  },
  { immediate: true },
)

function submit() {
  const name = nameValue.value.trim()
  if (!name || props.submitting) return
  emit('duplicate', { name, resetDone: resetDone.value })
}

const strings = {
  title: t('pantry', 'Duplicate list'),
  nameLabel: t('pantry', 'Name'),
  resetDoneLabel: t('pantry', 'Set all items to undone'),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, dialog button that creates the copy of the list.
  duplicate: t('pantry', 'Duplicate'),
}
</script>

<style scoped lang="scss">
.pantry-duplicate-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.5rem 0;
}
</style>
