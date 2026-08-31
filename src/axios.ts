import { generateOcsUrl } from '@nextcloud/router'
import _axios from '@nextcloud/axios'

const baseURL = generateOcsUrl('/apps/pantry/api')
export const http = _axios.create({ baseURL })
export const ocs = _axios.create({ baseURL })
ocs.interceptors.response.use(
  (response) => {
    const ocsData = response?.data?.ocs?.data
    response.data = ocsData ?? response?.data ?? null
    return response
  },
  (error) => {
    const message = error?.response?.data?.ocs?.meta?.message
    if (typeof message === 'string' && message.trim() !== '') {
      error.message = message
    }
    return Promise.reject(error)
  },
)
