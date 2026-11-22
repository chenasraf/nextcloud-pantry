<template>
  <NcDialog
    :name="name"
    :open="open"
    size="small"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <ul class="store-pick">
      <li v-for="store in stores" :key="store.id" class="store-pick__item">
        <button type="button" class="store-pick__main" @click="pick(store)">
          <span class="store-pick__icon" :style="{ color: store.color }">
            <component :is="storeIconComponent(store.icon)" :size="20" />
          </span>
          <span class="store-pick__name" dir="auto">{{ store.name }}</span>
        </button>
      </li>
    </ul>
  </NcDialog>
</template>

<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import type { Store } from '@/api/types'

defineProps<{
  open: boolean
  name: string
  stores: Store[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  pick: [store: Store]
}>()

function pick(store: Store) {
  emit('update:open', false)
  emit('pick', store)
}
</script>

<style scoped lang="scss">
.store-pick {
  list-style: none;
  padding: 0;
  margin: 0 0 0.5rem;

  &__item {
    border-bottom: 1px solid var(--color-border);

    &:last-child {
      border-bottom: none;
    }
  }

  &__main {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 10px 8px;
    border: none;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: start;
    cursor: pointer;
    border-radius: var(--border-radius, 8px);

    &:hover,
    &:focus-visible {
      background: var(--color-background-hover);
    }
  }

  &__icon {
    display: inline-flex;
    flex-shrink: 0;
  }

  &__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
