<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const workspaceStore = useWorkspaceStore()
const loading  = ref(false)
const rows     = ref<any[]>([])
const meta     = ref<any>({})
const summary  = ref<any>(null)

const filters = ref({
    date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
    settlement_id: '',
    transaction_type: '',
    page: 1,
    per_page: 50,
})

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const params = Object.fromEntries(Object.entries(filters.value).filter(([,v]) => v !== ''))
        const [listRes, sumRes] = await Promise.all([
            api.get(`/workspaces/${wsId}/settlements`, { params }),
            api.get(`/workspaces/${wsId}/settlements/summary`, {
                params: { date_from: filters.value.date_from, date_to: filters.value.date_to }
            }),
        ])
        rows.value    = listRes.data.data
        meta.value    = listRes.data.meta
        summary.value = sumRes.data.data
    } finally {
        loading.value = false
    }
}

onMounted(load)

const fmt = (v: number) => '₹' + (v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Settlements</h1>
            <RouterLink to="/imports/upload" class="text-sm text-indigo-600 hover:underline">+ Upload settlement CSV</RouterLink>
        </div>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Settlement Cycles</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ summary.settlement_cycles }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Deposited</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ fmt(summary.total_deposited) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Gross Payments</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ fmt(summary.gross_payments) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Fees Deducted</div>
                <div class="text-xl font-bold text-red-600 mt-1">{{ fmt(summary.total_fees) }}</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <input type="date" v-model="filters.date_from" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input type="date" v-model="filters.date_to" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input v-model="filters.settlement_id" type="text" placeholder="Settlement ID…"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-40" />
            <button @click="() => { filters.page = 1; load() }" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">Apply</button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading settlements…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Settlement ID</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Deposit Date</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Type</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Order ID</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Description</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">No settlements found. <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">Upload settlement CSV</RouterLink></td>
                    </tr>
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ r.settlement_id }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ r.deposit_date }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ r.transaction_type }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ r.order_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600 max-w-xs truncate">{{ r.amount_description }}</td>
                        <td :class="['px-4 py-3 text-right text-xs font-medium', r.amount >= 0 ? 'text-green-700' : 'text-red-600']">
                            {{ fmt(r.amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} rows</span>
            <div class="flex gap-2">
                <button @click="() => { filters.page--; load() }" :disabled="filters.page <= 1" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="() => { filters.page++; load() }" :disabled="filters.page >= meta.last_page" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
