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
      <fieldset class="pantry-duplicate-form__kinds">
        <legend>{{ strings.kindLabel }}</legend>
        <NcCheckboxRadioSwitch
          v-for="k in LIST_KINDS"
          :key="k"
          v-model="targetKind"
          :value="k"
          :name="kindFieldName"
          type="radio"
        >
          {{ listKindStrings(k).name }}
        </NcCheckboxRadioSwitch>
      </fieldset>
      <NcCheckboxRadioSwitch
        v-if="kind === 'checklist' && targetKind === 'checklist'"
        v-model="resetDone"
      >
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
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import type { Checklist, ListKind } from '@/api/types'
import { LIST_KINDS, listKindStrings } from '@/composables/useListKind'

const props = defineProps<{
  open: boolean
  list: Checklist | null
  /** The list's kind; falls back to the list's own. */
  kind?: ListKind
  submitting?: boolean
}>()

const kind = computed<ListKind>(() => props.kind ?? props.list?.kind ?? 'checklist')

const emit = defineEmits<{
  'update:open': [value: boolean]
  duplicate: [data: { name: string; resetDone: boolean; kind: ListKind }]
}>()

const formId = 'pantry-checklist-duplicate-dialog'
const kindFieldName = 'pantry-duplicate-kind'
const nameValue = ref('')
const resetDone = ref(true)
const targetKind = ref<ListKind>(kind.value)

watch(
  () => [props.open, props.list?.id] as const,
  ([isOpen]) => {
    if (!isOpen) return
    nameValue.value = props.list
      ? // TRANSLATORS: Default name offered for a copy of a list. {name} is the original list's name.
        t('pantry', 'Duplicate of {name}', { name: props.list.name })
      : ''
    resetDone.value = true
    targetKind.value = kind.value
  },
  { immediate: true },
)

function submit() {
  const name = nameValue.value.trim()
  if (!name || props.submitting) return
  emit('duplicate', { name, resetDone: resetDone.value, kind: targetKind.value })
}

const strings = computed(() => ({
  title: listKindStrings(kind.value).duplicateTitle,
  nameLabel: t('pantry', 'Name'),
  // TRANSLATORS: Heading above the choice of list kind (checklist, inventory, template) the copy becomes.
  kindLabel: t('pantry', 'Duplicate as:'),
  resetDoneLabel: t('pantry', 'Set all items to undone'),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, dialog button that creates the copy of the list.
  duplicate: t('pantry', 'Duplicate'),
}))
</script>

<style scoped lang="scss">
.pantry-duplicate-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.5rem 0;

  &__kinds {
    display: flex;
    flex-wrap: wrap;
    gap: 0 1rem;
    border: 0;
    margin: 0;
    padding: 0;

    legend {
      width: 100%;
      margin-bottom: 0.25rem;
      font-weight: 600;
    }
  }
}
</style>
