import * as linkify from 'linkifyjs'

/**
 * A slice of text that is either plain text or a hyperlink.
 */
export interface TextSegment {
  text: string
  href?: string
}

/**
 * Splits free text into ordered segments, marking any detected URLs or email
 * addresses as links. Plain text (no matches) returns a single text segment.
 *
 * Splitting into segments rather than producing an HTML string keeps rendering
 * safe from injection: the surrounding text is always bound as text, only the
 * href of a matched link is derived from the input.
 *
 * @param text - The text to scan for links
 * @returns Ordered segments covering the whole input
 */
export function linkifySegments(text: string): TextSegment[] {
  const matches = linkify.find(text)
  if (matches.length === 0) {
    return [{ text }]
  }

  const segments: TextSegment[] = []
  let cursor = 0
  for (const match of matches) {
    if (match.start > cursor) {
      segments.push({ text: text.slice(cursor, match.start) })
    }
    segments.push({ text: match.value, href: match.href })
    cursor = match.end
  }
  if (cursor < text.length) {
    segments.push({ text: text.slice(cursor) })
  }
  return segments
}
