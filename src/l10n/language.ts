/**
 * Per-user language override for Pantry.
 *
 * Nextcloud renders the app with the user's Nextcloud language and registers
 * that translation bundle before Pantry boots. When the user overrides the
 * language for this app, we swap in the chosen bundle and update the shared
 * `@nextcloud/l10n` state before any component module loads, so `@nextcloud/vue`
 * picks up the override as well. Changing the override reloads the page.
 *
 * Only the language is switched, not the Nextcloud locale: dates, week start
 * and number formatting keep following the account locale, exactly as they do
 * when the language is changed in Nextcloud's own personal settings.
 */
import { getLanguage, isRTL, setLanguage, unregister } from '@nextcloud/l10n'
import { APP_ID, loadLanguageBundle } from './loadBundle'

/**
 * Apply a Pantry language override.
 *
 * @param preference Language code, or an empty string to follow Nextcloud.
 */
export async function applyLanguage(preference: string): Promise<void> {
  if (!preference || preference === getLanguage()) return

  unregister(APP_ID)
  setLanguage(preference)
  // Nextcloud sets the direction on <body>, which would win over <html>.
  document.body.dir = isRTL(preference) ? 'rtl' : 'ltr'

  try {
    await loadLanguageBundle(preference)
  } catch (error) {
    // Missing or unreachable bundle: keep the untranslated source strings
    // rather than failing to boot the app.
    console.warn('[pantry] Could not load translations for', preference, error)
  }
}
