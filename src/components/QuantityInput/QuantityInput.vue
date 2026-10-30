<template>
  <div class="quantity-input">
    <div class="quantity-input__row">
      <NcButton
        variant="tertiary"
        :aria-label="strings.decrement"
        :title="strings.decrement"
        :disabled="!canDecrement"
        @click="decrement"
      >
        <template #icon>
          <MinusIcon :size="20" />
        </template>
      </NcButton>
      <NcTextField
        v-model="text"
        class="quantity-input__field"
        :placeholder="placeholder ?? strings.placeholder"
        :label="label"
        :label-outside="!!label"
        :error="error"
        :show-trailing-button="text.length > 0"
        trailing-button-icon="close"
        :trailing-button-label="strings.clear"
        autocomplete="off"
        @trailing-button-click="text = ''"
      />
      <NcButton
        variant="tertiary"
        :aria-label="strings.increment"
        :title="strings.increment"
        @click="increment"
      >
        <template #icon>
          <PlusIcon :size="20" />
        </template>
      </NcButton>
    </div>
    <p v-if="showHint" class="quantity-input__hint">{{ strings.hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import MinusIcon from '@icons/Minus.vue'
import { canStepDown, stepQuantity } from '@/utils/quantity'

const props = withDefaults(
  defineProps<{
    modelValue: string
    /** The lowest number − steps down to: 1 for an amount to get, 0 for what is on hand. */
    min?: number
    label?: string
    placeholder?: string
    showHint?: boolean
    error?: boolean
  }>(),
  { min: 1, label: undefined, placeholder: undefined, showHint: true, error: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const text = computed({
  get: () => props.modelValue,
  set: (v: string) => emit('update:modelValue', v),
})

const canDecrement = computed(() => canStepDown(text.value, props.min))

function increment() {
  text.value = stepQuantity(text.value, 1, props.min)
}

function decrement() {
  if (!canDecrement.value) return
  text.value = stepQuantity(text.value, -1, props.min)
}

const strings = {
  placeholder: t('pantry', 'e.g. 2 L, 500 g'),
  decrement: t('pantry', 'Decrease quantity'),
  increment: t('pantry', 'Increase quantity'),
  clear: t('pantry', 'Clear quantity'),
  hint: t('pantry', '+ / − change the number and keep the unit.'),
}
</script>

<style scoped lang="scss">
.quantity-input {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;

  &__row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  &__field {
    flex: 1;
    min-width: 0;
  }

  &__hint {
    margin: 0;
    font-size: 0.8rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
