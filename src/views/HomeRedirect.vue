<template>
  <div class="pantry-loading">
    <NcLoadingIcon :size="48" />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useHouses } from '@/composables/useHouses'
import { useLastHouse } from '@/composables/useLastHouse'

const router = useRouter()
const { load, houses } = useHouses()
const lastHouse = useLastHouse()

onMounted(async () => {
  await load()
  if (houses.value.length === 0) {
    await router.replace({ name: 'houses' })
    return
  }
  const lastId = await lastHouse.get()
  const first = houses.value[0]
  if (!first) {
    await router.replace({ name: 'houses' })
    return
  }
  const target = lastId !== null && houses.value.some((h) => h.id === lastId) ? lastId : first.id
  await router.replace({ name: 'lists', params: { houseId: String(target) } })
})
</script>

<style scoped>
.pantry-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 200px;
}
</style>
