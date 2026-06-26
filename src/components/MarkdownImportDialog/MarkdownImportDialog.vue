<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <div
      class="md-import"
      :class="{ 'md-import--drag': dragActive }"
      @dragover.prevent="onDragOver"
      @dragenter.prevent="onDragOver"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <div v-if="dragActive" class="md-import__drop-overlay">
        {{ strings.dropHint }}
      </div>
      <div class="md-import__source">
        <NcButton variant="secondary" type="button" @click="triggerFilePick">
          <template #icon>
            <FileUploadIcon :size="20" />
          </template>
          {{ strings.uploadFile }}
        </NcButton>
        <input
          ref="fileInputRef"
          type="file"
          accept=".md,.markdown,.txt,text/markdown,text/plain"
          class="md-import__file-input"
          @change="onFilePicked"
        />
      </div>

      <AutoResizeTextarea
        v-model="rawText"
        class="md-import__text"
        :rows="6"
        :label="strings.pasteLabel"
        :placeholder="strings.pastePlaceholder"
        autocomplete="off"
      />

      <div v-if="parsed.length > 0" class="md-import__found">
        <div class="md-import__found-header">
          <span class="md-import__found-count">{{ foundLabel }}</span>
          <NcButton variant="tertiary" type="button" @click="toggleAll">
            {{ allSelected ? strings.deselectAll : strings.selectAll }}
          </NcButton>
        </div>
        <ul class="md-import__list">
          <li v-for="(it, i) in parsed" :key="i" class="md-import__row">
            <NcCheckboxRadioSwitch v-model="selected[i]">
              {{ it.name }}
            </NcCheckboxRadioSwitch>
          </li>
        </ul>
      </div>
      <p v-else-if="rawText.trim().length > 0" class="md-import__empty">
        {{ strings.noneFound }}
      </p>

      <div v-if="parsed.length > 0" class="md-import__chips">
        <PantryChip
          v-for="chip in chips"
          :key="chip.key"
          :variant="chipVariant(chip)"
          class="md-import__chip"
          @click="toggleSection(chip.key)"
        >
          <template #icon>
            <component :is="chip.icon" :size="14" :style="chip.iconStyle" />
          </template>
          {{ chip.text }}
        </PantryChip>
      </div>

      <div v-if="parsed.length > 0 && openSection" class="md-import__section">
        <CategoryChipList
          v-if="openSection === 'category'"
          v-model="categoryId"
          :house-id="houseId"
        />
        <QuantityInput v-else-if="openSection === 'quantity'" v-model="quantity" />
        <AutoResizeTextarea
          v-else-if="openSection === 'description'"
          v-model="description"
          :label="strings.descriptionLabel"
          :placeholder="strings.descriptionPlaceholder"
          autocomplete="off"
        />
        <div v-else-if="openSection === 'type'" class="md-import__type">
          <ItemTypeSelector
            :delete-on-done="deleteOnDone"
            :rrule="rrule"
            @select-staple="selectStaple"
            @select-one-time="selectOneTime"
            @select-recurring="selectRecurring"
          />
          <RecurrenceForm
            v-if="currentType === 'recurring'"
            v-model="rrule"
            v-model:from-completion="repeatFromCompletion"
          />
        </div>
      </div>

      <NcCheckboxRadioSwitch
        v-if="parsed.length > 0 && canForceReuse"
        v-model="forceReuse"
        class="md-import__reuse"
      >
        {{ strings.reuseExisting }}
      </NcCheckboxRadioSwitch>
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="selectedCount === 0 || importing" @click="submit">
        {{ addLabel }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { t, n } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import FileUploadIcon from '@icons/FileUpload.vue'
import TagOutlineIcon from '@icons/TagOutline.vue'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import TextIcon from '@icons/Text.vue'
import PinIcon from '@icons/Pin.vue'
import DeleteIcon from '@icons/Delete.vue'
import RepeatIcon from '@icons/Repeat.vue'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import { RecurrenceForm } from '@/components/RecurrenceEditor'
import CategoryChipList from '@/components/CategoryChipList'
import ItemTypeSelector from '@/components/ItemTypeSelector'
import QuantityInput from '@/components/QuantityInput'
import PantryChip from '@/components/PantryChip'
import { useCategories } from '@/composables/useCategories'
import { categoryIconComponent } from '@/components/CategoryPicker/categoryIcons'
import { formatRrule } from '@/utils/rrule'
import { parseMarkdownItems } from '@/utils/markdownList'
import type { ItemInput } from '@/api/lists'
import type { ReuseExistingItems } from '@/api/prefs'

type SectionKey = 'category' | 'quantity' | 'description' | 'type'

const props = defineProps<{
  open: boolean
  houseId: number
  importing: boolean
  reusePref: ReuseExistingItems
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  import: [inputs: ItemInput[], forceReuse: boolean]
}>()

// Only offer the override when the global pref would not already reuse on its
// own — i.e. when it is "ask" or "never".
const canForceReuse = computed(() => props.reusePref !== 'reuse')

const rawText = ref('')
const fileInputRef = ref<HTMLInputElement | null>(null)
const selected = ref<boolean[]>([])

const description = ref('')
const quantity = ref('')
const categoryId = ref<number | null>(null)
const rrule = ref<string | null>(null)
const repeatFromCompletion = ref(false)
const deleteOnDone = ref(false)
const userPickedType = ref(false)
const openSection = ref<SectionKey | null>(null)
const forceReuse = ref(false)
const dragActive = ref(false)

const { items: categories, load: loadCategories } = useCategories(props.houseId)
void loadCategories()

const parsed = computed(() => parseMarkdownItems(rawText.value))

// Re-mark everything as selected whenever the parsed set changes.
watch(
  parsed,
  (items) => {
    selected.value = items.map(() => true)
  },
  { immediate: true },
)

// Reset the whole dialog each time it opens.
watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    rawText.value = ''
    description.value = ''
    quantity.value = ''
    categoryId.value = null
    rrule.value = null
    repeatFromCompletion.value = false
    deleteOnDone.value = false
    userPickedType.value = false
    openSection.value = null
    forceReuse.value = false
    dragActive.value = false
  },
)

const selectedCount = computed(() => selected.value.filter(Boolean).length)
const allSelected = computed(() => parsed.value.length > 0 && selected.value.every(Boolean))

function toggleAll() {
  const target = !allSelected.value
  selected.value = parsed.value.map(() => target)
}

function triggerFilePick() {
  fileInputRef.value?.click()
}

async function loadFile(file: File) {
  rawText.value = await file.text()
}

async function onFilePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  await loadFile(file)
  input.value = ''
}

// ----- Drag & drop -----

function onDragOver(e: DragEvent) {
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy'
  dragActive.value = true
}

function onDragLeave(e: DragEvent) {
  // Ignore leave events fired while moving between child elements — only clear
  // the highlight when the pointer actually leaves the dialog body.
  const related = e.relatedTarget as Node | null
  if (related && e.currentTarget instanceof Node && e.currentTarget.contains(related)) return
  dragActive.value = false
}

async function onDrop(e: DragEvent) {
  dragActive.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) await loadFile(file)
}

function toggleSection(key: SectionKey) {
  openSection.value = openSection.value === key ? null : key
}

// ----- Item type -----

type ItemType = 'staple' | 'oneTime' | 'recurring'

const currentType = computed<ItemType>(() => {
  if (deleteOnDone.value) return 'oneTime'
  if (rrule.value) return 'recurring'
  return 'staple'
})

function selectStaple() {
  rrule.value = null
  repeatFromCompletion.value = false
  userPickedType.value = true
  deleteOnDone.value = false
}

function selectOneTime() {
  rrule.value = null
  repeatFromCompletion.value = false
  userPickedType.value = true
  deleteOnDone.value = true
}

function selectRecurring() {
  deleteOnDone.value = false
  userPickedType.value = true
  if (!rrule.value) rrule.value = 'FREQ=WEEKLY;INTERVAL=1'
}

// ----- Chips -----

const selectedCategory = computed(() =>
  categoryId.value != null
    ? (categories.value.find((c) => c.id === categoryId.value) ?? null)
    : null,
)

interface Chip {
  key: SectionKey
  text: string
  icon: Component
  iconStyle?: Record<string, string>
  filled: boolean
}

const chips = computed<Chip[]>(() => {
  const list: Chip[] = [
    {
      key: 'category',
      text: selectedCategory.value ? selectedCategory.value.name : strings.category,
      icon: selectedCategory.value
        ? categoryIconComponent(selectedCategory.value.icon)
        : TagOutlineIcon,
      iconStyle: selectedCategory.value ? { color: selectedCategory.value.color } : undefined,
      filled: selectedCategory.value !== null,
    },
    {
      key: 'quantity',
      text: quantity.value.trim() || strings.quantity,
      icon: FormatListBulletedIcon,
      filled: quantity.value.trim().length > 0,
    },
    {
      key: 'description',
      text: strings.description,
      icon: TextIcon,
      filled: description.value.trim().length > 0,
    },
  ]

  if (!userPickedType.value) {
    list.push({ key: 'type', text: strings.itemType, icon: RepeatIcon, filled: false })
  } else if (currentType.value === 'staple') {
    list.push({ key: 'type', text: strings.staple, icon: PinIcon, filled: true })
  } else if (currentType.value === 'oneTime') {
    list.push({ key: 'type', text: strings.oneTime, icon: DeleteIcon, filled: true })
  } else {
    list.push({
      key: 'type',
      text: rrule.value ? formatRrule(rrule.value) : strings.recurring,
      icon: RepeatIcon,
      filled: true,
    })
  }

  return list
})

function chipVariant(chip: Chip): 'primary' | 'secondary' | 'tertiary' {
  if (openSection.value === chip.key) return 'primary'
  if (chip.filled) return 'secondary'
  return 'tertiary'
}

// ----- Submit -----

function submit() {
  const once = deleteOnDone.value
  const inputs: ItemInput[] = parsed.value
    .filter((_, i) => selected.value[i])
    .map((it) => ({
      name: it.name,
      description: description.value.trim() || null,
      quantity: quantity.value.trim() || null,
      categoryId: categoryId.value,
      rrule: once ? null : rrule.value,
      repeatFromCompletion: once ? false : repeatFromCompletion.value,
      deleteOnDone: once,
    }))
  if (inputs.length === 0) return
  emit('import', inputs, canForceReuse.value && forceReuse.value)
}

const foundLabel = computed(() =>
  n('pantry', '%n item found', '%n items found', parsed.value.length),
)
const addLabel = computed(() => n('pantry', 'Add %n item', 'Add %n items', selectedCount.value))

const strings = {
  title: t('pantry', 'Import from Markdown'),
  uploadFile: t('pantry', 'Upload .md file'),
  dropHint: t('pantry', 'Drop a Markdown file to import'),
  pasteLabel: t('pantry', 'Paste Markdown'),
  pastePlaceholder: t('pantry', 'Paste a Markdown list here …'),
  noneFound: t('pantry', 'No list items found in the text.'),
  selectAll: t('pantry', 'Select all'),
  deselectAll: t('pantry', 'Deselect all'),
  reuseExisting: t('pantry', 'Reuse existing items instead of adding duplicates'),
  cancel: t('pantry', 'Cancel'),
  category: t('pantry', 'Category'),
  quantity: t('pantry', 'Quantity'),
  description: t('pantry', 'Description'),
  descriptionLabel: t('pantry', 'Description'),
  descriptionPlaceholder: t('pantry', 'Notes, instructions, links …'),
  itemType: t('pantry', 'Item type'),
  staple: t('pantry', 'Staple'),
  oneTime: t('pantry', 'One-time'),
  recurring: t('pantry', 'Recurring'),
}
</script>

<style scoped lang="scss">
.md-import {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;

  &--drag {
    outline: 2px dashed var(--color-primary-element);
    outline-offset: 4px;
    border-radius: var(--border-radius-large, 8px);
  }

  &__drop-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    text-align: center;
    font-weight: bold;
    color: var(--color-primary-element);
    background: var(--color-primary-element-light, var(--color-background-hover));
    border-radius: var(--border-radius-large, 8px);
  }

  &__source {
    display: flex;
    gap: 0.5rem;
  }

  &__file-input {
    display: none;
  }

  &__found-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
  }

  &__found-count {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  &__list {
    max-height: 240px;
    overflow-y: auto;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 8px);
    padding: 0.5rem 0.75rem;
  }

  &__empty {
    color: var(--color-text-maxcontrast);
  }

  &__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
  }

  &__chip {
    flex: 0 0 auto;
    cursor: pointer;
  }

  &__section {
    padding: 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 8px);
    background: var(--color-background-hover);
  }

  &__type {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }
}
</style>
