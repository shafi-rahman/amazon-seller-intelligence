import axios from 'axios'

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
})

// Response interceptor — handle common errors globally
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status

        if (status === 401) {
            window.location.href = '/login'
            return Promise.reject(error)
        }

        // Show toast for 403, 429, 500
        if (status === 429) {
            // Lazy import to avoid circular deps
            import('@/stores/toast').then(({ useToastStore }) => {
                useToastStore().warning('Rate limit reached — please wait a moment and try again.')
            })
        } else if (status === 500) {
            import('@/stores/toast').then(({ useToastStore }) => {
                useToastStore().error('Server error — please try again. If this persists, check the Horizon queue.')
            })
        }

        return Promise.reject(error)
    }
)

export async function getCsrfCookie(): Promise<void> {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

export default api
