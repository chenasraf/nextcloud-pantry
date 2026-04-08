<template>
  <NcDialog
    :name="item.name"
    :open="open"
    close-on-click-outside
    size="large"
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <div class="image-preview">
      <img v-if="item.imageFileId" :src="largeUrl" :alt="item.name" />
    </div>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { itemImagePreviewUrl } from '@/api/images'
import type { ChecklistItem } from '@/api/types'

const props = defineProps<{
  open: boolean
  item: ChecklistItem
  houseId: number
}>()

defineEmits<{
  'update:open': [value: boolean]
}>()

const largeUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 1600)
    : '',
)
</script>

<style scoped lang="scss">
.image-preview {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0.5rem;

  img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: var(--border-radius, 8px);
  }
}
</style>
