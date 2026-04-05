<template>
  <NcEmptyContent :name="strings.title" :description="strings.body">
    <template #icon>
      <ImageIcon />
    </template>
  </NcEmptyContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ImageIcon from '@icons/Image.vue'

const strings = {
  title: t('pantry', 'Photo board'),
  body: t(
    'pantry',
    'Shared reference photos are coming soon. You will be able to upload and categorize pictures for the whole household.',
  ),
}
</script>
