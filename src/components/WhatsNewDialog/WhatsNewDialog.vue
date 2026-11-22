<template>
  <NcDialog :name="strings.title" :open="open" size="normal" @update:open="onUpdateOpen">
    <section
      v-for="entry in entries"
      :key="entry.version"
      class="whats-new__version"
      data-testid="whats-new-version"
    >
      <h3 class="whats-new__heading">{{ versionHeading(entry.version) }}</h3>

      <div v-for="step in entry.steps" :key="step.title" class="whats-new__step">
        <LightbulbIcon :size="20" class="whats-new__step-icon" />
        <div>
          <strong>{{ step.title }}</strong>
          <p>{{ step.body }}</p>
        </div>
      </div>

      <NcRichText v-if="entry.notes" :text="entry.notes" :use-markdown="true" />

      <a
        class="whats-new__link"
        :href="fullNotesUrl(entry.version)"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ strings.fullNotes }}
      </a>
    </section>

    <template #actions>
      <NcButton variant="primary" data-testid="whats-new-dismiss" @click="dismiss">
        {{ strings.dismiss }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import LightbulbIcon from '@icons/LightbulbOutline.vue'

import { markWhatsNewSeen } from '@/api/prefs'
import { releaseNotesByVersion } from '@/releaseNotes'
import { whatsNewSince } from '@/utils/whatsNew'

import { onboardingSteps } from './onboardingSteps'

interface WhatsNewState {
  seenVersion: string
  installedVersion: string
}

const state = loadState<WhatsNewState | null>('pantry', 'whatsNew', null)
const entries = state
  ? whatsNewSince(
      state.seenVersion,
      state.installedVersion,
      releaseNotesByVersion,
      onboardingSteps(),
    )
  : []
const open = ref(entries.length > 0)

const strings = {
  title: t('pantry', "What's new in Pantry"),
  fullNotes: t('pantry', 'Read the full release notes'),
  // TRANSLATORS: Button that closes the "What's new" dialog
  dismiss: t('pantry', 'Got it'),
}

function versionHeading(version: string): string {
  // TRANSLATORS: Heading above one release's notes; the placeholder is its version, e.g. "Pantry 0.40.0"
  return t('pantry', 'Pantry {version}', { version })
}

// Matches `full-notes-url` in release-notes/config.yml.
function fullNotesUrl(version: string): string {
  return `https://github.com/chenasraf/nextcloud-pantry/releases/tag/v${version}`
}

function dismiss() {
  if (!open.value) return
  open.value = false
  markWhatsNewSeen().catch((e) => console.error("[pantry] Failed to mark what's new as seen", e))
}

function onUpdateOpen(value: boolean) {
  if (!value) dismiss()
}
</script>

<style scoped>
.whats-new__version + .whats-new__version {
  margin-top: 1.5rem;
}

.whats-new__heading {
  margin: 0 0 0.5rem;
}

.whats-new__step {
  display: flex;
  gap: 0.75rem;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
  border-radius: var(--border-radius-large);
  background: var(--color-primary-element-light);
}

.whats-new__step p {
  margin: 0.25rem 0 0;
}

.whats-new__step-icon {
  flex-shrink: 0;
  color: var(--color-primary-element);
}

.whats-new__link {
  display: inline-block;
  margin-top: 0.5rem;
  color: var(--color-primary-element);
  text-decoration: underline;
}
</style>
