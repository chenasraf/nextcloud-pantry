<template>
  <NcDialog
    :name="photo.caption ?? strings.preview"
    :open="open"
    size="large"
    @update:open="$emit('update:open', $event)"
  >
    <div class="photo-preview">
      <img :src="largeUrl(photo.fileId)" :alt="photo.caption ?? ''" class="photo-preview__img" />
    </div>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.close }}</NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import type { Photo } from '@/api/types'

defineProps<{
  open: boolean
  photo: Photo
}>()

defineEmits<{
  'update:open': [value: boolean]
}>()

function largeUrl(fileId: number): string {
  const base = generateUrl('/core/preview')
  return `${base}?fileId=${fileId}&x=1600&y=1600&a=1`
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
