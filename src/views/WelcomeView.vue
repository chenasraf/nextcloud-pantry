<template>
  <NcEmptyContent :name="strings.title" :description="strings.body">
    <template #icon>
      <HomeIcon />
    </template>
    <template #action>
      <p class="pantry-welcome__hint">{{ strings.hint }}</p>
    </template>
  </NcEmptyContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import HomeIcon from '@icons/Home.vue'

const strings = {
  title: t('pantry', 'Welcome to Pantry'),
  body: t('pantry', 'Create a house to start organizing your checklists, photos and notes.'),
  hint: t('pantry', 'Use the house picker at the bottom of the sidebar to create one.'),
}
</script>

<style scoped>
.pantry-welcome__hint {
  color: var(--color-text-maxcontrast);
  font-size: 0.9rem;
}
</style>
