<!--
SPDX-FileCopyrightText: Your Name <your@email.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
  <div class="mynextcloudapp-{{ kebabCase name }}">{{ startCase name }} Page</div>
</template>

<script lang="ts">
import { defineComopnent, type PropType } from 'vue'
// import NcComponentExample from '@nextcloud/vue/components/NcComponentExample'

// import IconExample from '@icons/Example.vue'

export default defineComponent({
  name: '{{pascalCase name}}Page',
  components: {
    //
  },
})
</script>

<style scoped lang="scss">
#mynextcloudapp-{{ kebabCase name }} {
  /* Your styles here */
}
</style>
