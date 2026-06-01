<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useReconciliationStore } from '@/stores/reconciliation'

const route               = useRoute()
const workspaceStore      = useWorkspaceStore()
const reconciliationStore = useReconciliationStore()

const run           = ref<any>(null)
const activeReport  = ref<string | null>(null)
const reportData    = ref<any>(null)
const reportLoading = ref(false)
const exporting     = ref(false)
const exportDone    = ref(false)

const REPORT_LABELS: Record<string, string> = {
    summary:              'Summary',
    missing_settlements:  'Missing Settlements',
    missing_credits:      'Missing Bank Credits',
    refund_impact:        'Refund Impact',
    return_impact:        'Return Impact',
    gst_mismatch:         'GST Mismatches',
}

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    const runId = Number(route.params.id)
    if (!wsId || !runId) return
    run.value = await reconciliationStore.fetchRun(wsId, runId)
    // Auto-load summary
    await loadReport('summary')
})

async function loadReport(type: string) {
    const wsId  = workspaceStore.current?.id
    const runId = Number(route.params.id)
    if (!wsId || !runId) return
    activeReport.value  = type
    reportLoading.value = true
    reportData.value    = null
    try {
        reportData.value = await reconciliationStore.fetchReport(wsId, runId, type)
    } finally {
        reportLoading.value = false
    }
}

async function exportReport(format: string) {
    const wsId  = workspaceStore.current?.id
    const runId = Number(route.params.id)
    if (!wsId || !runId || !activeReport.value) return
    exporting.value = true
    try {
        await reconciliationStore.requestExport(wsId, runId, activeReport.value, format)
        exportDone.value = true
    } finally {
        exporting.value = false
    }
}

function getReportCount(type: string): number {
    const r = run.value?.reports?.find((x: any) => x.report_type === type)
    return r?.count ?? 0
}

const fmt  = (v: number | null) => v == null ? '—' : '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2 })
const num  = (v: number) => (v ?? 0).toLocaleString('en-IN')
</script>

<template>
    <div class="p-6">
        <div class="flex items-center gap-4 mb-6">
            <RouterLink to="/reconciliation" class="text-sm text-indigo-600 hover:underline">← Reconciliation</RouterLink>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900" v-if="run">
                Run #{{ run.id }} &mdash; {{ run.period_start }} to {{ run.period_end }}
            </h1>
        </div>

        <div v-if="!run" class="text-gray-400 text-sm py-8 text-center">Loading…</div>

        <div v-else class="flex gap-6">
            <!-- Left: report type sidebar -->
            <div class="w-56 flex-shrink-0">
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500">
                        REPORTS
                    </div>
                    <nav class="divide-y divide-gray-50">
                        <button
                            v-for="(label, type) in REPORT_LABELS"
                            :key="type"
                            @click="loadReport(type)"
                            :class="['w-full text-left px-4 py-3 text-sm transition-colors',
                                activeReport === type
                                    ? 'bg-indigo-50 text-indigo-700 font-medium'
                                    : 'text-gray-700 hover:bg-gray-50']"
                        >
                            <div>{{ label }}</div>
                            <div v-if="type !== 'summary'" class="text-xs mt-0.5"
                                :class="getReportCount(type) > 0 ? (type === 'summary' ? 'text-gray-400' : 'text-red-500 font-medium') : 'text-green-600'">
                                {{ getReportCount(type) > 0 ? getReportCount(type) + ' items' : '✓ None' }}
                            </div>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Right: report content -->
            <div class="flex-1 min-w-0">
                <div class="bg-white rounded-lg border border-gray-200">
                    <!-- Report header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                        <h2 class="text-base font-semibold text-gray-900">
                            {{ activeReport ? REPORT_LABELS[activeReport] : 'Select a report' }}
                        </h2>
                        <div v-if="activeReport && activeReport !== 'summary'" class="flex gap-2">
                            <button @click="exportReport('csv')" :disabled="exporting"
                                class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 transition-colors">
                                Export CSV
                            </button>
                            <button @click="exportReport('pdf')" :disabled="exporting"
                                class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 transition-colors">
                                Export PDF
                            </button>
                        </div>
                    </div>

                    <div v-if="exportDone" class="mx-5 mt-4 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-700">
                        Export queued — file will be ready shortly. Refresh to download.
                    </div>

                    <!-- Loading -->
                    <div v-if="reportLoading" class="py-10 text-center text-gray-400 text-sm">Loading report…</div>

                    <!-- Summary report -->
                    <div v-else-if="activeReport === 'summary' && reportData?.data" class="p-5">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">{{ num(reportData.data.total_orders) }}</div>
                                <div class="text-xs text-gray-500 mt-1">Total Orders</div>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-700">{{ num(reportData.data.matched_orders) }}</div>
                                <div class="text-xs text-gray-500 mt-1">Matched ({{ reportData.data.match_rate_pct }}%)</div>
                            </div>
                            <div class="text-center p-4 rounded-lg" :class="(reportData.data.unmatched_orders ?? 0) > 0 ? 'bg-red-50' : 'bg-gray-50'">
                                <div class="text-2xl font-bold" :class="(reportData.data.unmatched_orders ?? 0) > 0 ? 'text-red-600' : 'text-gray-400'">{{ num(reportData.data.unmatched_orders) }}</div>
                                <div class="text-xs text-gray-500 mt-1">Unmatched</div>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-xl font-bold text-gray-900">{{ fmt(reportData.data.total_order_value) }}</div>
                                <div class="text-xs text-gray-500 mt-1">Order Value</div>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="[key, val] in Object.entries(reportData.data).filter(([k]) => !['period'].includes(k))" :key="key" class="py-2">
                                    <td class="py-2 text-gray-600">{{ key.replace(/_/g, ' ') }}</td>
                                    <td class="py-2 text-right font-medium text-gray-900">
                                        {{ typeof val === 'number' && key.includes('value') || key.includes('settled') || key.includes('credits') ? fmt(val as number) : (val as any)?.toLocaleString?.('en-IN') ?? val }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Generic report data table -->
                    <div v-else-if="reportData?.data?.rows !== undefined" class="p-5">
                        <div class="flex gap-4 mb-4 text-sm">
                            <span v-if="reportData.data.count !== undefined" class="text-gray-600">
                                <strong class="text-gray-900">{{ reportData.data.count }}</strong> items
                            </span>
                            <span v-if="reportData.data.total_value !== undefined" class="text-gray-600">
                                Total: <strong>{{ fmt(reportData.data.total_value) }}</strong>
                            </span>
                        </div>

                        <div class="overflow-auto">
                            <table v-if="reportData.data.rows.length" class="w-full text-xs min-w-max">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th v-for="key in Object.keys(reportData.data.rows[0])" :key="key"
                                            class="text-left px-3 py-2 font-medium text-gray-600">
                                            {{ key.replace(/_/g, ' ') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(row, i) in reportData.data.rows" :key="i" class="hover:bg-gray-50">
                                        <td v-for="(val, key) in row" :key="key"
                                            :class="['px-3 py-2', typeof val === 'number' && String(key).includes('price') || String(key).includes('amount') || String(key).includes('value') ? 'text-right' : '']">
                                            {{ typeof val === 'number' ? val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : val }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="py-8 text-center text-gray-400">
                                No issues found —
                                <span v-if="activeReport === 'missing_settlements'">all orders have settlements</span>
                                <span v-else-if="activeReport === 'missing_credits'">all settlements matched to bank credits</span>
                                <span v-else>nothing to report</span>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="reportData.data.meta?.total > 50" class="flex justify-between items-center mt-4 text-sm text-gray-600">
                            <span>Showing {{ reportData.data.rows.length }} of {{ reportData.data.meta.total }} rows</span>
                        </div>
                    </div>

                    <div v-else-if="!reportLoading" class="py-10 text-center text-gray-400 text-sm">
                        Select a report type from the left
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
