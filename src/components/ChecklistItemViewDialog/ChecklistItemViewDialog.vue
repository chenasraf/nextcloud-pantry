<template>
  <NcDialog
    :name="item.name"
    :open="open"
    close-on-click-outside
    size="normal"
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <div class="item-view">
      <button
        v-if="item.imageFileId"
        type="button"
        class="item-view__image-btn"
        :aria-label="strings.viewImage"
        @click="$emit('preview', item)"
      >
        <img class="item-view__image" :src="largeUrl" :alt="item.name" />
      </button>

      <div v-if="item.description" class="item-view__description" dir="auto">
        <NcRichText :text="item.description" :use-markdown="true" :use-extended-markdown="true" />
      </div>

      <div class="item-view__details">
        <div v-if="item.quantity" class="item-view__row">
          <span class="item-view__label">{{ strings.quantity }}:</span>
          <span>&times; {{ item.quantity }}</span>
        </div>
        <div v-if="priceText" class="item-view__row">
          <span class="item-view__label">{{ strings.price }}:</span>
          <span>{{ priceText }}</span>
        </div>
        <div v-if="category" class="item-view__row">
          <span class="item-view__label">{{ strings.category }}:</span>
          <span class="item-view__badge" :style="{ color: category.color }">
            <component :is="categoryIconComponent(category.icon)" :size="14" />
            {{ category.name }}
          </span>
        </div>
        <div v-if="stores.length > 0" class="item-view__row">
          <span class="item-view__label">{{ strings.stores }}:</span>
          <span class="item-view__stores">
            <span
              v-for="store in stores"
              :key="store.id"
              class="item-view__badge"
              :style="{ color: store.color }"
            >
              <component :is="storeIconComponent(store.icon)" :size="14" />
              {{ store.name }}
            </span>
          </span>
        </div>
        <div v-if="item.rrule" class="item-view__row">
          <span class="item-view__label">{{ strings.recurrence }}:</span>
          <span class="item-view__badge">
            <RepeatIcon :size="14" />
            {{ formatRrule(item.rrule) }}
          </span>
        </div>
        <div v-if="nextRecurrence" class="item-view__row">
          <span class="item-view__label">{{ strings.nextRecurrence }}:</span>
          <span>{{ nextRecurrence }}</span>
        </div>
        <div v-if="item.done" class="item-view__row">
          <span class="item-view__label">{{ strings.status }}:</span>
          <span>{{ strings.done }}</span>
        </div>
      </div>
    </div>
    <template #actions>
      <NcButton variant="tertiary" @click="$emit('edit', item)">
        <template #icon>
          <PencilIcon :size="20" />
        </template>
        {{ strings.editItem }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import RepeatIcon from '@icons/Repeat.vue'
import PencilIcon from '@icons/Pencil.vue'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule, formatNextRecurrence } from '@/utils/rrule'
import { formatPrice } from '@/utils/price'
import type { ChecklistItem, Category, Store } from '@/api/types'

const props = withDefaults(
  defineProps<{
    open: boolean
    item: ChecklistItem
    category: Category | null
    stores?: Store[]
    houseId: number
  }>(),
  { stores: () => [] },
)

defineEmits<{
  'update:open': [value: boolean]
  edit: [item: ChecklistItem]
  preview: [item: ChecklistItem]
}>()

const largeUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 1600)
    : '',
)

const priceText = computed(() => formatPrice(props.item))

const nextRecurrence = computed(() =>
  props.item.rrule
    ? formatNextRecurrence(props.item.nextDueAt, props.item.repeatFromCompletion, props.item.done)
    : null,
)

const strings = {
  viewImage: t('pantry', 'View image'),
  quantity: t('pantry', 'Quantity'),
  price: t('pantry', 'Price'),
  category: t('pantry', 'Category'),
  // TRANSLATORS: Noun (plural), shops where this item can be bought. Detail row label.
  stores: t('pantry', 'Stores'),
  recurrence: t('pantry', 'Recurrence'),
  nextRecurrence: t('pantry', 'Next recurrence'),
  status: t('pantry', 'Status'),
  // TRANSLATORS: Status value (adjective) shown next to "Status:" — the item is completed, not a button.
  done: t('pantry', 'Done'),
  editItem: t('pantry', 'Edit item'),
}
</script>

<style scoped lang="scss">
.item-view {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  &__image-btn {
    display: block;
    width: 100%;
    padding: 0;
    border: 0;
    background: none;
    cursor: zoom-in;
    border-radius: var(--border-radius, 8px);
    overflow: hidden;
  }

  &__image {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    display: block;
    border-radius: var(--border-radius, 8px);
  }

  &__description {
    line-height: 1.6;
    font-size: 0.95rem;

    :deep(*) {
      color: inherit;
    }
  }

  &__details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  &__row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
  }

  &__label {
    color: var(--color-text-maxcontrast);
    font-weight: 500;
  }

  &__badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-background-hover);
  }

  &__stores {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }
}
</style>
