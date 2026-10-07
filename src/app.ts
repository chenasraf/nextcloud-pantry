import './style.scss'
import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { http } from './axios'
import { applyLanguage } from './l10n/language'

console.log('[DEBUG] Mounting Pantry app')
console.log('[DEBUG] Base URL:', http.defaults.baseURL)

async function bootstrap(): Promise<void> {
  await applyLanguage(loadState<string>('pantry', 'language', ''))

  // Imported only after the language is applied: `@nextcloud/vue` fixes its
  // translations when its modules first evaluate.
  const [{ default: App }, { default: router }] = await Promise.all([
    import('./App.vue'),
    import('./router'),
  ])
  createApp(App).use(router).mount('#pantry-app')
}

void bootstrap()
