import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { getCsrfCookie } from '@/api/axios'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)
    const loading = ref(false)

    const isAuthenticated = computed(() => user.value !== null)

    async function fetchUser(): Promise<void> {
        try {
            const { data } = await api.get('/auth/me')
            user.value = data.data ?? data
        } catch {
            user.value = null
        }
    }

    async function login(email: string, password: string): Promise<void> {
        loading.value = true
        try {
            await getCsrfCookie()
            const { data } = await api.post('/auth/login', { email, password })
            user.value = data.data?.user ?? data.user
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
            user.value = data.data?.user ?? data.user
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
