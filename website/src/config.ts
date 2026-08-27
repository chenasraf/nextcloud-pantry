/**
 * Central download/version config for the site.
 *
 * The app version drives the desktop release-asset URLs, whose filenames embed
 * the version (e.g. pantry-0.29.0-macos.dmg). CI passes the current app version
 * in via `PUBLIC_PANTRY_APP_VERSION`; the fallback keeps local builds working.
 * See the deploy workflow for how the version is resolved (release dispatch,
 * manual input, or the latest pantry-flutter release).
 */
export const APP_VERSION: string =
  (import.meta.env.PUBLIC_PANTRY_APP_VERSION as string | undefined)?.replace(/^v/, '') || '0.29.0'

const APP_REPO = 'chenasraf/pantry-flutter'
const releaseAsset = (file: string): string =>
  `https://github.com/${APP_REPO}/releases/download/v${APP_VERSION}/${file}`

/** App-store listings — stable IDs, independent of version. */
export const stores = {
  googlePlay: 'https://play.google.com/store/apps/details?id=dev.casraf.pantry',
  fdroid: 'https://f-droid.org/en/packages/dev.casraf.pantry/',
  appStore: 'https://apps.apple.com/us/app/pantry-for-nextcloud/id6762161619',
} as const

/** Direct desktop downloads — release assets for the current version. */
export const desktopDownloads = [
  { os: 'macOS', note: 'Apple silicon & Intel', file: `pantry-${APP_VERSION}-macos.dmg` },
  { os: 'Windows', note: '64-bit', file: `pantry-${APP_VERSION}-windows-x64.zip` },
  { os: 'Linux', note: 'Standalone binary (x64)', file: `pantry-${APP_VERSION}-linux-x64.tar.gz` },
].map((d) => ({ ...d, url: releaseAsset(d.file) }))

/** All release assets for this version live here. */
export const allReleasesUrl = `https://github.com/${APP_REPO}/releases/latest`
