// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

/** Matches a GFM task-list marker at the start of a list item: `- [ ]`, `* [x]`, `1. [X]`. */
const TASK_RE = /^([ \t]*(?:[-*+]|\d+[.)])[ \t]+\[)([ xX])(\])/

/**
 * Toggle the checked state of the task-list item at the given render index.
 *
 * `index` is the position of the checkbox as rendered by NcRichText (document
 * order). We count task items line by line while skipping fenced code blocks,
 * so a `- [ ]` inside a ``` fence — which NcRichText does not render as an
 * interactive checkbox — never throws off the alignment.
 *
 * Returns the text unchanged when the index does not resolve to a task item.
 */
export function toggleMarkdownTask(text: string, index: number): string {
  const lines = text.split('\n')
  let inFence = false
  let fenceChar = ''
  let seen = -1

  for (let ln = 0; ln < lines.length; ln++) {
    const line = lines[ln]
    const fence = line.match(/^\s*(`{3,}|~{3,})/)
    if (fence) {
      if (!inFence) {
        inFence = true
        fenceChar = fence[1][0]
      } else if (line.trimStart().startsWith(fenceChar)) {
        inFence = false
      }
      continue
    }
    if (inFence) {
      continue
    }

    const m = line.match(TASK_RE)
    if (m) {
      seen++
      if (seen === index) {
        const flipped = m[2] === ' ' ? 'x' : ' '
        lines[ln] = line.replace(TASK_RE, `$1${flipped}$3`)
        return lines.join('\n')
      }
    }
  }

  return text
}
