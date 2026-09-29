import App from './App.vue'
import './style.scss'
import { createApp } from 'vue'
import { http } from './axios'
import router from './router'
import { getUserPrefs } from './api/prefs'
import { applyLanguage } from './l10n/language'

console.log('[DEBUG] Mounting Pantry app')
console.log('[DEBUG] Base URL:', http.defaults.baseURL)

async function bootstrap(): Promise<void> {
  // Apply the per-user language override before the first render so the app
  // never flashes the Nextcloud language first.
  try {
    const prefs = await getUserPrefs()
    await applyLanguage(prefs.language)
  } catch {
    // A missing preference or translation bundle must not block the app.
  }

  createApp(App).use(router).mount('#pantry-app')
}

void bootstrap()
