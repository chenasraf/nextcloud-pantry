<template>
  <li class="checklist-row" :class="{ 'checklist-row--done': item.done }">
    <NcCheckboxRadioSwitch :model-value="item.done" @update:model-value="$emit('toggle', item.id)">
      <span class="checklist-row__label">
        <button
          v-if="item.imageFileId"
          type="button"
          class="checklist-row__thumb"
          :aria-label="strings.viewImage"
          @click.stop.prevent="$emit('preview', item)"
        >
          <img :src="thumbUrl" :alt="item.name" />
        </button>
        <span class="checklist-row__name">{{ item.name }}</span>
      </span>
    </NcCheckboxRadioSwitch>
    <div class="checklist-row__meta">
      <span v-if="item.quantity" class="checklist-row__quantity">&times; {{ item.quantity }}</span>
      <span v-if="category" class="checklist-row__category" :style="{ color: category.color }">
        <component :is="categoryIconComponent(category.icon)" :size="14" />
        {{ category.name }}
      </span>
      <span v-if="item.rrule" class="checklist-row__recurrence" :title="item.rrule">
        <RepeatIcon :size="14" />
        {{ formatRrule(item.rrule) }}
      </span>
    </div>
    <div class="checklist-row__actions">
      <NcButton variant="tertiary" :aria-label="strings.viewItem" @click="$emit('view', item)">
        <template #icon>
          <EyeIcon :size="18" />
        </template>
      </NcButton>
      <NcActions :aria-label="strings.itemActions">
        <NcActionButton @click="$emit('edit', item)">
          <template #icon>
            <PencilIcon :size="20" />
          </template>
          {{ strings.editItem }}
        </NcActionButton>
        <NcActionButton @click="$emit('remove', item.id)">
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
          {{ strings.removeItem }}
        </NcActionButton>
      </NcActions>
    </div>
  </li>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import RepeatIcon from '@icons/Repeat.vue'
import PencilIcon from '@icons/Pencil.vue'
import EyeIcon from '@icons/Eye.vue'
import DeleteIcon from '@icons/Delete.vue'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule } from '@/utils/rrule'
import type { ChecklistItem, Category } from '@/api/types'

const props = defineProps<{
  item: ChecklistItem
  category: Category | null
  houseId: number
}>()

defineEmits<{
  toggle: [id: number]
  view: [item: ChecklistItem]
  edit: [item: ChecklistItem]
  remove: [id: number]
  preview: [item: ChecklistItem]
}>()

const thumbUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 64)
    : '',
)

const strings = {
  viewImage: t('pantry', 'View image'),
  viewItem: t('pantry', 'View item'),
  itemActions: t('pantry', 'Item actions'),
  editItem: t('pantry', 'Edit item'),
  removeItem: t('pantry', 'Remove item'),
}
</script>

<style scoped lang="scss">
.checklist-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius, 8px);
  background: var(--color-main-background);

  @media (max-width: 600px) {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'check actions'
      'meta  meta';
    gap: 0.25rem 0.5rem;

    :deep(.checkbox-radio-switch) {
      grid-area: check;
    }

    .checklist-row__actions {
      grid-area: actions;
    }

    .checklist-row__meta {
      grid-area: meta;
    }
  }

  &--done {
    opacity: 0.6;

    .checklist-row__name {
      text-decoration: line-through;
    }
  }

  :deep(.checkbox-content__icon) {
    margin-block: auto !important;
  }

  :deep(.checkbox-radio-switch__content) {
    width: 100%;
    max-width: unset;
  }

  &__label {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
  }

  &__thumb {
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 6px);
    background: var(--color-background-hover);
    cursor: zoom-in;
    overflow: hidden;
    flex-shrink: 0;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    &:hover,
    &:focus-visible {
      border-color: var(--color-primary-element);
    }
  }

  &__name {
    font-weight: 500;
  }

  &__meta {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  &__quantity,
  &__category,
  &__recurrence {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }
}
</style>
