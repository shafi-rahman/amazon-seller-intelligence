<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

const authStore = useAuthStore()
const workspaceStore = useWorkspaceStore()
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                Welcome back, {{ authStore.user?.name }}
            </h1>
            <p class="text-gray-500 mt-1">
                {{ workspaceStore.current?.name }} &middot; {{ workspaceStore.current?.marketplace }} marketplace
            </p>
        </div>

        <!-- Placeholder cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                <div class="text-sm font-medium text-gray-500 mb-1">Financial Intelligence</div>
                <div class="text-2xl font-bold text-gray-900">—</div>
                <div class="text-xs text-gray-400 mt-1">Upload orders & settlements to start</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                <div class="text-sm font-medium text-gray-500 mb-1">Product Listings</div>
                <div class="text-2xl font-bold text-gray-900">—</div>
                <div class="text-xs text-gray-400 mt-1">Upload product data to start</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                <div class="text-sm font-medium text-gray-500 mb-1">Competitor Data</div>
                <div class="text-2xl font-bold text-gray-900">—</div>
                <div class="text-xs text-gray-400 mt-1">Add competitor ASINs to start</div>
            </div>
        </div>

        <!-- Sprint progress note -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="w-5 h-5 text-indigo-600 mt-0.5 flex-shrink-0">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-indigo-800">Sprint 1 Complete — Foundation Ready</div>
                    <div class="text-xs text-indigo-600 mt-0.5">
                        Auth, workspaces, and RBAC are set up. Sprint 2 will add CSV imports and data processing.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
