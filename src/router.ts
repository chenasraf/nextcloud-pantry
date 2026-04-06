import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const SideNavigation = () => import('@/views/SideNavigation.vue')
const HouseLayout = () => import('@/views/HouseLayout.vue')

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    components: {
      default: () => import('@/views/HomeRedirect.vue'),
      navigation: SideNavigation,
    },
  },
  {
    path: '/welcome',
    name: 'welcome',
    components: {
      default: () => import('@/views/WelcomeView.vue'),
      navigation: SideNavigation,
    },
  },
  {
    path: '/houses/:houseId',
    components: {
      default: HouseLayout,
      navigation: SideNavigation,
    },
    props: { default: true, navigation: false },
    children: [
      { path: '', redirect: (to) => ({ name: 'lists', params: to.params }) },
      {
        path: 'lists',
        name: 'lists',
        component: () => import('@/views/ChecklistsView.vue'),
        props: true,
      },
      {
        path: 'lists/:listId',
        name: 'list-detail',
        component: () => import('@/views/ChecklistDetail.vue'),
        props: true,
      },
      {
        path: 'photos',
        name: 'photos',
        component: () => import('@/views/PhotosView.vue'),
        props: true,
      },
      {
        path: 'photos/folders/:folderId',
        name: 'photo-folder',
        component: () => import('@/views/PhotosView.vue'),
        props: true,
      },
      {
        path: 'notes',
        name: 'notes',
        component: () => import('@/views/NotesView.vue'),
        props: true,
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/pantry')),
  routes,
})

export default router
