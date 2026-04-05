import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const routes: RouteRecordRaw[] = [{ path: '/', component: () => import('@/views/AppView.vue') }]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/nextcloudapptemplate')),
  routes,
})

export default router
