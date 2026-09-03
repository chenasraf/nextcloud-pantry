/**
 * Central download/version config for the site.
 *
 * The app version drives the desktop release-asset URLs, whose filenames embed
 * the version (e.g. pantry-0.29.0-macos.dmg). It arrives in
 * `PUBLIC_PANTRY_APP_VERSION`: the deploy workflow resolves it from the release
 * dispatch, a manual input, or the latest pantry-flutter release, and
 * `make website-dev` resolves that same latest release once per run. The
 * fallback only covers a bare `astro dev`/`astro build` with no version to hand,
 * so links may point at an older release than the one published.
 */
export const APP_VERSION: string =
  (import.meta.env.PUBLIC_PANTRY_APP_VERSION as string | undefined)?.replace(/^v/, '') || '0.29.0'

const APP_REPO = 'chenasraf/pantry-flutter'
const releaseAsset = (file: string): string =>
  `https://github.com/${APP_REPO}/releases/download/v${APP_VERSION}/${file}`

/**
 * App-store listings — stable IDs, independent of version.
 *
 * The App Store listing is a universal app: the same link installs on iOS and
 * on macOS.
 */
export const stores = {
  googlePlay: 'https://play.google.com/store/apps/details?id=dev.casraf.pantry',
  fdroid: 'https://f-droid.org/en/packages/dev.casraf.pantry/',
  appStore: 'https://apps.apple.com/us/app/pantry-for-nextcloud/id6762161619',
} as const

/** Direct APK downloads — one per Android ABI, for the current version. */
export const androidApks = [
  { abi: 'arm64-v8a', note: 'most phones since 2017', file: `pantry-${APP_VERSION}-arm64-v8a.apk` },
  {
    abi: 'armeabi-v7a',
    note: 'older 32-bit devices',
    file: `pantry-${APP_VERSION}-armeabi-v7a.apk`,
  },
  { abi: 'x86_64', note: 'emulators & x86 tablets', file: `pantry-${APP_VERSION}-x86_64.apk` },
].map((d) => ({ ...d, url: releaseAsset(d.file) }))

/**
 * Desktop builds — release assets for the current version, plus the store
 * listing for platforms that have one.
 */
const desktopBuilds: { os: string; note: string; file: string; store?: string }[] = [
  {
    os: 'macOS',
    note: 'Apple silicon & Intel',
    file: `pantry-${APP_VERSION}-macos.dmg`,
    store: stores.appStore,
  },
  { os: 'Windows', note: '64-bit', file: `pantry-${APP_VERSION}-windows-x64.zip` },
  { os: 'Linux', note: 'Standalone binary (x64)', file: `pantry-${APP_VERSION}-linux-x64.tar.gz` },
]

export const desktopDownloads = desktopBuilds.map((d) => ({ ...d, url: releaseAsset(d.file) }))

/** All release assets for this version live here. */
export const allReleasesUrl = `https://github.com/${APP_REPO}/releases/latest`
