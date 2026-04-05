import App from './App.vue'
import './style.scss'
import { createApp } from 'vue'
import { http } from './axios'
import router from './router'

console.log('[DEBUG] Mounting NextcloudAppTemplate app')
console.log('[DEBUG] Base URL:', http.defaults.baseURL)
createApp(App).use(router).mount('#nextcloudapptemplate-app')
