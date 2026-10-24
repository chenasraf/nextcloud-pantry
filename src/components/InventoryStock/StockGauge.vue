<template>
  <div class="stock-gauge" :class="`stock-gauge--${tone}`" aria-hidden="true">
    <template v-if="segments">
      <span v-for="n in 3" :key="n" class="stock-gauge__segment">
        <span
          class="stock-gauge__fill"
          :style="{ width: n <= filledSegments ? '100%' : '0%', transitionDelay: segmentDelay(n) }"
        />
      </span>
    </template>
    <span v-else class="stock-gauge__track">
      <span class="stock-gauge__fill" :style="{ width: `${fill * 100}%` }" />
      <span v-if="tick !== null" class="stock-gauge__tick" :style="{ left: `${tick * 100}%` }" />
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { StockTone } from '@/utils/stock'

const props = withDefaults(
  defineProps<{
    /** 0 to 1. */
    fill: number
    tone: StockTone
    /** Where the reorder point sits, 0 to 1. */
    tick?: number | null
    /** Draw three segments (a level item) instead of a bar. */
    segments?: boolean
  }>(),
  { tick: null, segments: false },
)

const filledSegments = computed(() => Math.round(props.fill * 3))

/**
 * Segments fill left to right and empty right to left, so a level change
 * sweeps across the gauge instead of every segment switching at once.
 */
const previousFilled = ref(filledSegments.value)
const rising = ref(true)
watch(filledSegments, (next, prev) => {
  rising.value = next > prev
  previousFilled.value = prev
})

function segmentDelay(n: number): string {
  const order = rising.value ? n - previousFilled.value - 1 : previousFilled.value - n
  return `${Math.max(0, order) * SEGMENT_STAGGER_MS}ms`
}

const SEGMENT_STAGGER_MS = 120
</script>

<style scoped lang="scss">
.stock-gauge {
  --gauge-color: var(--color-element-success, var(--color-success));
  display: flex;
  gap: 2px;
  width: min(100%, 12rem);
  height: 4px;
  margin-top: 0.25rem;

  &--warning {
    --gauge-color: var(--color-element-warning, var(--color-warning));
  }

  &--error {
    --gauge-color: var(--color-element-error, var(--color-error));
  }

  &__track {
    position: relative;
    flex: 1;
    border-radius: 2px;
    background: var(--color-background-darker);
  }

  &__fill {
    position: absolute;
    inset: 0 auto 0 0;
    border-radius: 2px;
    background: var(--gauge-color);
    transition:
      width 0.35s ease-out,
      background-color 0.35s ease-out;
  }

  &__tick {
    position: absolute;
    top: -2px;
    bottom: -2px;
    width: 2px;
    margin-left: -1px;
    background: var(--color-main-text);
    transition: left 0.35s ease-out;
  }

  &__segment {
    position: relative;
    flex: 1;
    border-radius: 2px;
    background: var(--color-background-darker);
    overflow: hidden;

    .stock-gauge__fill {
      transition-duration: 0.15s;
    }
  }
}
</style>
