<template>
  <NcDialog
    :name="photo.caption ?? strings.preview"
    :open="open"
    close-on-click-outside
    size="large"
    @update:open="$emit('update:open', $event)"
  >
    <div class="photo-preview">
      <img :src="largeUrl(photo.id)" :alt="photo.caption ?? ''" class="photo-preview__img" />
    </div>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.close }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { photoPreviewUrl } from '@/api/images'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import type { Photo } from '@/api/types'

const props = defineProps<{
  open: boolean
  photo: Photo
  houseId: number
}>()

defineEmits<{
  'update:open': [value: boolean]
}>()

function largeUrl(photoId: number): string {
  return photoPreviewUrl(props.houseId, photoId, 1600)
}

const strings = {
  preview: t('pantry', 'Photo preview'),
  close: t('pantry', 'Close'),
}
</script>

<style scoped lang="scss">
.photo-preview {
  display: flex;
  align-items: center;
  justify-content: center;

  &__img {
    max-width: 100%;
    max-height: 70vh;
    border-radius: var(--border-radius-large, 12px);
  }
}
</style>
