<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useImportStore } from '@/stores/imports'
import type { ImportStatus } from '@/types'

const route      = useRoute()
const router     = useRouter()
const importStore = useImportStore()

const status    = ref<ImportStatus | null>(null)
const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)
const batchId   = route.params.id as string  // UUID (public_id)

const IMPORT_LABELS: Record<string, string> = {
    orders: 'Amazon Orders', settlements: 'Settlements',
    bank_statement: 'Bank Statement', gst_report: 'GST Report',
    products: 'Products', competitors_csv: 'Competitors (CSV)', competitors_html: 'Competitors (HTML)',
}

const DONE_STATUSES = ['completed', 'partial', 'failed']

onMounted(async () => {
    await poll()
    if (status.value && !DONE_STATUSES.includes(status.value.status)) {
        pollTimer.value = setInterval(poll, 3000)
    }
})

onUnmounted(() => {
    if (pollTimer.value) clearInterval(pollTimer.value)
})

async function poll() {
    try {
        status.value = await importStore.pollStatus(batchId)
        if (status.value && DONE_STATUSES.includes(status.value.status)) {
            if (pollTimer.value) clearInterval(pollTimer.value)
        }
    } catch {
        // Stop polling on error so the interval never leaks; the interceptor toasts.
        if (pollTimer.value) clearInterval(pollTimer.value)
    }
}
</script>

<template>
    <div class="p-6 max-w-2xl">
        <div class="mb-6">
            <RouterLink to="/imports" class="text-sm text-indigo-600 hover:underline">← Back to imports</RouterLink>
        </div>

        <div v-if="!status" class="text-gray-400 text-sm">Loading…</div>

        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        {{ IMPORT_LABELS[status.type] ?? status.type }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">Import #{{ batchId }}</p>
                </div>
                <span :class="{
                    'bg-green-100 text-green-700': status.status === 'completed',
                    'bg-orange-100 text-orange-700': status.status === 'partial',
                    'bg-red-100 text-red-600': status.status === 'failed',
                    'bg-indigo-100 text-indigo-700': status.status === 'processing',
                    'bg-gray-100 text-gray-600': !['completed','partial','failed','processing'].includes(status.status),
                }" class="px-3 py-1 rounded-full text-sm font-medium">
                    {{ status.status }}
                </span>
            </div>

            <!-- Progress bar -->
            <div v-if="status.total_rows > 0" class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>{{ status.processed_rows.toLocaleString() }} / {{ status.total_rows.toLocaleString() }} rows</span>
                    <span>{{ status.percent }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div
                        class="h-2 rounded-full transition-all duration-500"
                        :class="status.status === 'failed' ? 'bg-red-500' : status.status === 'partial' ? 'bg-orange-500' : 'bg-indigo-600'"
                        :style="{ width: `${status.percent}%` }"
                    />
                </div>
            </div>

            <!-- Stats -->
            <div v-if="status.status !== 'processing'" class="grid grid-cols-3 gap-4 mb-4">
                <div class="text-center p-3 bg-gray-50 rounded-md">
                    <div class="text-2xl font-bold text-gray-900">{{ status.total_rows.toLocaleString() }}</div>
                    <div class="text-xs text-gray-500">Total rows</div>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-md">
                    <div class="text-2xl font-bold text-green-700">{{ (status.processed_rows - status.failed_rows).toLocaleString() }}</div>
                    <div class="text-xs text-gray-500">Imported</div>
                </div>
                <div class="text-center p-3 rounded-md" :class="status.failed_rows > 0 ? 'bg-red-50' : 'bg-gray-50'">
                    <div class="text-2xl font-bold" :class="status.failed_rows > 0 ? 'text-red-600' : 'text-gray-400'">
                        {{ status.failed_rows.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500">Errors</div>
                </div>
            </div>

            <!-- Processing spinner -->
            <div v-else class="flex items-center gap-3 py-4 text-indigo-600">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span class="text-sm">Processing rows… page will update automatically</span>
            </div>

            <!-- Actions -->
            <div class="flex gap-3" v-if="DONE_STATUSES.includes(status.status)">
                <RouterLink
                    v-if="status.failed_rows > 0"
                    :to="`/imports/${batchId}/errors`"
                    class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded-md hover:bg-red-50 transition-colors"
                >
                    View {{ status.failed_rows }} errors
                </RouterLink>
                <RouterLink
                    to="/imports"
                    class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors"
                >
                    Back to imports
                </RouterLink>
            </div>
        </div>
    </div>
</template>
