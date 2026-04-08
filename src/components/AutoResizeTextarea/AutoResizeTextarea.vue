<template>
  <NcTextArea
    ref="textareaRef"
    v-model="model"
    v-bind="$attrs"
    resize="none"
    :rows="rows"
    class="auto-resize-textarea"
  />
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

const props = withDefaults(
  defineProps<{
    maxHeight?: number
    rows?: number
  }>(),
  {
    maxHeight: 400,
    rows: 1,
  },
)

const model = defineModel<string>({ default: '' })

const textareaRef = ref<InstanceType<typeof NcTextArea> | null>(null)

function getTextareaEl(): HTMLTextAreaElement | null {
  const el = textareaRef.value?.$el as HTMLElement | undefined
  return el?.querySelector('textarea') ?? null
}

function resize() {
  const ta = getTextareaEl()
  if (!ta) return
  ta.style.height = 'auto'
  ta.style.height = Math.min(ta.scrollHeight, props.maxHeight) + 'px'
  ta.style.overflowY = ta.scrollHeight > props.maxHeight ? 'auto' : 'hidden'
}

watch(model, () => {
  nextTick(resize)
})

onMounted(() => {
  nextTick(resize)
})

defineExpose({ resize, getTextareaEl })
</script>
