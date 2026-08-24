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
      <div class="note-dialog__title-row">
        <input
          v-if="editing"
          ref="titleInputRef"
          v-model="titleValue"
          :placeholder="strings.titlePlaceholder"
          class="note-dialog__title-input"
          dir="auto"
        />
        <h2 v-else class="note-dialog__title-text" dir="auto">
          {{ titleValue || strings.untitled }}
        </h2>
        <NcCheckboxRadioSwitch
          v-if="editing && richAvailable"
          v-model="richMode"
          type="switch"
          class="note-dialog__rich-toggle"
          :style="richToggleStyle"
        >
          {{ strings.richText }}
        </NcCheckboxRadioSwitch>
        <div class="note-dialog__toolbar">
          <NcButton
            v-if="note && !editing"
            variant="tertiary"
            :aria-label="strings.share"
            :title="strings.share"
            @click="shareDialogOpen = true"
          >
            <template #icon>
              <ShareVariantIcon :size="20" />
            </template>
          </NcButton>
          <NcButton
            variant="tertiary"
            :aria-label="editing ? strings.view : strings.edit"
            :title="editing ? strings.view : strings.edit"
            @click="toggleEditing"
          >
            <template #icon>
              <EyeIcon v-if="editing" :size="20" />
              <PencilIcon v-else :size="20" />
            </template>
          </NcButton>
        </div>
      </div>

      <MarkdownEditor
        v-if="editing"
        ref="contentInputRef"
        v-model="contentValue"
        v-model:rich="richMode"
        :toolbar="false"
        :placeholder="strings.contentPlaceholder"
        :max-height="MAX_TEXTAREA_HEIGHT"
        class="note-dialog__content-input"
        dir="auto"
        autocomplete="off"
        @available="richAvailable = $event"
      />
      <div v-else class="note-dialog__content" dir="auto">
        <div v-if="contentValue" class="note-dialog__rendered">
          <NcRichText :text="contentValue" :use-markdown="true" :use-extended-markdown="true" />
        </div>
        <p v-else class="note-dialog__empty">{{ strings.noContent }}</p>
      </div>

      <!-- Color swatches (edit mode only) -->
      <div v-if="editing" class="note-dialog__color">
        <div class="note-dialog__swatches">
          <button
            type="button"
            class="note-dialog__swatch note-dialog__swatch--none"
            :class="{ 'note-dialog__swatch--active': colorValue === '' }"
            :style="{ borderColor: colorValue === '' ? 'var(--color-main-text)' : 'transparent' }"
            :aria-label="strings.noColor"
            @click="toggleColor('')"
          />
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

      <ShareEditor
        v-if="note && editing"
        class="note-dialog__share"
        :house-id="note.houseId"
        entity-type="note"
        :entity-id="note.id"
        :can-manage="canManageShares"
      />
    </div>
  </NcDialog>

  <NcDialog
    v-if="note"
    :open="shareDialogOpen"
    :name="strings.shareTitle"
    size="normal"
    @update:open="shareDialogOpen = $event"
  >
    <ShareEditor
      class="note-dialog__share note-dialog__share--dialog"
      :house-id="note.houseId"
      entity-type="note"
      :entity-id="note.id"
      :can-manage="canManageShares"
    />
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { MarkdownEditor, isWysiwygAvailable } from '@/components/MarkdownEditor'
import PencilIcon from '@icons/Pencil.vue'
import EyeIcon from '@icons/Eye.vue'
import ShareVariantIcon from '@icons/ShareVariant.vue'
import { contrastColor, noteColorOptions } from './noteColors'
import type { Note } from '@/api/types'
import { ShareEditor } from '@/components/ShareEditor'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { getCurrentUserId } from '@/utils/currentUser'

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
const shareDialogOpen = ref(false)
const dialogRef = ref<InstanceType<typeof NcDialog> | null>(null)
const titleInputRef = ref<HTMLInputElement | null>(null)
const contentInputRef = ref<InstanceType<typeof MarkdownEditor> | null>(null)

// Whether the Text (WYSIWYG) editor is available, and the current rich/source
// mode — the toggle lives up on the title row and drives the MarkdownEditor.
const richAvailable = ref(isWysiwygAvailable())
const richMode = ref(true)

const MAX_TEXTAREA_HEIGHT = 400

const { isAdmin } = useCurrentHouse()
const isExisting = computed(() => !!props.note)
const canManageShares = computed(
  () => isAdmin.value || props.note?.createdBy === getCurrentUserId(),
)
const swatchBorderColor = computed(() =>
  colorValue.value ? contrastColor(colorValue.value) : 'var(--color-main-text)',
)
const colorOptions = noteColorOptions

// The rich-text switch's on-state uses --color-primary-element (the theme
// accent), which can be invisible against a coloured note. On coloured notes,
// retint it with the note's foreground colour; on default notes keep the theme.
const richToggleStyle = computed((): Record<string, string> => {
  if (!colorValue.value) return {}
  const fg = contrastColor(colorValue.value)
  return {
    '--color-primary-element': fg,
    '--color-primary-element-hover': fg,
    '--color-primary-element-light': `color-mix(in srgb, ${fg} 30%, transparent)`,
    '--color-primary-element-text': colorValue.value,
  }
})

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
    // Nextcloud components (including the embedded Text editor) theme themselves
    // through these CSS variables, so overriding them on the dialog container is
    // what makes the rich editor's background and text follow the note colour —
    // far more robust than targeting the editor's internal class names.
    const themeVars: Record<string, string> = {
      '--color-main-text': fg,
      '--color-main-background': colorValue.value,
      // The Text editor's sticky menubar uses the translucent background var
      // (behind a backdrop blur); tint it with the note colour so it blends.
      '--color-main-background-translucent': `color-mix(in srgb, ${colorValue.value} 80%, transparent)`,
      '--color-text-maxcontrast': `color-mix(in srgb, ${fg} 65%, transparent)`,
      '--color-border': `color-mix(in srgb, ${fg} 25%, transparent)`,
      '--color-border-dark': `color-mix(in srgb, ${fg} 35%, transparent)`,
      '--color-background-hover': `color-mix(in srgb, ${fg} 12%, transparent)`,
      '--color-background-dark': `color-mix(in srgb, ${fg} 18%, transparent)`,
    }
    if (colorValue.value) {
      container.style.background = colorValue.value
      container.style.color = fg
      for (const [key, value] of Object.entries(themeVars)) {
        container.style.setProperty(key, value)
      }
    } else {
      container.style.background = ''
      container.style.color = ''
      for (const key of Object.keys(themeVars)) {
        container.style.removeProperty(key)
      }
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
      shareDialogOpen.value = false
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

// ----- Edit mode -----

function toggleEditing() {
  if (editing.value) {
    editing.value = false
    if (isExisting.value) {
      flushSave()
    }
  } else {
    startEditing('content')
  }
}

function startEditing(focus?: 'title' | 'content') {
  editing.value = true
  if (focus) {
    nextTick(() => {
      if (focus === 'title') {
        titleInputRef.value?.focus()
      } else {
        contentInputRef.value?.focus()
      }
    })
  }
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
  // TRANSLATORS: Toggle button label to switch the note from editing into read-only preview mode.
  view: t('pantry', 'Preview'),
  // TRANSLATORS: Button that opens the sharing dialog for the note.
  share: t('pantry', 'Share'),
  // TRANSLATORS: Title of the dialog that shows who the note is shared with.
  shareTitle: t('pantry', 'Share note'),
  untitled: t('pantry', 'Untitled note'),
  noContent: t('pantry', 'No content yet'),
  noColor: t('pantry', 'Default (no color)'),
  // TRANSLATORS: Label for a toggle switch that turns the formatted rich-text
  // (WYSIWYG) editor on or off (versus editing raw Markdown).
  richText: t('pantry', 'Rich text'),
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

  // Title and the rich-text toggle share one row; the toggle sits at the end,
  // just inside the space reserved for the dialog's close button.
  // Top-align so the toolbar buttons line up with the dialog's close button;
  // the title gets its own top spacing instead of the whole row.
  &__title-row {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding-right: var(--default-clickable-area, 44px);
    margin-bottom: 0.25rem;
  }

  &__rich-toggle {
    flex: 0 0 auto;
    white-space: nowrap;
  }

  &__toolbar {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex: 0 0 auto;
    padding-top: 1px;
  }

  &__title-text {
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    // Nudge the title down so it sits centered against the top-aligned buttons.
    padding-top: 0.5rem;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1.3;
    text-align: start;
    color: inherit;
  }

  &__title-input,
  &__title-input:focus,
  &__title-input:focus-visible,
  &__title-input:hover {
    font-size: 1.3rem !important;
    font-weight: 600 !important;
    line-height: 1.3 !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    width: auto !important;
    border: 0 !important;
    border-width: 0 !important;
    outline: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    color: inherit !important;
    // Match the view-mode title's top nudge so it centers against the buttons.
    padding: 0.5rem 0 0 0 !important;
    margin: 0 !important;
    font-family: inherit !important;
    box-sizing: border-box !important;
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
    min-height: 100px;
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

  &__share {
    margin-bottom: 0.75rem;

    &--dialog {
      margin-bottom: 1rem;
    }
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

    &--none {
      background: var(--color-background-hover);
      // Diagonal red line to indicate "no color" (uses a fixed pure red so it
      // stays visible in both light and dark themes).
      background-image: linear-gradient(
        135deg,
        transparent 40%,
        #ff0000 40%,
        #ff0000 60%,
        transparent 60%
      );
      background-size: 100% 100%;
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
