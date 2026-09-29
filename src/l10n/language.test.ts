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

import { __resetLanguageState, applyLanguage, languageVersion } from './language'

describe('language', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    l10n.getLanguage.mockReturnValue('fr')
    bundle.loadLanguageBundle.mockImplementation(() => Promise.resolve())
    __resetLanguageState()
    document.documentElement.dir = 'ltr'
  })

  it('loads and registers the override bundle', async () => {
    await applyLanguage('de')

    expect(l10n.unregister).toHaveBeenCalledWith('pantry')
    expect(l10n.setLanguage).toHaveBeenCalledWith('de')
    expect(bundle.loadLanguageBundle).toHaveBeenCalledWith('de')
    expect(languageVersion.value).toBe(1)
  })

  it('does not reload when the target language is already active', async () => {
    // The server rendered the app in 'fr', so following it is a no-op.
    await applyLanguage('fr')

    expect(l10n.unregister).not.toHaveBeenCalled()
    expect(bundle.loadLanguageBundle).not.toHaveBeenCalled()
    expect(languageVersion.value).toBe(0)
  })

  it('restores the Nextcloud language when the override is cleared', async () => {
    await applyLanguage('de')
    await applyLanguage('')

    expect(l10n.setLanguage).toHaveBeenLastCalledWith('fr')
    expect(bundle.loadLanguageBundle).toHaveBeenCalledTimes(2)
    expect(languageVersion.value).toBe(2)
  })

  it('applies the text direction of the selected language', async () => {
    await applyLanguage('ar')
    expect(document.documentElement.dir).toBe('rtl')

    await applyLanguage('de')
    expect(document.documentElement.dir).toBe('ltr')
  })

  it('still re-renders when the bundle fails to load', async () => {
    bundle.loadLanguageBundle.mockRejectedValueOnce(new Error('network'))
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

    await expect(applyLanguage('de')).resolves.toBeUndefined()

    expect(languageVersion.value).toBe(1)
    expect(warn).toHaveBeenCalled()
    warn.mockRestore()
  })
})
