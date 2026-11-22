export interface OnboardingStep {
  /** The release the step ships in. */
  version: string
  title: string
  body: string
}

export interface WhatsNewEntry {
  version: string
  /** The App Store notes for the version, in markdown, or null when it has none. */
  notes: string | null
  steps: OnboardingStep[]
}

const VERSION_PATTERN = /^\d+\.\d+\.\d+$/

/** Compares two `major.minor.patch` versions: negative when a is older. */
export function compareVersions(a: string, b: string): number {
  const pa = a.split('.').map((n) => parseInt(n, 10) || 0)
  const pb = b.split('.').map((n) => parseInt(n, 10) || 0)
  for (let i = 0; i < 3; i++) {
    const diff = (pa[i] ?? 0) - (pb[i] ?? 0)
    if (diff !== 0) return diff
  }
  return 0
}

/**
 * Every version after `seen` up to and including `installed` that has notes
 * or onboarding steps, newest first.
 */
export function whatsNewSince(
  seen: string,
  installed: string,
  notesByVersion: Record<string, string>,
  steps: OnboardingStep[],
): WhatsNewEntry[] {
  const inRange = (v: string) =>
    VERSION_PATTERN.test(v) && compareVersions(v, seen) > 0 && compareVersions(v, installed) <= 0

  const versions = new Set<string>()
  for (const [version, notes] of Object.entries(notesByVersion)) {
    if (notes.trim() !== '' && inRange(version)) versions.add(version)
  }
  for (const step of steps) {
    if (inRange(step.version)) versions.add(step.version)
  }

  return [...versions]
    .sort((a, b) => compareVersions(b, a))
    .map((version) => ({
      version,
      notes: notesByVersion[version]?.trim() || null,
      steps: steps.filter((s) => s.version === version),
    }))
}
