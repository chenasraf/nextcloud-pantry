import { describe, expect, it } from 'vitest'

import { linkifySegments } from './linkify'

describe('linkifySegments', () => {
  it('returns a single text segment when there are no links', () => {
    expect(linkifySegments('just some text')).toEqual([{ text: 'just some text' }])
  })

  it('returns a single text segment for empty input', () => {
    expect(linkifySegments('')).toEqual([{ text: '' }])
  })

  it('links a bare URL', () => {
    expect(linkifySegments('https://example.com')).toEqual([
      { text: 'https://example.com', href: 'https://example.com' },
    ])
  })

  it('keeps surrounding text as plain segments', () => {
    expect(linkifySegments('see https://example.com now')).toEqual([
      { text: 'see ' },
      { text: 'https://example.com', href: 'https://example.com' },
      { text: ' now' },
    ])
  })

  it('detects www URLs and prefixes a scheme in the href', () => {
    expect(linkifySegments('visit www.example.com')).toEqual([
      { text: 'visit ' },
      { text: 'www.example.com', href: 'http://www.example.com' },
    ])
  })

  it('detects email addresses as mailto links', () => {
    expect(linkifySegments('mail me at a@b.com')).toEqual([
      { text: 'mail me at ' },
      { text: 'a@b.com', href: 'mailto:a@b.com' },
    ])
  })

  it('handles multiple links in one string', () => {
    expect(linkifySegments('https://a.com and https://b.com')).toEqual([
      { text: 'https://a.com', href: 'https://a.com' },
      { text: ' and ' },
      { text: 'https://b.com', href: 'https://b.com' },
    ])
  })
})
