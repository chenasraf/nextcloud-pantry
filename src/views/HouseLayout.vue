<template>
  <div v-if="loading" class="pantry-loading">
    <NcLoadingIcon :size="48" />
  </div>
  <NcEmptyContent
    v-else-if="!house"
    :name="strings.notFoundTitle"
    :description="strings.notFoundBody"
  >
    <template #icon>
      <HomeIcon />
    </template>
  </NcEmptyContent>
  <router-view v-else />
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import HomeIcon from '@icons/Home.vue'
import { useCurrentHouse } from '@/composables/useCurrentHouse'
import { useLastHouse } from '@/composables/useLastHouse'

const { house, houseId, loading } = useCurrentHouse()
const lastHouse = useLastHouse()

async function persistLastHouse() {
  if (houseId.value !== null) {
    try {
      await lastHouse.set(houseId.value)
    } catch {
      // Non-critical; swallow.
    }
  }
}

onMounted(persistLastHouse)
watch(houseId, persistLastHouse)

const strings = {
  notFoundTitle: t('pantry', 'House not found'),
  notFoundBody: t('pantry', 'This house does not exist or you no longer have access.'),
}
</script>

<style scoped>
.pantry-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 300px;
}
</style>
