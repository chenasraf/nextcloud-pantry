<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    close-on-click-outside
    size="normal"
    @update:open="$emit('update:open', $event)"
  >
    <div class="recurrence-editor">
      <RecurrenceForm
        v-if="open"
        :model-value="localRrule"
        :from-completion="localFromCompletion"
        @update:model-value="localRrule = $event"
        @update:from-completion="localFromCompletion = $event"
      />
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton v-if="hasExisting" variant="tertiary" @click="clear">
        {{ strings.clearButton }}
      </NcButton>
      <NcButton variant="primary" @click="save">{{ strings.save }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import RecurrenceForm from './RecurrenceForm.vue'

const props = defineProps<{
  open: boolean
  modelValue: string | null
  fromCompletion?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:modelValue': [value: string | null]
  'update:fromCompletion': [value: boolean]
}>()

// Local copy so edits aren't propagated until the user saves.
const localRrule = ref<string | null>(props.modelValue)
const localFromCompletion = ref<boolean>(!!props.fromCompletion)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      localRrule.value = props.modelValue
      localFromCompletion.value = !!props.fromCompletion
    }
  },
)

const hasExisting = computed(() => !!props.modelValue)

function save() {
  emit('update:modelValue', localRrule.value)
  emit('update:fromCompletion', localFromCompletion.value)
  emit('update:open', false)
}

function clear() {
  emit('update:modelValue', null)
  emit('update:fromCompletion', false)
  emit('update:open', false)
}

const strings = {
  title: t('pantry', 'Recurrence'),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  clearButton: t('pantry', 'Remove recurrence'),
}
</script>

<style scoped lang="scss">
.recurrence-editor {
  min-width: 420px;

  @media (max-width: 600px) {
    min-width: 0;
  }
}
</style>
