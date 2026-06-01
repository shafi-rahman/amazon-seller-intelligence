<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const workspaceStore = useWorkspaceStore()
const router = useRouter()

onMounted(() => {
    workspaceStore.fetchAll()
})

async function handleLogout() {
    await authStore.logout()
    router.push({ name: 'login' })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-sm flex flex-col">
            <div class="flex items-center gap-2 p-4 border-b">
                <div class="w-7 h-7 bg-indigo-600 rounded flex items-center justify-center">
                    <span class="text-white font-bold text-xs">A</span>
                </div>
                <span class="font-bold text-gray-900">ASIP</span>
            </div>

            <!-- Workspace selector -->
            <div class="p-3 border-b">
                <div class="text-xs text-gray-500 mb-1">Workspace</div>
                <div class="text-sm font-medium text-gray-800 truncate">
                    {{ workspaceStore.current?.name ?? 'Loading...' }}
                </div>
            </div>

            <nav class="flex-1 p-3 space-y-1">
                <RouterLink
                    to="/dashboard"
                    class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </RouterLink>

                <RouterLink
                    to="/imports"
                    class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Imports
                </RouterLink>

                <!-- Reconciliation -->
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <RouterLink to="/reconciliation" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        Reconciliation
                    </RouterLink>
                </div>

                <!-- Products -->
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <RouterLink to="/products" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        Products
                    </RouterLink>
                </div>

                <!-- Finance section -->
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <p class="px-3 mb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Finance</p>
                    <RouterLink to="/finance" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        Dashboard
                    </RouterLink>
                    <RouterLink to="/finance/orders" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        Orders
                    </RouterLink>
                    <RouterLink to="/finance/settlements" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        Settlements
                    </RouterLink>
                    <RouterLink to="/finance/bank" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        Bank
                    </RouterLink>
                    <RouterLink to="/finance/gst" class="flex items-center gap-2 px-3 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors" active-class="bg-indigo-50 text-indigo-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                        GST
                    </RouterLink>
                </div>
            </nav>

            <!-- User info -->
            <div class="p-3 border-t">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-indigo-700 font-medium text-sm">
                            {{ authStore.user?.name?.charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ authStore.user?.name }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ authStore.user?.role }}</div>
                    </div>
                    <button
                        @click="handleLogout"
                        class="text-gray-400 hover:text-red-500 transition-colors"
                        title="Logout"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-auto">
            <RouterView />
        </main>
    </div>
</template>
