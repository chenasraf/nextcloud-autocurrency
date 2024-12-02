import App from './App.vue'
import './style.scss'
import { createApp } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const baseURL = generateOcsUrl('/apps/autocurrency/api')
axios.defaults.baseURL = baseURL

console.log('[DEBUG] Mounting AutoCurrency Settings')
console.log('[DEBUG] Base URL:', baseURL)
createApp(App).mount('#autocurrency-settings')
