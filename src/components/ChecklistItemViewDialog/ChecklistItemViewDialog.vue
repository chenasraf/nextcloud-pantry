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

      <div v-if="item.description" ref="descriptionEl" class="item-view__description" dir="auto">
        <NcRichText
          :text="item.description"
          :use-markdown="true"
          :use-extended-markdown="true"
          :interactive="true"
          @interact-todo="onToggleTask"
        />
      </div>

      <div class="item-view__details">
        <div v-if="item.quantity" class="item-view__row">
          <span class="item-view__label">{{ strings.quantity }}:</span>
          <span>&times; {{ item.quantity }}</span>
        </div>
        <div v-if="priceRows.length > 0" class="item-view__row">
          <span class="item-view__label">{{ strings.price }}:</span>
          <span class="item-view__prices">
            <span v-for="row in priceRows" :key="row.key" class="item-view__price">
              <span class="item-view__price-store">{{ row.label }}</span>
              <span>{{ row.text }}</span>
            </span>
          </span>
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
        <div v-if="labels.length > 0" class="item-view__row">
          <span class="item-view__label">{{ strings.labels }}:</span>
          <span class="item-view__stores">
            <span
              v-for="label in labels"
              :key="label.id"
              class="item-view__badge"
              :style="{ color: label.color }"
            >
              <component :is="labelIconComponent(label.icon)" :size="14" />
              {{ label.name }}
            </span>
          </span>
        </div>
        <div v-for="row in customFieldRows" :key="row.id" class="item-view__row">
          <span class="item-view__label">{{ row.name }}:</span>
          <span dir="auto">{{ row.text }}</span>
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
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import RepeatIcon from '@icons/Repeat.vue'
import PencilIcon from '@icons/Pencil.vue'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule, formatNextRecurrence } from '@/utils/rrule'
import { formatPrice } from '@/utils/price'
import { toggleMarkdownTask } from '@/utils/markdownTask'
import { useCustomFields } from '@/composables/useCustomFields'
import type {
  ChecklistItem,
  Category,
  Store,
  Label,
  FieldDefinition,
  ItemCustomFieldValue,
} from '@/api/types'

const props = withDefaults(
  defineProps<{
    open: boolean
    item: ChecklistItem
    category: Category | null
    stores?: Store[]
    labels?: Label[]
    houseId: number
  }>(),
  { stores: () => [], labels: () => [] },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  edit: [item: ChecklistItem]
  preview: [item: ChecklistItem]
  'toggle-task': [item: ChecklistItem, description: string]
}>()

const descriptionEl = ref<HTMLElement | null>(null)

/**
 * A task-list checkbox in the rendered description was toggled. NcRichText
 * gives us the checkbox's DOM id, so we map it back to its position among the
 * rendered checkboxes (document order) and flip the matching task token in the
 * source markdown, then let the parent persist the new description.
 */
function onToggleTask(id: string) {
  const el = descriptionEl.value
  if (!el) {
    return
  }
  const inputs = Array.from(el.querySelectorAll<HTMLInputElement>('input[type="checkbox"]'))
  const index = inputs.findIndex((input) => input.id === id)
  if (index === -1) {
    return
  }
  const next = toggleMarkdownTask(props.item.description ?? '', index)
  if (next !== props.item.description) {
    emit('toggle-task', props.item, next)
  }
}

const largeUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 1600)
    : '',
)

// One line per price: the store-less price labeled "Any store", then each
// per-store price labeled by its store name.
const priceRows = computed(() =>
  props.item.prices
    .map((p) => ({
      key: p.storeId ?? 'none',
      label:
        p.storeId == null
          ? strings.anyStore
          : (props.stores.find((s) => s.id === p.storeId)?.name ?? strings.store),
      text: formatPrice(p),
    }))
    .filter(
      (row): row is { key: number | string; label: string; text: string } => row.text != null,
    ),
)

const nextRecurrence = computed(() =>
  props.item.rrule
    ? formatNextRecurrence(props.item.nextDueAt, props.item.repeatFromCompletion, props.item.done)
    : null,
)

// The item carries its custom-field values, but rendering them needs the field
// definitions (name, type, select option labels), so load them for the house.
const fields = useCustomFields(props.houseId)
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void fields.load()
  },
  { immediate: true },
)

function formatFieldValue(field: FieldDefinition, value: ItemCustomFieldValue): string | null {
  switch (field.type) {
    case 'text':
      return value.valueText && value.valueText.trim() !== '' ? value.valueText : null
    case 'number':
      return value.valueNumber != null ? String(value.valueNumber) : null
    case 'checkbox':
      return value.valueBool ? strings.yes : strings.no
    case 'date':
      return value.valueDate != null
        ? new Date(value.valueDate * 1000).toLocaleDateString(undefined, { dateStyle: 'medium' })
        : null
    case 'select': {
      const opt = field.options.find((o) => o.id === value.valueOptionId)
      return opt ? opt.label : null
    }
    default:
      return null
  }
}

// House-wide ∪ this item's list fields that carry a value, in definition order.
const customFieldRows = computed<{ id: number; name: string; text: string }[]>(() => {
  const byField = new Map(props.item.customFields.map((v) => [v.fieldId, v]))
  const rows: { id: number; name: string; text: string }[] = []
  for (const field of fields.items.value) {
    if (field.listId != null && field.listId !== props.item.listId) continue
    const value = byField.get(field.id)
    if (!value) continue
    const text = formatFieldValue(field, value)
    if (text == null || text === '') continue
    rows.push({ id: field.id, name: field.name, text })
  }
  return rows
})

const strings = {
  viewImage: t('pantry', 'View image'),
  quantity: t('pantry', 'Quantity'),
  price: t('pantry', 'Price'),
  // TRANSLATORS: Label for the price that applies when no specific store is chosen
  anyStore: t('pantry', 'Any store'),
  store: t('pantry', 'Store'),
  category: t('pantry', 'Category'),
  // TRANSLATORS: Noun (plural), tags attached to an item. Row label in item details.
  labels: t('pantry', 'Labels'),
  // TRANSLATORS: Noun (plural), shops where this item can be bought. Detail row label.
  stores: t('pantry', 'Stores'),
  recurrence: t('pantry', 'Recurrence'),
  nextRecurrence: t('pantry', 'Next recurrence'),
  status: t('pantry', 'Status'),
  // TRANSLATORS: Status value (adjective) shown next to "Status:" — the item is completed, not a button.
  done: t('pantry', 'Done'),
  // TRANSLATORS: Value of a checkbox custom field that is ticked.
  yes: t('pantry', 'Yes'),
  // TRANSLATORS: Value of a checkbox custom field that is not ticked.
  no: t('pantry', 'No'),
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

  &__prices {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
  }

  &__price {
    display: inline-flex;
    align-items: baseline;
    gap: 0.5rem;
  }

  &__price-store {
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }
}
</style>
