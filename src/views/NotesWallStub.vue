<template>
  <NcEmptyContent :name="strings.title" :description="strings.body">
    <template #icon>
      <NoteIcon />
    </template>
  </NcEmptyContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NoteIcon from '@icons/Note.vue'

const strings = {
  title: t('pantry', 'Notes wall'),
  body: t('pantry', 'A shared space for household notes and reminders is coming soon.'),
}
</script>
