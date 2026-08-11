<template>
  <div class="markdown-editor" :class="{ 'markdown-editor--rich': mode === 'wysiwyg' }">
    <div v-if="toolbar && (label || showToggle)" class="markdown-editor__bar">
      <span v-if="label" class="markdown-editor__label">{{ label }}</span>
      <NcCheckboxRadioSwitch
        v-if="showToggle"
        v-model="richMode"
        type="switch"
        class="markdown-editor__switch"
      >
        {{ strings.richText }}
      </NcCheckboxRadioSwitch>
    </div>

    <!-- WYSIWYG mount point — kept in the DOM (v-show) so the Text editor can
         mount into a stable element; only created while this mode is active. -->
    <div v-show="mode === 'wysiwyg'" ref="wysiwygEl" class="markdown-editor__wysiwyg" />

    <!-- Raw markdown source -->
    <AutoResizeTextarea
      v-if="mode === 'source'"
      ref="textareaRef"
      v-model="model"
      v-bind="$attrs"
      :placeholder="placeholder"
      :max-height="maxHeight"
      :dir="dir"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { AutoResizeTextarea } from '@/components/AutoResizeTextarea'
import { createContentEditor, isWysiwygAvailable, type ContentEditorInstance } from './textEditor'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    label?: string
    placeholder?: string
    maxHeight?: number
    dir?: string
    /** Show the built-in toolbar (field label + rich/source switch). Disable to
     * place the switch elsewhere via `v-model:rich` + `available`. */
    toolbar?: boolean
  }>(),
  { maxHeight: 400, dir: 'auto', toolbar: true },
)

const model = defineModel<string>({ default: '' })
/** Two-way binding for the rich/source mode: true = WYSIWYG, false = source. */
const richModel = defineModel<boolean | undefined>('rich', { default: undefined })

const emit = defineEmits<{
  /** Emitted once availability of the WYSIWYG editor is known. */
  available: [value: boolean]
}>()

type Mode = 'wysiwyg' | 'source'
const MODE_KEY = 'pantry:markdown-editor-mode'

const wysiwygAvailable = ref(false)
const mode = ref<Mode>('source')
const wysiwygEl = ref<HTMLElement | null>(null)
const textareaRef = ref<InstanceType<typeof AutoResizeTextarea> | null>(null)

const showToggle = computed(() => wysiwygAvailable.value)

// Non-reactive editor state. `lastMarkdown` tracks the content last synced
// to/from the Text editor so external model changes and editor updates don't
// ping-pong into an infinite loop.
let editor: ContentEditorInstance | null = null
let generation = 0
let lastMarkdown = ''

function preferredMode(): Mode {
  const saved = localStorage.getItem(MODE_KEY)
  return saved === 'source' || saved === 'wysiwyg' ? saved : 'wysiwyg'
}

async function mountEditor() {
  const el = wysiwygEl.value
  if (!el) return
  const gen = ++generation
  lastMarkdown = model.value
  const instance = await createContentEditor({
    el,
    content: model.value,
    onUpdate: (markdown) => {
      lastMarkdown = markdown
      if (markdown !== model.value) model.value = markdown
    },
  })
  // Torn down or switched away while the factory promise was in flight.
  if (gen !== generation) {
    instance?.destroy()
    return
  }
  editor = instance
}

function destroyEditor() {
  generation++ // invalidate any in-flight mountEditor()
  editor?.destroy()
  editor = null
}

async function setMode(next: Mode) {
  if (next === mode.value) return
  if (next === 'wysiwyg') {
    mode.value = 'wysiwyg'
    await nextTick()
    await mountEditor()
  } else {
    // The editor keeps `model` in sync via onUpdate, so the source textarea
    // already shows the latest markdown — just tear the editor down.
    destroyEditor()
    mode.value = 'source'
  }
  localStorage.setItem(MODE_KEY, next)
  richModel.value = next === 'wysiwyg'
}

const richMode = computed<boolean>({
  get: () => mode.value === 'wysiwyg',
  set: (value) => {
    setMode(value ? 'wysiwyg' : 'source')
  },
})

// Allow a parent-provided `v-model:rich` to drive the mode.
watch(richModel, (value) => {
  if (value === undefined) return
  setMode(value ? 'wysiwyg' : 'source')
})

// Reflect external model changes (e.g. dialog reopened with new content) into
// the live editor without echoing them straight back through onUpdate.
watch(model, (value) => {
  if (mode.value === 'wysiwyg' && editor && value !== lastMarkdown) {
    lastMarkdown = value
    editor.setContent(value)
  }
})

onMounted(async () => {
  wysiwygAvailable.value = isWysiwygAvailable()
  emit('available', wysiwygAvailable.value)
  let initial: Mode
  if (!wysiwygAvailable.value) {
    initial = 'source'
  } else if (richModel.value !== undefined) {
    initial = richModel.value ? 'wysiwyg' : 'source'
  } else {
    initial = preferredMode()
  }
  mode.value = initial
  richModel.value = initial === 'wysiwyg'
  if (initial === 'wysiwyg') {
    await nextTick()
    await mountEditor()
  }
})

onBeforeUnmount(destroyEditor)

/** Focus whichever editor is currently active. */
function focus() {
  if (mode.value === 'wysiwyg') {
    editor?.focus()
  } else {
    textareaRef.value?.getTextareaEl()?.focus()
  }
}

defineExpose({ focus })

const strings = {
  // TRANSLATORS: Label for a toggle switch; when on, the field is edited with a
  // formatted rich-text (WYSIWYG) editor instead of raw Markdown source.
  richText: t('pantry', 'Rich text'),
}
</script>

<style scoped lang="scss">
.markdown-editor {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-height: var(--default-clickable-area, 44px);
  }

  &__label {
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  // Keep the switch pinned to the trailing edge whether or not a field label
  // is present.
  &__switch {
    margin-inline-start: auto;
  }

  &__wysiwyg {
    // The Text editor brings its own styling; give it a sensible min-height so
    // an empty editor is still a comfortable click target.
    min-height: 4rem;

    :deep(.text-editor) {
      min-height: 4rem;
    }
  }
}
</style>
