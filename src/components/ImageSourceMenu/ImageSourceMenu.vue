<template>
  <NcActions :menu-name="label" :aria-label="label" :variant="variant">
    <template #icon>
      <slot name="icon">
        <UploadIcon :size="20" />
      </slot>
    </template>
    <NcActionButton close-after-click @click="emit('computer')">
      <template #icon>
        <MonitorIcon :size="20" />
      </template>
      {{ strings.fromComputer }}
    </NcActionButton>
    <NcActionButton close-after-click @click="emit('files')">
      <template #icon>
        <FolderImageIcon :size="20" />
      </template>
      {{ strings.fromFiles }}
    </NcActionButton>
  </NcActions>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import UploadIcon from '@icons/Upload.vue'
import MonitorIcon from '@icons/Monitor.vue'
import FolderImageIcon from '@icons/FolderImage.vue'

withDefaults(
  defineProps<{
    label: string
    variant?: 'primary' | 'secondary' | 'tertiary'
  }>(),
  { variant: 'tertiary' },
)

const emit = defineEmits<{
  computer: []
  files: []
}>()

const strings = {
  fromComputer: t('pantry', 'From this device'),
  fromFiles: t('pantry', 'From Nextcloud files'),
}
</script>
