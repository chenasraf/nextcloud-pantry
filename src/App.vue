<template>
  <NcContent app-name="pantry">
    <router-view name="navigation" />
    <NcAppContent id="pantry-main">
      <div id="pantry-router">
        <div v-if="isRouterLoading" class="router-loading">
          <NcLoadingIcon :size="48" />
        </div>
        <router-view v-else />
      </div>
    </NcAppContent>
  </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

export default {
  name: 'PantryApp',
  components: {
    NcContent,
    NcAppContent,
    NcLoadingIcon,
  },
  provide() {
    return { 'NcContent:setHasAppNavigation': () => true }
  },
  data() {
    return {
      isRouterLoading: false,
      _removeBeforeEach: null,
      _removeAfterEach: null,
    }
  },
  created() {
    this._removeBeforeEach = this.$router.beforeEach((to, from, next) => {
      this.isRouterLoading = true
      next()
    })
    this._removeAfterEach = this.$router.afterEach(() => {
      this.isRouterLoading = false
    })
  },
  beforeUnmount() {
    if (typeof this._removeBeforeEach === 'function') this._removeBeforeEach()
    if (typeof this._removeAfterEach === 'function') this._removeAfterEach()
  },
}
</script>

<style scoped lang="scss">
#pantry-main {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

#pantry-router {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.router-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}
</style>
