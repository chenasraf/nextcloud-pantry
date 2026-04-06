<template>
  <div id="pantry-content" class="section">
    <h2>{{ strings.title }}</h2>
    <NcAppSettingsSection :name="strings.aboutHeader">
      <p>{{ strings.aboutBody }}</p>
    </NcAppSettingsSection>
  </div>
</template>

<script>
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import { t } from '@nextcloud/l10n'

export default {
  name: 'PantrySettings',
  components: {
    NcAppSettingsSection,
  },
  data() {
    return {
      strings: {
        title: t('pantry', 'Pantry'),
        aboutHeader: t('pantry', 'About:'),
        aboutBody: t(
          'pantry',
          'Pantry is a household organizer. Open the Pantry app from the top navigation to manage your houses, checklists, photos and notes.',
        ),
      },
    }
  },
}
</script>

<style scoped lang="scss">
#pantry-content {
  h2:first-child {
    margin-top: 0;
  }
}
</style>
