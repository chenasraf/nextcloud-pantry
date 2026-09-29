/**
 * Runtime language switching for Pantry.
 *
 * Nextcloud renders the app with the user's Nextcloud language and registers
 * that translation bundle before Pantry boots. Pantry lets a user override the
 * language just for this app, so we re-register the app's translations for the
 * chosen language and update the shared `@nextcloud/l10n` state.
 *
 * Only the language is switched, not the Nextcloud locale: dates, week start
 * and number formatting keep following the account locale, exactly as they do
 * when the language is changed in Nextcloud's own personal settings.
 */
import { getLanguage, isRTL, setLanguage, unregister } from '@nextcloud/l10n'
import { ref, type Ref } from 'vue'
import { APP_ID, loadLanguageBundle } from './loadBundle'

/** Language Nextcloud used to render the app, before any Pantry override. */
const serverLanguage = getLanguage()

/** Direction Nextcloud rendered the page with (restored when following it). */
const serverDirection =
  typeof document === 'undefined' ? 'ltr' : document.documentElement.dir || 'ltr'

/**
 * Bumped every time the active language changes. Bound as a `:key` on the app
 * root so components re-render (and re-evaluate `t()`) in the new language.
 */
export const languageVersion: Ref<number> = ref(0)

/** Language whose bundle is currently registered by us (server default at boot). */
let registeredLanguage = serverLanguage

function applyDocumentDirection(language: string): void {
  if (typeof document === 'undefined') return
  if (language === serverLanguage) {
    document.documentElement.dir = serverDirection
    return
  }
  document.documentElement.dir = isRTL(language) ? 'rtl' : 'ltr'
}

/**
 * Apply a Pantry language preference.
 *
 * @param preference Language code, or an empty string to follow Nextcloud.
 */
export async function applyLanguage(preference: string): Promise<void> {
  const target = preference || serverLanguage

  if (target === registeredLanguage) return

  // Drop whatever bundle is registered (the server-rendered one on the first
  // override) before loading the target language.
  unregister(APP_ID)
  setLanguage(target)
  applyDocumentDirection(target)

  try {
    await loadLanguageBundle(target)
  } catch (error) {
    // Missing or unreachable bundle: keep the untranslated source strings
    // rather than failing to boot the app.
    console.warn('[pantry] Could not load translations for', target, error)
  }

  registeredLanguage = target
  languageVersion.value += 1
}

/**
 * Reset the module-level state. Only used by tests.
 */
export function __resetLanguageState(): void {
  registeredLanguage = serverLanguage
  languageVersion.value = 0
}
