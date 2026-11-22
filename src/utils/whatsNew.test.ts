import { describe, expect, it } from 'vitest'

import { compareVersions, whatsNewSince, type OnboardingStep } from './whatsNew'

describe('compareVersions', () => {
  it('compares each part numerically', () => {
    expect(compareVersions('0.10.0', '0.9.0')).toBeGreaterThan(0)
    expect(compareVersions('0.40.0', '0.40.1')).toBeLessThan(0)
    expect(compareVersions('1.0.0', '1.0.0')).toBe(0)
  })
})

describe('whatsNewSince', () => {
  const notes = {
    '0.39.0': 'Old notes',
    '0.40.0': 'Forty',
    '0.41.0': 'Forty-one',
    '0.42.0': 'Forty-two',
    '0.43.0': 'Not installed yet',
  }
  const step: OnboardingStep = { version: '0.40.0', title: 'Smart input', body: 'Type 2 L milk' }

  it('shows every version after the seen one up to the installed one, newest first', () => {
    const entries = whatsNewSince('0.39.0', '0.42.0', notes, [step])
    expect(entries.map((e) => e.version)).toEqual(['0.42.0', '0.41.0', '0.40.0'])
    expect(entries[2]?.steps).toEqual([step])
    expect(entries[0]?.steps).toEqual([])
  })

  it('shows nothing when the installed version was already seen', () => {
    expect(whatsNewSince('0.42.0', '0.42.0', notes, [step])).toEqual([])
  })

  it('skips versions without notes or steps', () => {
    const entries = whatsNewSince('0.39.0', '0.41.0', { '0.41.0': '  ' }, [])
    expect(entries).toEqual([])
  })

  it('shows a step for a version that has no notes', () => {
    const entries = whatsNewSince('0.39.0', '0.40.0', {}, [step])
    expect(entries).toEqual([{ version: '0.40.0', notes: null, steps: [step] }])
  })

  it('leaves out the step for someone who updated past it already', () => {
    const entries = whatsNewSince('0.40.0', '0.41.0', notes, [step])
    expect(entries.flatMap((e) => e.steps)).toEqual([])
  })
})
