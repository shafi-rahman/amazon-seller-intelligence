<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({ email: '', password: '' })
const errors = ref<Record<string, string[]>>({})
const serverError = ref('')

async function submit() {
    errors.value = {}
    serverError.value = ''
    try {
        await authStore.login(form.value.email, form.value.password)
        router.push({ name: 'dashboard' })
    } catch (err: unknown) {
        const e = err as { response?: { data?: ApiError } }
        if (e.response?.data?.errors) {
            errors.value = e.response.data.errors
        } else {
            serverError.value = e.response?.data?.message ?? 'Login failed. Please try again.'
        }
    }
}
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Sign in to ASIP</h2>

        <div v-if="serverError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
            {{ serverError }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :class="{ 'border-red-400': errors.email }"
                />
                <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :class="{ 'border-red-400': errors.password }"
                />
                <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password[0] }}</p>
            </div>

            <button
                type="submit"
                :disabled="authStore.loading"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <span v-if="authStore.loading">Signing in...</span>
                <span v-else>Sign in</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Don't have an account?
            <RouterLink to="/register" class="font-medium text-indigo-600 hover:text-indigo-500">
                Create one
            </RouterLink>
        </p>
    </div>
</template>
