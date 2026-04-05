<template>
  <NcAppNavigation>
    <template #list>
      <NcAppNavigationItem
        :name="strings.allHouses"
        :to="{ name: 'houses' }"
        :active="$route.name === 'houses' || $route.name === 'home'"
      >
        <template #icon>
          <HomeIcon :size="20" />
        </template>
      </NcAppNavigationItem>
    </template>
  </NcAppNavigation>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import HomeIcon from '@icons/Home.vue'

const strings = {
  allHouses: t('pantry', 'All houses'),
}
</script>
