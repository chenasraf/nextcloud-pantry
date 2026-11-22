// The App Store notes of every released version, bundled at build time:
// `release-notes/` itself is left out of the app tarball.
const files = import.meta.glob<string>('../release-notes/*/appstore.md', {
  query: '?raw',
  import: 'default',
  eager: true,
})

export const releaseNotesByVersion: Record<string, string> = Object.fromEntries(
  Object.entries(files).map(([path, notes]) => [path.split('/').at(-2) ?? '', notes]),
)
