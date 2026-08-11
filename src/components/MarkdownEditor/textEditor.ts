/**
 * Thin, typed wrapper around the Nextcloud Text app's public editor API
 * (`window.OCA.Text`). Text exposes `createMarkdownContentEditor` (API >= 1.4)
 * for editing a plain markdown string with no backing file — exactly what we
 * need for item descriptions and notes. Older Text versions only ship the
 * (now deprecated) `createEditor`, which routes to the same content editor
 * when called without a `fileId`, so we fall back to it.
 *
 * The Text app must be installed and enabled, and the page must have dispatched
 * the `LoadEditor` event (see PageController) for `window.OCA.Text` to exist.
 * When it does not, {@link isWysiwygAvailable} returns false and callers fall
 * back to a plain markdown textarea.
 */

/** Subset of the TextEditorEmbed instance we use. */
export interface ContentEditorInstance {
  setContent(content: string): void
  setReadOnly(value: boolean): void
  insertAtCursor(content: string): void
  focus(): void
  destroy(): void
}

interface CreateOptions {
  el: HTMLElement
  content?: string
  readOnly?: boolean
  autofocus?: boolean
  onUpdate?: (payload: { markdown: string }) => void
  onLoaded?: () => void
}

type EditorFactory = (options: CreateOptions) => Promise<ContentEditorInstance>

interface OCATextGlobal {
  createMarkdownContentEditor?: EditorFactory
  createEditor?: EditorFactory
}

function ocaText(): OCATextGlobal | undefined {
  return (window as unknown as { OCA?: { Text?: OCATextGlobal } }).OCA?.Text
}

function factory(): EditorFactory | undefined {
  const text = ocaText()
  return text?.createMarkdownContentEditor ?? text?.createEditor
}

/** Whether the Text app's WYSIWYG content editor can be mounted. */
export function isWysiwygAvailable(): boolean {
  return factory() !== undefined
}

/**
 * Mount a Text WYSIWYG editor for a markdown string into `el`.
 *
 * @param options editor options
 * @return the editor instance, or null if Text is unavailable
 */
export async function createContentEditor(options: {
  el: HTMLElement
  content: string
  readOnly?: boolean
  autofocus?: boolean
  onUpdate: (markdown: string) => void
  onLoaded?: () => void
}): Promise<ContentEditorInstance | null> {
  const create = factory()
  if (!create) {
    return null
  }
  return create({
    el: options.el,
    content: options.content,
    readOnly: options.readOnly ?? false,
    autofocus: options.autofocus ?? false,
    onUpdate: ({ markdown }) => options.onUpdate(markdown),
    onLoaded: options.onLoaded,
  })
}
