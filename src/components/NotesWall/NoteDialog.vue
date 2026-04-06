<template>
  <NcDialog
    ref="dialogRef"
    name=""
    :open="open"
    close-on-click-outside
    size="normal"
    @update:open="onClose"
  >
    <div class="note-dialog__body">
      <!-- Title -->
      <input
        v-if="editing"
        ref="titleInputRef"
        v-model="titleValue"
        :placeholder="strings.titlePlaceholder"
        class="note-dialog__title-input"
      />
      <h2 v-else class="note-dialog__title-text" @click="startEditing('title')">
        {{ titleValue || strings.untitled }}
      </h2>

      <!-- Content -->
      <NcTextArea
        v-if="editing"
        ref="contentInputRef"
        v-model="contentValue"
        :placeholder="strings.contentPlaceholder"
        resize="none"
        rows="3"
        class="note-dialog__content-input"
      />
      <div
        v-else
        ref="renderedContentRef"
        class="note-dialog__content"
        @click="startEditing('content')"
      >
        <div v-if="contentValue" class="note-dialog__rendered">
          <NcRichText :text="contentValue" :use-markdown="true" :use-extended-markdown="true" />
        </div>
        <p v-else class="note-dialog__empty">{{ strings.noContent }}</p>
      </div>

      <!-- Color swatches (always visible) -->
      <div class="note-dialog__color">
        <div class="note-dialog__swatches">
          <button
            v-for="c in colorOptions"
            :key="c"
            type="button"
            class="note-dialog__swatch"
            :class="{ 'note-dialog__swatch--active': colorValue === c }"
            :style="{
              background: c,
              borderColor: colorValue === c ? swatchBorderColor : 'transparent',
            }"
            :aria-label="c"
            @click="toggleColor(c)"
          />
        </div>
      </div>
    </div>

    <template #actions>
      <NcButton
        variant="tertiary"
        :aria-label="editing ? strings.view : strings.edit"
        @click="toggleEditing"
      >
        <template #icon>
          <EyeIcon v-if="editing" :size="20" />
          <PencilIcon v-else :size="20" />
        </template>
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import PencilIcon from '@icons/Pencil.vue'
import EyeIcon from '@icons/Eye.vue'
import { contrastColor, noteColorOptions } from './noteColors'
import type { Note } from '@/api/types'

const props = defineProps<{
  open: boolean
  note?: Note | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [data: { title: string; content: string; color: string }]
}>()

const titleValue = ref('')
const contentValue = ref('')
const colorValue = ref('')
const editing = ref(false)
const dialogRef = ref<InstanceType<typeof NcDialog> | null>(null)
const titleInputRef = ref<HTMLInputElement | null>(null)
const contentInputRef = ref<InstanceType<typeof NcTextArea> | null>(null)
const renderedContentRef = ref<HTMLElement | null>(null)

const MAX_TEXTAREA_HEIGHT = 400

const isExisting = computed(() => !!props.note)
const swatchBorderColor = computed(() =>
  colorValue.value ? contrastColor(colorValue.value) : 'var(--color-main-text)',
)
const colorOptions = noteColorOptions

// ----- Dialog background color -----

function applyDialogColor() {
  nextTick(() => {
    // NcDialog teleports its content, so we need to find the dialog container
    // through the DOM. The dialog ref's $el may be a comment node (teleport anchor).
    const el = dialogRef.value?.$el as HTMLElement | undefined
    // Try via the component's $el first, then walk up, then search globally
    let container: HTMLElement | null = null
    if (el) {
      container = el.closest?.('.modal-container') as HTMLElement | null
      if (!container) {
        container = el.querySelector?.('.modal-container') as HTMLElement | null
      }
    }
    // Fallback: search all open dialog containers and use the last one (most recent)
    if (!container) {
      const all = document.querySelectorAll('.modal-container')
      container = all.length > 0 ? (all[all.length - 1] as HTMLElement) : null
    }
    if (!container) return
    const fg = colorValue.value ? contrastColor(colorValue.value) : ''
    if (colorValue.value) {
      container.style.background = colorValue.value
      container.style.color = fg
    } else {
      container.style.background = ''
      container.style.color = ''
    }
    // Hide the empty dialog name element
    const nameEl = container.querySelector<HTMLElement>('.dialog__name')
    if (nameEl) {
      nameEl.style.display = 'none'
    }
    // Style header/action buttons and set hover background
    container
      .querySelectorAll<HTMLElement>(
        '.dialog__actions button, .modal-header *, .dialog__close button, .modal-container__close, .modal-container__close *',
      )
      .forEach((el) => {
        el.style.color = fg
      })
    // Set a CSS variable for button hover backgrounds
    container.style.setProperty(
      '--note-btn-hover',
      fg ? `color-mix(in srgb, ${fg} 15%, transparent)` : '',
    )
  })
}

// ----- Lifecycle -----

watch(
  () => props.open,
  (v) => {
    if (v) {
      titleValue.value = props.note?.title ?? ''
      contentValue.value = props.note?.content ?? ''
      colorValue.value = props.note?.color ?? ''
      editing.value = !props.note
      nextTick(applyDialogColor)
    }
  },
  { immediate: true },
)

watch(colorValue, applyDialogColor)

// ----- Auto-save with debounce -----

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function scheduleSave() {
  if (!isExisting.value) return
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(flushSave, 800)
}

function flushSave() {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
  const title = titleValue.value.trim()
  if (!title) return
  emit('save', {
    title,
    content: contentValue.value,
    color: colorValue.value,
  })
}

watch(titleValue, scheduleSave)
watch(contentValue, scheduleSave)

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
})

// ----- Color toggle (saves immediately for existing) -----

function toggleColor(c: string) {
  colorValue.value = colorValue.value === c ? '' : c
  if (isExisting.value) {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = null
    const title = titleValue.value.trim()
    if (!title) return
    emit('save', {
      title,
      content: contentValue.value,
      color: colorValue.value,
    })
  }
}

// ----- Auto-resize textarea -----

function getTextarea(): HTMLTextAreaElement | null {
  const el = contentInputRef.value?.$el as HTMLElement | undefined
  return el?.querySelector('textarea') ?? null
}

function autoResizeTextarea() {
  const ta = getTextarea()
  if (!ta) return
  ta.style.height = 'auto'
  ta.style.height = Math.min(ta.scrollHeight, MAX_TEXTAREA_HEIGHT) + 'px'
  ta.style.overflowY = ta.scrollHeight > MAX_TEXTAREA_HEIGHT ? 'auto' : 'hidden'
}

watch(contentValue, () => {
  if (editing.value) {
    nextTick(autoResizeTextarea)
  }
})

// ----- Edit mode -----

function toggleEditing() {
  if (editing.value) {
    editing.value = false
    if (isExisting.value) {
      flushSave()
    }
  } else {
    startEditing()
  }
}

function startEditing(focus?: 'title' | 'content') {
  // Capture rendered content height before switching to edit
  const renderedHeight = renderedContentRef.value?.offsetHeight ?? 0
  editing.value = true
  if (focus) {
    nextTick(() => {
      if (focus === 'title') {
        titleInputRef.value?.focus()
      } else {
        const el = contentInputRef.value?.$el as HTMLElement | undefined
        const ta = el?.querySelector('textarea') as HTMLElement | null
        ta?.focus()
      }
    })
  }
  // Size textarea to match rendered content
  nextTick(() => {
    const ta = getTextarea()
    if (!ta) return
    const targetHeight = Math.max(renderedHeight, ta.scrollHeight, 80)
    ta.style.height = Math.min(targetHeight, MAX_TEXTAREA_HEIGHT) + 'px'
    ta.style.overflowY = targetHeight > MAX_TEXTAREA_HEIGHT ? 'auto' : 'hidden'
  })
}

// ----- Close -----

function onClose(v: boolean) {
  if (!v) {
    if (isExisting.value) {
      flushSave()
    } else {
      const title = titleValue.value.trim()
      if (title) {
        emit('save', {
          title,
          content: contentValue.value,
          color: colorValue.value,
        })
      }
    }
    emit('update:open', false)
  }
}

const strings = {
  titlePlaceholder: t('pantry', 'Note title'),
  contentPlaceholder: t('pantry', 'Write your note here …'),
  edit: t('pantry', 'Edit'),
  view: t('pantry', 'Preview'),
  untitled: t('pantry', 'Untitled note'),
  noContent: t('pantry', 'Click to add content …'),
}
</script>

<style scoped lang="scss">
.note-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-height: 200px;
  }

  &__title-text {
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    cursor: text;
    line-height: 1.3;
    text-align: start;
    color: inherit;

    &:hover {
      opacity: 0.7;
    }
  }

  &__title-input,
  &__title-input:focus,
  &__title-input:focus-visible,
  &__title-input:hover {
    font-size: 1.3rem !important;
    font-weight: 600 !important;
    line-height: 1.3 !important;
    width: 100% !important;
    border: 0 !important;
    border-width: 0 !important;
    outline: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    color: inherit !important;
    padding: 0 !important;
    margin: 0 !important;
    font-family: inherit !important;
  }

  &__title-input::placeholder {
    opacity: 0.5;
    color: inherit;
  }

  &__content-input {
    :deep(label),
    :deep(.input-field__label) {
      display: none !important;
    }
  }

  &__content {
    flex: 1;
    cursor: text;
    min-height: 100px;

    &:hover {
      opacity: 0.85;
    }
  }

  &__rendered {
    line-height: 1.6;
    font-size: 0.95rem;

    :deep(*) {
      color: inherit !important;
    }
  }

  &__empty {
    color: var(--color-text-maxcontrast);
    font-style: italic;
    margin: 0;
  }

  &__color {
    padding-top: 0.5rem;
    border-top: 1px solid rgba(128, 128, 128, 0.2);
  }

  &__swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 2px;
  }

  &__swatch {
    width: 24px;
    height: 24px;
    min-width: 24px;
    min-height: 24px;
    max-width: 24px;
    max-height: 24px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    padding: 0;
    box-sizing: content-box;
    transition:
      border-color 0.15s ease,
      transform 0.15s ease;

    &:hover {
      transform: scale(1.15);
    }

    &--active {
      transform: scale(1.15);
    }
  }
}
</style>

<style lang="scss">
// Override NC's scoped textarea styles which use [data-v-*] + !important
// We match with .textarea__input[data-v-*] to get equal specificity
.note-dialog__content-input {
  .textarea__input[class],
  .textarea__input[class]:focus,
  .textarea__input[class]:focus-visible,
  .textarea__input[class]:focus-within,
  .textarea__input[class]:hover,
  .textarea__input[class]:active,
  .textarea__input[class]:focus-within:not([disabled]),
  .textarea__input[class]:active:not([disabled]) {
    border: 0 !important;
    outline: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
    font-size: 0.95rem !important;
    line-height: 1.6 !important;
    color: inherit !important;
    border-radius: 0 !important;
  }

  .textarea__input[class]::placeholder {
    opacity: 0.5;
    color: inherit !important;
  }

  .textarea__label {
    display: none !important;
  }

  .textarea__main-wrapper {
    padding: 0 !important;
  }

  .textarea {
    margin-block-start: 0 !important;
  }
}

// Button hover backgrounds inside the note dialog modal
.modal-container:has(.note-dialog__body) {
  .button-vue--vue-tertiary:hover,
  .button-vue--tertiary:hover,
  .modal-container__close:hover {
    background-color: var(--note-btn-hover, rgba(0, 0, 0, 0.08)) !important;
  }
}
</style>
