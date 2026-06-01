<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useImportStore } from '@/stores/imports'
import type { ImportBatch } from '@/types'

const router        = useRouter()
const workspaceStore = useWorkspaceStore()
const importStore    = useImportStore()

const typeFilter   = ref('')
const statusFilter = ref('')

const IMPORT_LABELS: Record<string, string> = {
    orders:           'Amazon Orders',
    settlements:      'Settlements',
    bank_statement:   'Bank Statement',
    gst_report:       'GST Report',
    products:         'Products',
    competitors_csv:  'Competitors (CSV)',
    competitors_html: 'Competitors (HTML)',
}

const STATUS_COLORS: Record<string, string> = {
    pending:         'bg-gray-100 text-gray-600',
    detecting:       'bg-yellow-100 text-yellow-700',
    awaiting_mapping:'bg-blue-100 text-blue-700',
    processing:      'bg-indigo-100 text-indigo-700',
    completed:       'bg-green-100 text-green-700',
    partial:         'bg-orange-100 text-orange-700',
    failed:          'bg-red-100 text-red-600',
}

onMounted(() => loadBatches())

async function loadBatches() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    const params: Record<string, string> = {}
    if (typeFilter.value)   params.type   = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    await importStore.fetchBatches(wsId, params)
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Imports</h1>
                <p class="text-gray-500 text-sm mt-1">Upload and manage your data imports</p>
            </div>
            <div class="flex gap-2">
                <RouterLink
                    to="/imports/upload"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors"
                >
                    New Import
                </RouterLink>
                <RouterLink
                    to="/imports/html"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors"
                >
                    Paste HTML
                </RouterLink>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 mb-4">
            <select v-model="typeFilter" @change="loadBatches" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All types</option>
                <option v-for="(label, key) in IMPORT_LABELS" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="statusFilter" @change="loadBatches" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="completed">Completed</option>
                <option value="partial">Partial</option>
                <option value="processing">Processing</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">File</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Progress</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="importStore.batches.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                            No imports yet. Click <strong>New Import</strong> to get started.
                        </td>
                    </tr>
                    <tr v-for="batch in importStore.batches" :key="batch.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate">{{ batch.original_filename }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ IMPORT_LABELS[batch.type] ?? batch.type }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[batch.status] ?? 'bg-gray-100 text-gray-600']">
                                {{ batch.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            <span v-if="batch.total_rows > 0">
                                {{ batch.processed_rows.toLocaleString() }} / {{ batch.total_rows.toLocaleString() }} rows
                                <span v-if="batch.failed_rows > 0" class="text-red-500 ml-1">({{ batch.failed_rows }} errors)</span>
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ formatDate(batch.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink
                                v-if="batch.failed_rows > 0"
                                :to="`/imports/${batch.id}/errors`"
                                class="text-xs text-red-600 hover:underline"
                            >
                                View errors
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
