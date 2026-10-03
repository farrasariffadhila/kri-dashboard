import { createApp } from 'vue'
import '@fontsource/ibm-plex-sans/400.css'
import '@fontsource/ibm-plex-sans/500.css'
import '@fontsource/ibm-plex-sans/600.css'
import '@fontsource/ibm-plex-serif/500.css'
import '@fontsource/ibm-plex-serif/600.css'
import './style.css'
import App from './App.vue'
import router from './router.js'
import { i18n } from './i18n/index.js'

createApp(App).use(i18n).use(router).mount('#app')
