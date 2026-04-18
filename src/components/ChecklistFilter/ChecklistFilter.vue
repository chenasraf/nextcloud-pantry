<template>
  <div class="pantry-filter">
    <NcTextField
      v-model="localQuery"
      :placeholder="strings.placeholder"
      :show-trailing-button="localQuery.length > 0"
      trailing-button-icon="close"
      @trailing-button-click="localQuery = ''"
    >
      <template #icon>
        <MagnifyIcon :size="18" />
      </template>
    </NcTextField>

    <div v-if="categoryOptions.length > 0" class="pantry-filter__categories">
      <NcChip
        :variant="selectedIds.length === 0 ? 'primary' : 'secondary'"
        class="pantry-filter__chip"
        no-close
        @click="$emit('update:selectedCategoryIds', [])"
      >
        <template #icon>
          <CheckIcon v-if="selectedIds.length === 0" :size="16" />
        </template>
        <span class="pantry-filter__chip-content">
          {{ strings.all }}
          <NcCounterBubble :count="totalCount" />
        </span>
      </NcChip>
      <NcChip
        v-for="opt in categoryOptions"
        :key="opt.category.id"
        :variant="selectedIds.includes(opt.category.id) ? 'primary' : 'secondary'"
        class="pantry-filter__chip"
        no-close
        @click="toggleCategory(opt.category.id)"
      >
        <template #icon>
          <component
            :is="iconFor(opt.category.icon)"
            :size="16"
            :style="{ color: opt.category.color }"
          />
        </template>
        <span class="pantry-filter__chip-content">
          {{ opt.category.name }}
          <NcCounterBubble :count="opt.count" />
        </span>
      </NcChip>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import MagnifyIcon from '@icons/Magnify.vue'
import CheckIcon from '@icons/Check.vue'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import type { Category, ChecklistItem } from '@/api/types'

const props = defineProps<{
  query: string
  selectedCategoryIds: number[]
  items: ChecklistItem[]
  categories: Category[]
}>()

const emit = defineEmits<{
  (e: 'update:query', v: string): void
  (e: 'update:selectedCategoryIds', v: number[]): void
}>()

const localQuery = computed({
  get: () => props.query,
  set: (v: string) => emit('update:query', v),
})

const totalCount = computed(() => props.items.length)

interface CategoryOption {
  category: Category
  count: number
}

const categoryOptions = computed<CategoryOption[]>(() => {
  const counts = new Map<number, number>()
  for (const item of props.items) {
    if (item.categoryId != null) {
      counts.set(item.categoryId, (counts.get(item.categoryId) ?? 0) + 1)
    }
  }
  return props.categories
    .filter((c) => counts.has(c.id))
    .map((c) => ({ category: c, count: counts.get(c.id)! }))
})

const selectedIds = computed(() => props.selectedCategoryIds)

function toggleCategory(id: number) {
  const current = selectedIds.value
  if (current.includes(id)) {
    emit(
      'update:selectedCategoryIds',
      current.filter((cid) => cid !== id),
    )
  } else {
    emit('update:selectedCategoryIds', [...current, id])
  }
}

function iconFor(key: string) {
  return categoryIconComponent(key)
}

const strings = {
  placeholder: t('pantry', 'Type to filter …'),
  all: t('pantry', 'All'),
}
</script>

<style scoped lang="scss">
.pantry-filter {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  &__categories {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    align-items: center;
  }

  &__chip :deep(*) {
    cursor: pointer;
  }

  &__chip#{&}__chip {
    transition: background-color 0.15s ease;
  }

  &__chip#{&}__chip:hover {
    background-color: color-mix(
      in srgb,
      var(--color-primary-element) 50%,
      var(--color-background-hover)
    );
  }

  &__chip-content {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }
}
</style>
