import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
  generateFilePath: vi.fn(
    (app: string, type: string, file: string) => `/custom_apps/${app}/${type}/${file}`,
  ),
}))

import { loadLanguageBundle } from './loadBundle'

describe('loadLanguageBundle', () => {
  let appended: HTMLScriptElement[]
  let appendSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    appended = []
    appendSpy = vi.spyOn(document.head, 'appendChild').mockImplementation((node) => {
      appended.push(node as HTMLScriptElement)
      return node
    })
  })

  afterEach(() => {
    appendSpy.mockRestore()
  })

  it('resolves immediately for the source language', async () => {
    await expect(loadLanguageBundle('en')).resolves.toBeUndefined()
    expect(appended).toHaveLength(0)
  })

  it('injects the compiled js bundle and resolves on load', async () => {
    const promise = loadLanguageBundle('de')

    expect(appended).toHaveLength(1)
    expect(appended[0].src).toContain('/custom_apps/pantry/l10n/de.js')

    appended[0].onload!(new Event('load'))
    await expect(promise).resolves.toBeUndefined()
  })

  it('rejects when the bundle fails to load', async () => {
    const promise = loadLanguageBundle('de')

    appended[0].onerror!(new Event('error'))
    await expect(promise).rejects.toThrow('Failed to load de translations')
  })
})
