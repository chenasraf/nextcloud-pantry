<template>
  <header class="pantry-toolbar">
    <div class="pantry-toolbar__left">
      <slot name="before-title" />
      <h2 v-if="title" class="pantry-toolbar__title">{{ title }}</h2>
      <slot name="after-title" />
    </div>
    <div v-if="$slots.actions" class="pantry-toolbar__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
}>()
</script>

<style scoped lang="scss">
.pantry-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 1rem;
  // The NC sidebar toggle overlays the top-left area of the content.
  // Add left padding so the toolbar content is not hidden behind it.
  padding-inline-start: var(--default-clickable-area, 44px);

  &__left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  &__title {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
  }
}
</style>
