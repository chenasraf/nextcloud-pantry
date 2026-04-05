import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const HouseLayout = () => import('@/views/HouseLayout.vue')
const HousesNavigation = () => import('@/views/HousesNavigation.vue')
const HouseNavigation = () => import('@/views/HouseNavigation.vue')

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    components: {
      default: () => import('@/views/HomeRedirect.vue'),
      navigation: HousesNavigation,
    },
  },
  {
    path: '/houses',
    name: 'houses',
    components: {
      default: () => import('@/views/HousesList.vue'),
      navigation: HousesNavigation,
    },
  },
  {
    path: '/houses/:houseId',
    components: {
      default: HouseLayout,
      navigation: HouseNavigation,
    },
    props: { default: true, navigation: true },
    children: [
      { path: '', redirect: (to) => ({ name: 'lists', params: to.params }) },
      {
        path: 'lists',
        name: 'lists',
        component: () => import('@/views/ShoppingListsView.vue'),
        props: true,
      },
      {
        path: 'lists/:listId',
        name: 'list-detail',
        component: () => import('@/views/ShoppingListDetail.vue'),
        props: true,
      },
      {
        path: 'photos',
        name: 'photos',
        component: () => import('@/views/PhotoBoardStub.vue'),
      },
      {
        path: 'notes',
        name: 'notes',
        component: () => import('@/views/NotesWallStub.vue'),
      },
      {
        path: 'members',
        name: 'members',
        component: () => import('@/views/MembersView.vue'),
        props: true,
      },
      {
        path: 'settings',
        name: 'house-settings',
        component: () => import('@/views/HouseSettingsView.vue'),
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
