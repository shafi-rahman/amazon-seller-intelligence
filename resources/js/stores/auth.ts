import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { getCsrfCookie } from '@/api/axios'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)
    const loading = ref(false)

    const isAuthenticated = computed(() => user.value !== null)

    // API responses have two shapes depending on endpoint:
    //   GET /auth/me  → { data: { id, name, email, ... } }   (user is data root)
    //   POST /login   → { data: { user: { id, name, ... } } } (user nested under key)
    function extractUser(data: Record<string, unknown>): User | null {
        const payload = (data.data ?? data) as Record<string, unknown>
        return (payload.user ?? payload) as User | null
    }

    async function fetchUser(): Promise<void> {
        try {
            const { data } = await api.get('/auth/me')
            user.value = extractUser(data)
        } catch {
            user.value = null
        }
    }

    async function login(email: string, password: string): Promise<void> {
        loading.value = true
        try {
            await getCsrfCookie()
            const { data } = await api.post('/auth/login', { email, password })
            user.value = extractUser(data)
        } finally {
            loading.value = false
        }
    }

    async function register(payload: {
        name: string
        email: string
        password: string
        password_confirmation: string
        workspace_name?: string
    }): Promise<void> {
        loading.value = true
        try {
            await getCsrfCookie()
            const { data } = await api.post('/auth/register', payload)
            user.value = extractUser(data)
        } finally {
            loading.value = false
        }
    }

    async function logout(): Promise<void> {
        await api.post('/auth/logout')
        user.value = null
    }

    return { user, loading, isAuthenticated, fetchUser, login, register, logout }
})
