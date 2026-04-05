/**
 * Example test file demonstrating pure TypeScript/utility function testing.
 *
 * This shows how to:
 * - Use describe/it/expect structure
 * - Test various inputs including edge cases
 * - Handle null/undefined values
 */
import { describe, expect, it } from 'vitest'

import { capitalize, formatFileSize, truncate } from './string'

describe('truncate', () => {
  it('returns empty string for null input', () => {
    expect(truncate(null, 10)).toBe('')
  })

  it('returns empty string for undefined input', () => {
    expect(truncate(undefined, 10)).toBe('')
  })

  it('returns the original string if shorter than maxLength', () => {
    expect(truncate('hello', 10)).toBe('hello')
  })

  it('returns the original string if equal to maxLength', () => {
    expect(truncate('hello', 5)).toBe('hello')
  })

  it('truncates and adds ellipsis when string exceeds maxLength', () => {
    expect(truncate('hello world', 8)).toBe('hello...')
  })

  it('uses custom ellipsis when provided', () => {
    expect(truncate('hello world', 8, '…')).toBe('hello w…')
  })

  it('returns empty string for negative maxLength', () => {
    expect(truncate('hello', -1)).toBe('')
  })

  it('handles zero maxLength', () => {
    expect(truncate('hello', 0)).toBe('')
  })

  it('handles maxLength smaller than ellipsis', () => {
    expect(truncate('hello world', 2)).toBe('..')
  })

  it('handles empty string input', () => {
    expect(truncate('', 10)).toBe('')
  })
})

describe('capitalize', () => {
  it('returns empty string for null input', () => {
    expect(capitalize(null)).toBe('')
  })

  it('returns empty string for undefined input', () => {
    expect(capitalize(undefined)).toBe('')
  })

  it('returns empty string for empty string input', () => {
    expect(capitalize('')).toBe('')
  })

  it('capitalizes a lowercase string', () => {
    expect(capitalize('hello')).toBe('Hello')
  })

  it('keeps already capitalized string unchanged', () => {
    expect(capitalize('Hello')).toBe('Hello')
  })

  it('capitalizes single character', () => {
    expect(capitalize('a')).toBe('A')
  })

  it('handles strings starting with numbers', () => {
    expect(capitalize('123abc')).toBe('123abc')
  })

  it('only capitalizes the first character', () => {
    expect(capitalize('hELLO')).toBe('HELLO')
  })
})

describe('formatFileSize', () => {
  it('returns "0 B" for null input', () => {
    expect(formatFileSize(null)).toBe('0 B')
  })

  it('returns "0 B" for undefined input', () => {
    expect(formatFileSize(undefined)).toBe('0 B')
  })

  it('returns "0 B" for zero bytes', () => {
    expect(formatFileSize(0)).toBe('0 B')
  })

  it('returns "0 B" for negative bytes', () => {
    expect(formatFileSize(-100)).toBe('0 B')
  })

  it('formats bytes correctly', () => {
    expect(formatFileSize(500)).toBe('500 B')
  })

  it('formats kilobytes correctly', () => {
    expect(formatFileSize(1024)).toBe('1 KB')
    expect(formatFileSize(1536)).toBe('1.5 KB')
  })

  it('formats megabytes correctly', () => {
    expect(formatFileSize(1048576)).toBe('1 MB')
    expect(formatFileSize(1572864)).toBe('1.5 MB')
  })

  it('formats gigabytes correctly', () => {
    expect(formatFileSize(1073741824)).toBe('1 GB')
  })

  it('respects custom decimal places', () => {
    expect(formatFileSize(1536, 0)).toBe('2 KB')
    expect(formatFileSize(1536, 1)).toBe('1.5 KB')
    expect(formatFileSize(1536, 3)).toBe('1.5 KB')
  })

  it('handles Infinity', () => {
    expect(formatFileSize(Infinity)).toBe('0 B')
  })

  it('handles NaN', () => {
    expect(formatFileSize(NaN)).toBe('0 B')
  })
})
