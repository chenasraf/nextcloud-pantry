import { beforeEach, describe, expect, it, vi } from 'vitest'

const l10n = vi.hoisted(() => ({
  getLanguage: vi.fn(() => 'fr'),
  setLanguage: vi.fn(),
  unregister: vi.fn(),
  isRTL: vi.fn((language: string) => language === 'ar'),
}))

const bundle = vi.hoisted(() => ({
  APP_ID: 'pantry',
  loadLanguageBundle: vi.fn(() => Promise.resolve()),
}))

vi.mock('@nextcloud/l10n', () => l10n)
vi.mock('./loadBundle', () => bundle)

import { applyLanguage } from './language'

describe('applyLanguage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    l10n.getLanguage.mockReturnValue('fr')
    bundle.loadLanguageBundle.mockImplementation(() => Promise.resolve())
    document.documentElement.dir = 'ltr'
  })

  it('swaps in the override bundle', async () => {
    await applyLanguage('de')

    expect(l10n.unregister).toHaveBeenCalledWith('pantry')
    expect(l10n.setLanguage).toHaveBeenCalledWith('de')
    expect(bundle.loadLanguageBundle).toHaveBeenCalledWith('de')
  })

  it('leaves the Nextcloud language alone when there is no override', async () => {
    await applyLanguage('')

    expect(l10n.unregister).not.toHaveBeenCalled()
    expect(l10n.setLanguage).not.toHaveBeenCalled()
    expect(bundle.loadLanguageBundle).not.toHaveBeenCalled()
  })

  it('does nothing when the override matches the Nextcloud language', async () => {
    await applyLanguage('fr')

    expect(l10n.unregister).not.toHaveBeenCalled()
    expect(bundle.loadLanguageBundle).not.toHaveBeenCalled()
  })

  it('applies the text direction of the selected language', async () => {
    await applyLanguage('ar')
    expect(document.documentElement.dir).toBe('rtl')
  })

  it('resolves when the bundle fails to load', async () => {
    bundle.loadLanguageBundle.mockRejectedValueOnce(new Error('network'))
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

    await expect(applyLanguage('de')).resolves.toBeUndefined()

    expect(warn).toHaveBeenCalled()
    warn.mockRestore()
  })
})
