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
        :placeholder="strings.placeholder"
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
    <p class="quantity-input__hint">{{ strings.hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PlusIcon from '@icons/Plus.vue'
import MinusIcon from '@icons/Minus.vue'

const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const text = computed({
  get: () => props.modelValue,
  set: (v: string) => emit('update:modelValue', v),
})

const FIRST_NUMBER_RE = /\d+(?:\.\d+)?/

function firstNumber(s: string): number | null {
  const m = s.match(FIRST_NUMBER_RE)
  if (!m) return null
  const n = Number(m[0])
  return Number.isFinite(n) ? n : null
}

function replaceFirstNumber(s: string, value: number): string {
  return s.replace(FIRST_NUMBER_RE, String(value))
}

const canDecrement = computed(() => {
  const n = firstNumber(text.value)
  return n !== null && n > 1
})

function increment() {
  const n = firstNumber(text.value)
  if (n === null) {
    text.value = '1'
  } else {
    text.value = replaceFirstNumber(text.value, n + 1)
  }
}

function decrement() {
  const n = firstNumber(text.value)
  if (n === null || n <= 1) return
  text.value = replaceFirstNumber(text.value, n - 1)
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
