<template>
  <form class="checklist-add" autocomplete="off" @submit.prevent="submitAdd">
    <NcTextField
      v-model="name"
      :label="strings.nameLabel"
      :placeholder="strings.namePlaceholder"
      autocomplete="off"
    />
    <NcTextField
      v-model="quantity"
      :label="strings.quantityLabel"
      :placeholder="strings.quantityPlaceholder"
      autocomplete="off"
    />
    <CategoryPicker
      v-model="categoryId"
      :house-id="houseId"
      :placeholder="strings.categoryPlaceholder"
    />
    <div class="checklist-add__once" :title="strings.onceHint">
      <NcCheckboxRadioSwitch v-model="deleteOnDone">
        {{ strings.once }}
      </NcCheckboxRadioSwitch>
    </div>
    <NcButton v-if="!deleteOnDone" variant="tertiary" @click="showRecurrenceEditor = true">
      <template #icon>
        <RepeatIcon :size="20" />
      </template>
      {{ rrule ? strings.recurrenceSet : strings.recurrenceButton }}
    </NcButton>
    <NcButton
      variant="tertiary"
      :aria-label="strings.descriptionToggle"
      @click="showDescription = !showDescription"
    >
      <template #icon>
        <ChevronDownIcon
          :size="20"
          class="checklist-add__chevron"
          :class="{ 'checklist-add__chevron--open': showDescription }"
        />
      </template>
    </NcButton>
    <NcButton type="submit" variant="primary" :disabled="!name.trim() || adding">
      <template #icon>
        <PlusIcon :size="20" />
      </template>
      {{ strings.add }}
    </NcButton>
    <AutoResizeTextarea
      v-if="showDescription"
      v-model="description"
      :label="strings.descriptionLabel"
      :placeholder="strings.descriptionPlaceholder"
      class="checklist-add__description"
      autocomplete="off"
    />

    <RecurrenceEditor
      v-model:open="showRecurrenceEditor"
      v-model="rrule"
      v-model:from-completion="repeatFromCompletion"
    />
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import RepeatIcon from '@icons/Repeat.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import RecurrenceEditor from '@/components/RecurrenceEditor'
import CategoryPicker from '@/components/CategoryPicker'
import type { ItemInput } from '@/api/lists'

defineProps<{
  houseId: number
  adding: boolean
}>()

const emit = defineEmits<{
  add: [input: ItemInput]
}>()

const name = ref('')
const description = ref('')
const quantity = ref('')
const categoryId = ref<number | null>(null)
const rrule = ref<string | null>(null)
const repeatFromCompletion = ref(false)
const deleteOnDone = ref(false)
const showDescription = ref(false)
const showRecurrenceEditor = ref(false)

function submitAdd() {
  const trimmedName = name.value.trim()
  if (!trimmedName) return
  // "Once" items can't recur — ignore any locally-retained recurrence settings.
  const once = deleteOnDone.value
  emit('add', {
    name: trimmedName,
    description: description.value.trim() || null,
    quantity: quantity.value.trim() || null,
    categoryId: categoryId.value,
    rrule: once ? null : rrule.value,
    repeatFromCompletion: once ? false : repeatFromCompletion.value,
    deleteOnDone: once,
  })
  name.value = ''
  description.value = ''
  quantity.value = ''
  categoryId.value = null
  rrule.value = null
  repeatFromCompletion.value = false
  deleteOnDone.value = false
  showDescription.value = false
}

const strings = {
  add: t('pantry', 'Add'),
  nameLabel: t('pantry', 'Item name'),
  namePlaceholder: t('pantry', 'e.g. Milk'),
  quantityLabel: t('pantry', 'Quantity'),
  quantityPlaceholder: t('pantry', 'e.g. 2 L'),
  categoryPlaceholder: t('pantry', 'Category'),
  recurrenceButton: t('pantry', 'Repeat …'),
  recurrenceSet: t('pantry', 'Repeat: set'),
  once: t('pantry', 'Once'),
  onceHint: t('pantry', 'Delete this item once it is marked as done.'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'Add a description …'),
  descriptionToggle: t('pantry', 'Toggle description'),
}
</script>

<style scoped lang="scss">
.checklist-add {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto auto auto auto;
  gap: 0.75rem;
  align-items: end;
  margin-bottom: 1.5rem;

  :deep(.v-select.select) {
    margin-bottom: 0;
  }

  @media (max-width: 900px) {
    grid-template-columns: 1fr 1fr;
  }

  &__description {
    grid-column: 1 / -1;
  }

  &__once {
    padding-bottom: 0.25rem;
  }

  &__chevron {
    transition: transform 0.2s ease;

    &--open {
      transform: rotate(180deg);
    }
  }
}
</style>
