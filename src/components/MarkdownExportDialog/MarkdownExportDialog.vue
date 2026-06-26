<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    close-on-click-outside
    @update:open="(v) => !v && $emit('update:open', false)"
  >
    <NcCheckboxRadioSwitch v-model="includeChecked" class="md-export__include">
      {{ strings.includeChecked }}
    </NcCheckboxRadioSwitch>
    <p class="md-export__hint">{{ strings.editHint }}</p>
    <textarea v-model="content" class="md-export" :aria-label="strings.title" rows="16" />
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.close }}</NcButton>
      <NcButton @click="copy">
        <template #icon>
          <ContentCopyIcon :size="20" />
        </template>
        {{ strings.copy }}
      </NcButton>
      <NcButton variant="primary" @click="download">
        <template #icon>
          <DownloadIcon :size="20" />
        </template>
        {{ strings.download }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import DownloadIcon from '@icons/Download.vue'
import { buildListMarkdown } from '@/utils/markdownList'
import type { Category, ChecklistItem } from '@/api/types'

const props = defineProps<{
  open: boolean
  listName: string
  items: ChecklistItem[]
  categoryFor: (id: number | null) => Category | null
}>()

defineEmits<{
  'update:open': [value: boolean]
}>()

// Completed items are excluded by default; the checkbox opts them back in.
const includeChecked = ref(false)

const generated = computed(() => {
  const items = includeChecked.value ? props.items : props.items.filter((i) => !i.done)
  return buildListMarkdown(props.listName, items, props.categoryFor)
})

// Local editable copy — re-seeded from the generated document whenever the
// dialog opens or the include-completed toggle flips, so edits survive typing
// but a content-changing toggle regenerates. copy/download use this value.
const content = ref('')

function reseed() {
  content.value = generated.value
}

watch(
  () => props.open,
  (open) => {
    if (!open) return
    includeChecked.value = false
    reseed()
  },
  { immediate: true },
)

watch(includeChecked, () => {
  if (props.open) reseed()
})

async function copy() {
  try {
    await navigator.clipboard.writeText(content.value)
    showSuccess(strings.copied)
  } catch {
    showError(strings.copyFailed)
  }
}

function safeFileName(name: string): string {
  const base = name.trim().replace(/[\\/:*?"<>|]+/g, '-') || 'list'
  return `${base}.md`
}

function download() {
  const blob = new Blob([content.value], { type: 'text/markdown;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = safeFileName(props.listName)
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

const strings = {
  title: t('pantry', 'Export to Markdown'),
  includeChecked: t('pantry', 'Include completed items'),
  editHint: t('pantry', 'Edit the text below to modify the exported list'),
  close: t('pantry', 'Close'),
  copy: t('pantry', 'Copy'),
  download: t('pantry', 'Download .md'),
  copied: t('pantry', 'Copied to clipboard'),
  copyFailed: t('pantry', 'Could not copy to clipboard'),
}
</script>

<style scoped lang="scss">
.md-export__include {
  margin-bottom: 0.75rem;
}

.md-export__hint {
  margin-bottom: 0.5rem;
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
}

.md-export {
  width: 100%;
  min-height: 320px;
  resize: vertical;
  font-family: monospace;
  font-size: 0.85rem;
  line-height: 1.5;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 8px);
  background: var(--color-background-hover);
  color: var(--color-main-text);
}
</style>
