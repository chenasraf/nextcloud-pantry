import { generateFilePath } from '@nextcloud/router'

/** Pantry's app id. */
export const APP_ID = 'pantry'

/**
 * Inject the app's compiled `l10n/<language>.js` bundle.
 *
 * The bundle registers its strings with `OC.L10N`, the same mechanism Nextcloud
 * uses when it renders a page. We deliberately do not use `loadTranslations`
 * from `@nextcloud/l10n`: that helper fetches `l10n/<language>.json`, but
 * Nextcloud's `.htaccess` only serves a static extension whitelist from app
 * paths and rewrites `.json` to `index.php` (404). `.js` is on the whitelist,
 * so it is served directly for apps in `custom_apps` too.
 *
 * @param language Language code to load.
 */
export function loadLanguageBundle(language: string): Promise<void> {
  // English strings are the source language; there is no bundle to load.
  if (language === 'en') {
    return Promise.resolve()
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = generateFilePath(APP_ID, 'l10n', `${language}.js`)
    script.onload = () => {
      script.remove()
      resolve()
    }
    script.onerror = () => {
      script.remove()
      reject(new Error(`Failed to load ${language} translations`))
    }
    document.head.appendChild(script)
  })
}
