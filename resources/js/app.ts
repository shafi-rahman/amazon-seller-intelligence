import './bootstrap.ts'
import '../css/app.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Surface otherwise-silent runtime errors instead of failing invisibly.
app.config.errorHandler = (err) => {
    console.error('[vue:error]', err)
    import('@/stores/toast').then(({ useToastStore }) => useToastStore().error('Something went wrong.')).catch(() => {})
}
window.addEventListener('unhandledrejection', (e) => {
    console.error('[unhandledrejection]', e.reason)
})

app.mount('#app')
