import UserSettings from './UserSettings.vue'
import './style.scss'
import { createApp } from 'vue'
import { http } from './axios'

console.log('[DEBUG] Mounting AutoCurrency Settings')
console.log('[DEBUG] Base URL:', http.defaults.baseURL)
createApp(UserSettings).mount('#autocurrency-settings')
