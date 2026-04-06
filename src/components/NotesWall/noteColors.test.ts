import { describe, expect, it } from 'vitest'
import { contrastColor, noteColorOptions } from './noteColors'

describe('noteColors', () => {
  describe('contrastColor', () => {
    it('returns black for light colors', () => {
      expect(contrastColor('#ffffff')).toBe('#000000')
      expect(contrastColor('#ffeb3b')).toBe('#000000') // yellow
      expect(contrastColor('#cddc39')).toBe('#000000') // lime
      expect(contrastColor('#8bc34a')).toBe('#000000') // light green
      expect(contrastColor('#ffc107')).toBe('#000000') // amber
    })

    it('returns white for dark colors', () => {
      expect(contrastColor('#000000')).toBe('#ffffff')
      expect(contrastColor('#f44336')).toBe('#ffffff') // red
      expect(contrastColor('#9c27b0')).toBe('#ffffff') // purple
      expect(contrastColor('#673ab7')).toBe('#ffffff') // deep purple
      expect(contrastColor('#3f51b5')).toBe('#ffffff') // indigo
    })

    it('returns black for orange (bright but saturated)', () => {
      expect(contrastColor('#ff9800')).toBe('#000000')
    })

    it('returns white for teal', () => {
      expect(contrastColor('#009688')).toBe('#ffffff')
    })

    it('returns white for blue', () => {
      expect(contrastColor('#2196f3')).toBe('#ffffff')
    })
  })

  describe('noteColorOptions', () => {
    it('has 16 color options', () => {
      expect(noteColorOptions).toHaveLength(16)
    })

    it('all options are valid hex colors', () => {
      for (const c of noteColorOptions) {
        expect(c).toMatch(/^#[0-9a-fA-F]{6}$/)
      }
    })
  })
})
