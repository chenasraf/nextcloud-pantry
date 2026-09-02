<template>
  <NcDialog
    :name="item.name"
    :open="open"
    close-on-click-outside
    size="normal"
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <div class="item-view">
      <div
        class="item-view__hero"
        :class="{ 'item-view__hero--image': hasImage }"
        :style="heroStyle"
      >
        <button
          v-if="hasImage"
          type="button"
          class="item-view__image-btn"
          :aria-label="strings.viewImage"
          @click="$emit('preview', item)"
        >
          <img :src="largeUrl" :alt="item.name" />
        </button>
        <span v-else-if="category" class="item-view__glyph">
          <component :is="categoryIconComponent(category.icon)" :size="34" />
        </span>

        <div class="item-view__overlay">
          <div v-if="chips.length > 0" class="item-view__chips">
            <PantryChip
              v-for="chip in chips"
              :key="chip.key"
              class="item-view__chip"
              :color="chip.color"
              solid
              size="sm"
              :interactive="false"
            >
              <template #icon>
                <component :is="chip.icon" :size="14" />
              </template>
              {{ chip.name }}
            </PantryChip>
          </div>
          <h2 class="item-view__title" dir="auto">{{ item.name }}</h2>
        </div>
      </div>

      <div class="item-view__tiles">
        <FieldCard v-if="item.quantity" :label="strings.quantity" class="item-view__tile">
          <span class="item-view__tile-value">{{ item.quantity }}</span>
        </FieldCard>
        <FieldCard :label="strings.type" class="item-view__tile">
          <span class="item-view__tile-value item-view__tile-value--type">
            <component :is="typeIcon" :size="20" />
            {{ typeLabel }}
          </span>
          <span v-if="typeSubtitle" class="item-view__tile-sub">{{ typeSubtitle }}</span>
        </FieldCard>
      </div>

      <FieldCard v-if="priceRows.length > 0" :label="strings.price">
        <div class="item-view__prices">
          <span v-for="row in priceRows" :key="row.key" class="item-view__price">
            <PriceIcon :size="16" class="item-view__price-icon" />
            <span v-if="showPriceStore" class="item-view__price-store">{{ row.label }}</span>
            <span class="item-view__price-amount">{{ row.text }}</span>
          </span>
        </div>
      </FieldCard>

      <FieldCard v-if="customFieldRows.length > 0" :label="strings.customFields">
        <div class="item-view__cf">
          <div v-for="row in customFieldRows" :key="row.id" class="item-view__cf-row">
            <component :is="row.icon" :size="18" class="item-view__cf-icon" />
            <span class="item-view__cf-name">{{ row.name }}</span>
            <span class="item-view__cf-value" dir="auto">
              <template v-if="row.segments">
                <template v-for="(seg, i) in row.segments" :key="i">
                  <a
                    v-if="seg.href"
                    :href="seg.href"
                    class="item-view__cf-link"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    >{{ seg.text }}</a
                  >
                  <template v-else>{{ seg.text }}</template></template
                >
              </template>
              <template v-else>{{ row.text }}</template>
            </span>
          </div>
        </div>
      </FieldCard>

      <FieldCard v-if="item.description" :label="strings.description">
        <div ref="descriptionEl" class="item-view__description" dir="auto">
          <NcRichText
            :text="item.description"
            :use-markdown="true"
            :use-extended-markdown="true"
            :interactive="true"
            @interact-todo="onToggleTask"
          />
        </div>
      </FieldCard>

      <div class="item-view__meta">
        <div class="item-view__meta-row">
          <NcAvatar
            v-if="showAddedBy && item.addedBy"
            :user="item.addedBy"
            :size="24"
            :show-user-status="false"
            :disable-menu="true"
          />
          <span class="item-view__meta-text">
            {{ addedLabel }}
            <span class="item-view__meta-sep" aria-hidden="true">·</span>
            <NcDateTime :timestamp="item.createdAt * 1000" />
          </span>
        </div>
        <div v-if="item.done && item.doneAt" class="item-view__meta-row">
          <CheckCircleOutlineIcon :size="20" class="item-view__meta-icon" />
          <span class="item-view__meta-text">
            {{ doneLabel }}
            <span class="item-view__meta-sep" aria-hidden="true">·</span>
            <NcDateTime :timestamp="item.doneAt * 1000" />
          </span>
        </div>
      </div>
    </div>
    <template #actions>
      <NcButton variant="primary" @click="$emit('edit', item)">
        <template #icon>
          <PencilIcon :size="20" />
        </template>
        {{ strings.editItem }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import RepeatIcon from '@icons/Repeat.vue'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import PencilIcon from '@icons/Pencil.vue'
import CheckCircleOutlineIcon from '@icons/CheckCircleOutline.vue'
import TextIcon from '@icons/Text.vue'
import NumericIcon from '@icons/Numeric.vue'
import CheckboxMarkedOutlineIcon from '@icons/CheckboxMarkedOutline.vue'
import CalendarOutlineIcon from '@icons/CalendarOutline.vue'
import FormatListBulletedTypeIcon from '@icons/FormatListBulletedType.vue'
import FieldCard from '@/components/FieldCard'
import { categoryIconComponent } from '@/components/CategoryPicker'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import { labelIconComponent } from '@/components/LabelPicker/labelIcons'
import { entityIcon } from '@/utils/entityIcons'
import PantryChip from '@/components/PantryChip'
import { itemImagePreviewUrl } from '@/api/images'
import { formatRrule } from '@/utils/rrule'
import { formatPrice } from '@/utils/price'
import { toggleMarkdownTask } from '@/utils/markdownTask'
import { linkifySegments, type TextSegment } from '@/utils/linkify'
import { getCurrentUserId } from '@/utils/currentUser'
import { useCustomFields } from '@/composables/useCustomFields'
import { useHouseMembers } from '@/composables/useHouseMembers'
import type {
  ChecklistItem,
  Category,
  Store,
  Label,
  FieldDefinition,
  FieldType,
  ItemCustomFieldValue,
} from '@/api/types'

const PriceIcon = entityIcon.price

const props = withDefaults(
  defineProps<{
    open: boolean
    item: ChecklistItem
    category: Category | null
    stores?: Store[]
    labels?: Label[]
    houseId: number
    /** Reveal who added/completed the item, mirroring the list's added-by pref. */
    showAddedBy?: boolean
  }>(),
  { stores: () => [], labels: () => [], showAddedBy: false },
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

const hasImage = computed(() => !!props.item.imageFileId)

const largeUrl = computed(() =>
  props.item.imageFileId
    ? itemImagePreviewUrl(props.houseId, props.item.imageFileId!, props.item.imageUploadedBy!, 1600)
    : '',
)

// The fallback header (no image) tints a gradient with the category color.
const heroStyle = computed(() => ({
  '--cat-color': props.category?.color ?? 'var(--color-primary-element)',
}))

interface Chip {
  key: string
  name: string
  color: string
  icon: Component
}

const chips = computed<Chip[]>(() => {
  const list: Chip[] = []
  if (props.category) {
    list.push({
      key: 'category',
      name: props.category.name,
      color: props.category.color,
      icon: categoryIconComponent(props.category.icon),
    })
  }
  for (const store of props.stores) {
    list.push({
      key: `store-${store.id}`,
      name: store.name,
      color: store.color,
      icon: storeIconComponent(store.icon),
    })
  }
  for (const label of props.labels) {
    list.push({
      key: `label-${label.id}`,
      name: label.name,
      color: label.color,
      icon: labelIconComponent(label.icon),
    })
  }
  return list
})

// ----- Item type tile -----

const isOneTime = computed(() => props.item.deleteOnDone)
const isRecurring = computed(() => !props.item.deleteOnDone && !!props.item.rrule)

const typeLabel = computed(() =>
  isOneTime.value ? strings.oneTime : isRecurring.value ? strings.recurring : strings.staple,
)
const typeIcon = computed<Component>(() =>
  isOneTime.value ? DeleteIcon : isRecurring.value ? RepeatIcon : PinIcon,
)
const typeSubtitle = computed(() =>
  isRecurring.value && props.item.rrule ? formatRrule(props.item.rrule) : null,
)

// ----- Prices -----

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

// A single store-less price needs no per-line store label; several prices do.
const showPriceStore = computed(() => priceRows.value.length > 1)

// ----- Custom fields -----

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

const CF_TYPE_ICON: Record<FieldType, Component> = {
  text: TextIcon,
  number: NumericIcon,
  checkbox: CheckboxMarkedOutlineIcon,
  date: CalendarOutlineIcon,
  select: FormatListBulletedTypeIcon,
}

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

interface CustomFieldRow {
  id: number
  name: string
  text: string
  icon: Component
  // Present only for text fields, where the value may contain hyperlinks.
  segments: TextSegment[] | null
}

// House-wide ∪ this item's list fields that carry a value, in definition order.
const customFieldRows = computed<CustomFieldRow[]>(() => {
  const byField = new Map(props.item.customFields.map((v) => [v.fieldId, v]))
  const rows: CustomFieldRow[] = []
  for (const field of fields.items.value) {
    if (field.listId != null && field.listId !== props.item.listId) continue
    const value = byField.get(field.id)
    if (!value) continue
    const text = formatFieldValue(field, value)
    if (text == null || text === '') continue
    rows.push({
      id: field.id,
      name: field.name,
      text,
      icon: CF_TYPE_ICON[field.type],
      segments: field.type === 'text' ? linkifySegments(text) : null,
    })
  }
  return rows
})

// ----- Added / done attribution -----

const currentUid = getCurrentUserId()
const { displayNameByUid } = useHouseMembers(props.houseId)

function actorName(uid: string): string {
  if (uid === currentUid) return strings.you
  return displayNameByUid.value[uid] ?? uid
}

const addedLabel = computed(() =>
  props.showAddedBy && props.item.addedBy
    ? t('pantry', 'Added by {user}', { user: actorName(props.item.addedBy) })
    : strings.added,
)

const doneLabel = computed(() =>
  props.showAddedBy && props.item.doneBy
    ? t('pantry', 'Done by {user}', { user: actorName(props.item.doneBy) })
    : strings.doneOn,
)

const strings = {
  viewImage: t('pantry', 'View image'),
  quantity: t('pantry', 'Quantity'),
  // TRANSLATORS: Noun, the item's lifecycle kind (staple / one-time / recurring). Tile caption.
  type: t('pantry', 'Type'),
  price: t('pantry', 'Price'),
  // TRANSLATORS: Label for the price that applies when no specific store is chosen
  anyStore: t('pantry', 'Any store'),
  store: t('pantry', 'Store'),
  customFields: t('pantry', 'Custom fields'),
  description: t('pantry', 'Description'),
  // TRANSLATORS: Item type (noun) — a staple that stays on the list after being checked off.
  staple: t('pantry', 'Staple'),
  oneTime: t('pantry', 'One-time'),
  recurring: t('pantry', 'Recurring'),
  // TRANSLATORS: Prefix before a relative time, e.g. "Added · 2 months ago". Verb, past tense.
  added: t('pantry', 'Added'),
  // TRANSLATORS: Prefix before a relative time, e.g. "Done · 3 days ago". The item was completed.
  doneOn: t('pantry', 'Done'),
  // TRANSLATORS: Stands in for the current user's own name, e.g. "Added by you".
  you: t('pantry', 'you'),
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
  gap: 0.85rem;

  &__hero {
    position: relative;
    display: flex;
    align-items: flex-end;
    min-height: 168px;
    border-radius: var(--border-radius-large, 8px);
    overflow: hidden;
    background: linear-gradient(
      160deg,
      color-mix(in srgb, var(--cat-color) 45%, var(--color-main-background)),
      var(--color-main-background)
    );

    &--image::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.72), rgba(0, 0, 0, 0) 62%);
      pointer-events: none;
    }
  }

  &__image-btn {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    background: none;
    cursor: zoom-in;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
  }

  &__glyph {
    position: absolute;
    inset-block-start: 0.85rem;
    inset-inline-start: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 14px;
    color: var(--cat-color);
    background: color-mix(in srgb, var(--cat-color) 20%, var(--color-main-background));
  }

  &__overlay {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
    padding: 0.85rem;
  }

  &__hero--image &__overlay {
    color: #fff;
  }

  &__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
  }

  &__title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
  }

  &__tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  &__tile {
    flex: 1 1 42%;
    min-width: 120px;
  }

  &__tile-value {
    font-size: 1.25rem;
    font-weight: 700;

    &--type {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
  }

  &__tile-sub {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__prices {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
  }

  &__price {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  &__price-icon {
    color: var(--color-primary-element);
  }

  &__price-store {
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__price-amount {
    font-weight: 600;
  }

  &__cf {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
  }

  &__cf-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  &__cf-icon {
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
  }

  &__cf-name {
    color: var(--color-text-maxcontrast);
    font-size: 0.85rem;
  }

  &__cf-value {
    font-weight: 600;
  }

  &__cf-link {
    color: var(--color-primary-element);
    text-decoration: underline;
    word-break: break-word;
  }

  &__description {
    line-height: 1.6;
    font-size: 0.95rem;

    :deep(*) {
      color: inherit;
    }
  }

  &__meta {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.15rem 0.15rem 0.25rem;
  }

  &__meta-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
  }

  &__meta-icon {
    color: var(--color-text-maxcontrast);
  }

  &__meta-text {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
  }

  &__meta-sep {
    opacity: 0.6;
  }
}
</style>
