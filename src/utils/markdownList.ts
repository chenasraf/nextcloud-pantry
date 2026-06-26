import { t } from '@nextcloud/l10n'
import type { Category, ChecklistItem } from '@/api/types'

// Separator between the item name and its quantity/description on a line.
// Spaced em dash, matching the export format. Import strips everything from
// the first occurrence so names round-trip cleanly.
const SEP = ' — '

export interface ParsedMarkdownItem {
  name: string
  done: boolean
}

function isoDate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function formatItemLine(item: ChecklistItem): string {
  const checkbox = item.done ? '[x]' : '[ ]'
  const parts = [item.name.trim()]
  if (item.quantity?.trim()) parts.push(item.quantity.trim())
  if (item.description?.trim()) parts.push(item.description.trim().replace(/\s*\n\s*/g, ' '))
  return `- ${checkbox} ${parts.join(SEP)}`
}

/**
 * Build a Markdown document for a list: a title, an export date, then items
 * grouped under `## Category` headings (categories in first-seen order,
 * uncategorized items last).
 */
export function buildListMarkdown(
  listName: string,
  items: ChecklistItem[],
  categoryFor: (id: number | null) => Category | null,
  now: Date = new Date(),
): string {
  const lines: string[] = [
    `# ${listName}`,
    '',
    `_${t('pantry', 'Exported {date}', { date: isoDate(now) })}_`,
    '',
  ]

  const groups = new Map<number | null, ChecklistItem[]>()
  for (const item of items) {
    const key = item.categoryId ?? null
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key)!.push(item)
  }

  const uncategorizedLabel = t('pantry', 'Uncategorized')
  const keys = [...groups.keys()].filter((k): k is number => k !== null)
  if (groups.has(null)) keys.push(null as unknown as number)

  for (const key of keys) {
    const heading =
      key === null ? uncategorizedLabel : (categoryFor(key)?.name ?? uncategorizedLabel)
    lines.push(`## ${heading}`)
    for (const item of groups.get(key)!) lines.push(formatItemLine(item))
    lines.push('')
  }

  return lines.join('\n').trimEnd() + '\n'
}

// Bullet (-, *, +) or ordered (1. / 1)) list item; captures the trailing text.
const ITEM_RE = /^\s*(?:[-*+]|\d+[.)])\s+(.+)$/
// A leading `[ ]` / `[x]` checkbox on the captured text.
const CHECKBOX_RE = /^\[(.)\]\s*(.*)$/

/**
 * Parse list items out of a Markdown document. Headings, the export-date line
 * and any other prose are ignored. The quantity/description suffix produced by
 * {@link buildListMarkdown} is stripped so only the item name is returned.
 */
export function parseMarkdownItems(text: string): ParsedMarkdownItem[] {
  const out: ParsedMarkdownItem[] = []
  for (const line of text.split(/\r?\n/)) {
    const m = ITEM_RE.exec(line)
    if (!m) continue
    let name = m[1].trim()
    let done = false
    const cb = CHECKBOX_RE.exec(name)
    if (cb) {
      done = /x/i.test(cb[1])
      name = cb[2].trim()
    }
    const sepIdx = name.indexOf(SEP)
    if (sepIdx !== -1) name = name.slice(0, sepIdx).trim()
    if (!name) continue
    out.push({ name, done })
  }
  return out
}
