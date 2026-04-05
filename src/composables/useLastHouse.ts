import * as api from '@/api/prefs'

export function useLastHouse() {
  return {
    get: api.getLastHouse,
    set: api.setLastHouse,
  }
}
