<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import { useReportsStore } from '@/stores/reports'

const workspaceStore = useWorkspaceStore()
const reportsStore   = useReportsStore()

const generating    = ref<string | null>(null)
const showModal     = ref(false)
const selected      = ref({ type: '', format: 'csv', reconciliation_run_id: '', product_id: '' })
const typeFilter    = ref('')
const statusFilter  = ref('')

const STATUS_COLORS: Record<string, string> = {
    pending:    'bg-yellow-100 text-yellow-700',
    generating: 'bg-blue-100 text-blue-700',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-600',
}

const FORMAT_ICONS: Record<string, string> = {
    pdf: '📄',
    csv: '📊',
}

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await Promise.all([
        reportsStore.fetchTypes(wsId),
        reportsStore.fetchReports(wsId),
    ])
})

function openModal(type: string) {
    selected.value = { type, format: 'csv', reconciliation_run_id: '', product_id: '' }
    // Auto-select first available format
    const reportType = reportsStore.types[type]
    if (reportType?.formats?.length === 1) {
        selected.value.format = reportType.formats[0]
    }
    showModal.value = true
}

async function generate() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !selected.value.type) return
    generating.value = selected.value.type
    showModal.value  = false

    const params: Record<string, unknown> = {}
    if (selected.value.reconciliation_run_id) params.reconciliation_run_id = Number(selected.value.reconciliation_run_id)
    if (selected.value.product_id) params.product_id = Number(selected.value.product_id)

    try {
        await reportsStore.requestReport(wsId, selected.value.type, selected.value.format, params)
    } finally {
        generating.value = null
    }
}

async function download(reportId: number) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    const url = await reportsStore.getDownloadUrl(wsId, reportId)
    window.open(url, '_blank')
}

async function loadFiltered() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    const params: Record<string, string> = {}
    if (typeFilter.value) params.type = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    await reportsStore.fetchReports(wsId, params)
}

function needsReconciliationId(type: string) {
    return ['reconciliation_summary','missing_settlements','missing_credits','refund_impact','gst_mismatch'].includes(type)
}
function needsProductId(type: string) {
    return ['listing_analysis','keyword_gap','competitor_benchmark'].includes(type)
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
                <p class="text-gray-500 text-sm mt-1">Generate PDF and CSV reports from your Amazon data</p>
            </div>
        </div>

        <!-- Report type cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div v-for="(meta, type) in reportsStore.types" :key="type"
                class="bg-white rounded-lg border border-gray-200 p-4 hover:border-indigo-300 transition-colors">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-800">{{ meta.title }}</div>
                        <div class="flex gap-1 mt-1">
                            <span v-for="fmt in meta.formats" :key="fmt"
                                class="px-1.5 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">
                                {{ FORMAT_ICONS[fmt] }} {{ fmt.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                </div>
                <button @click="openModal(type)"
                    :disabled="generating === type"
                    class="w-full py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {{ generating === type ? 'Queued…' : 'Generate' }}
                </button>
            </div>
        </div>

        <!-- Recent reports -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800">Recent Reports</h2>
            <div class="flex gap-2">
                <select v-model="typeFilter" @change="loadFiltered" class="text-sm border border-gray-300 rounded px-2 py-1 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">All types</option>
                    <option v-for="(meta, type) in reportsStore.types" :key="type" :value="type">{{ meta.title }}</option>
                </select>
                <select v-model="statusFilter" @change="loadFiltered" class="text-sm border border-gray-300 rounded px-2 py-1 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="reportsStore.loading" class="py-10 text-center text-gray-400 text-sm">Loading…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Report</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Format</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Generated</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="reportsStore.reports.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                            No reports generated yet. Click "Generate" on a report type above.
                        </td>
                    </tr>
                    <tr v-for="r in reportsStore.reports" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-xs font-medium text-gray-800">{{ r.title }}</div>
                            <div class="text-xs text-gray-400">#{{ r.id }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ FORMAT_ICONS[r.file_format] }} {{ r.file_format?.toUpperCase() }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[r.status] ?? 'bg-gray-100 text-gray-600']">
                                {{ r.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ r.generated_at ? new Date(r.generated_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="r.status === 'completed' && r.has_file"
                                @click="download(r.id)"
                                class="text-xs text-indigo-600 hover:underline font-medium">
                                Download ↓
                            </button>
                            <span v-else-if="r.status === 'generating'" class="text-xs text-blue-500 italic">Generating…</span>
                            <span v-else-if="r.status === 'failed'" class="text-xs text-red-500">Failed</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Generate Report Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showModal = false">
            <div class="bg-white rounded-xl shadow-xl p-6 w-96">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Generate Report</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                        <div class="text-sm text-gray-600">{{ reportsStore.types[selected.type]?.title }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                        <div class="flex gap-2">
                            <button v-for="fmt in reportsStore.types[selected.type]?.formats ?? []" :key="fmt"
                                @click="selected.format = fmt"
                                :class="['flex-1 py-2 text-sm rounded-lg border-2 transition-colors',
                                    selected.format === fmt ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-medium' : 'border-gray-200 text-gray-600']">
                                {{ FORMAT_ICONS[fmt] }} {{ fmt.toUpperCase() }}
                            </button>
                        </div>
                    </div>

                    <!-- Reconciliation run ID -->
                    <div v-if="needsReconciliationId(selected.type)">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Reconciliation Run ID <span class="text-red-500">*</span>
                        </label>
                        <input v-model="selected.reconciliation_run_id" type="number" min="1"
                            placeholder="e.g. 7"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-400 mt-1">Find the run ID in the Reconciliation page</p>
                    </div>

                    <!-- Product ID -->
                    <div v-if="needsProductId(selected.type)">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Product ID <span class="text-red-500">*</span>
                        </label>
                        <input v-model="selected.product_id" type="number" min="1"
                            placeholder="e.g. 1"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-400 mt-1">Find the product ID in the Products page</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-5">
                    <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button @click="generate"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                        Generate →
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
