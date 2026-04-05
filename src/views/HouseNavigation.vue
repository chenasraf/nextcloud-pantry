<template>
  <NcAppNavigation>
    <template #list>
      <NcAppNavigationItem :name="strings.allHouses" :to="{ name: 'houses' }">
        <template #icon>
          <ArrowLeftIcon :size="20" />
        </template>
      </NcAppNavigationItem>

      <li v-if="house" class="pantry-nav-house-name" :title="house.name">
        {{ house.name }}
      </li>

      <NcAppNavigationItem
        :name="strings.lists"
        :to="{ name: 'lists', params: { houseId: String(houseId) } }"
      >
        <template #icon>
          <CartIcon :size="20" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="strings.photos"
        :to="{ name: 'photos', params: { houseId: String(houseId) } }"
      >
        <template #icon>
          <ImageIcon :size="20" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="strings.notes"
        :to="{ name: 'notes', params: { houseId: String(houseId) } }"
      >
        <template #icon>
          <NoteIcon :size="20" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        :name="strings.members"
        :to="{ name: 'members', params: { houseId: String(houseId) } }"
      >
        <template #icon>
          <AccountGroupIcon :size="20" />
        </template>
      </NcAppNavigationItem>

      <NcAppNavigationItem
        v-if="canAdmin"
        :name="strings.houseSettings"
        :to="{ name: 'house-settings', params: { houseId: String(houseId) } }"
      >
        <template #icon>
          <CogIcon :size="20" />
        </template>
      </NcAppNavigationItem>
    </template>
  </NcAppNavigation>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import CartIcon from '@icons/Cart.vue'
import ImageIcon from '@icons/Image.vue'
import NoteIcon from '@icons/Note.vue'
import AccountGroupIcon from '@icons/AccountGroup.vue'
import CogIcon from '@icons/Cog.vue'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

const { house, houseId, canAdmin } = useCurrentHouse()

const strings = {
  allHouses: t('pantry', 'All houses'),
  lists: t('pantry', 'Shopping lists'),
  photos: t('pantry', 'Photo board'),
  notes: t('pantry', 'Notes wall'),
  members: t('pantry', 'Members'),
  houseSettings: t('pantry', 'House settings'),
}
</script>

<style scoped>
.pantry-nav-house-name {
  padding: 8px 16px 4px;
  font-weight: 600;
  color: var(--color-text-maxcontrast);
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
