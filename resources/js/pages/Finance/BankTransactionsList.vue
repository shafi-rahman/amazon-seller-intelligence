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
    type: '',
    search: '',
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
            api.get(`/workspaces/${wsId}/bank-transactions`, { params }),
            api.get(`/workspaces/${wsId}/bank-transactions/summary`, {
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
            <h1 class="text-2xl font-bold text-gray-900">Bank Transactions</h1>
            <RouterLink to="/imports/upload" class="text-sm text-indigo-600 hover:underline">+ Upload bank statement</RouterLink>
        </div>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Transactions</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ summary.total_transactions?.toLocaleString('en-IN') }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Credits</div>
                <div class="text-xl font-bold text-green-700 mt-1">{{ fmt(summary.total_credits) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Amazon Credits</div>
                <div class="text-xl font-bold text-indigo-700 mt-1">{{ fmt(summary.amazon_credits) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Net Cash Flow</div>
                <div :class="['text-xl font-bold mt-1', summary.net_cashflow >= 0 ? 'text-green-700' : 'text-red-600']">{{ fmt(summary.net_cashflow) }}</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <input type="date" v-model="filters.date_from" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input type="date" v-model="filters.date_to" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <select v-model="filters.type" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">All transactions</option>
                <option value="credit">Credits only</option>
                <option value="debit">Debits only</option>
            </select>
            <input v-model="filters.search" type="text" placeholder="Search description…"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-48" />
            <button @click="() => { filters.page = 1; load() }" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">Apply</button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading transactions…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Description</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Reference</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Debit</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Credit</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">No transactions. <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">Upload bank statement</RouterLink></td>
                    </tr>
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50" :class="r.is_amazon_credit ? 'bg-indigo-50/30' : ''">
                        <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{{ r.transaction_date }}</td>
                        <td class="px-4 py-3 text-xs text-gray-700 max-w-xs">
                            <span class="line-clamp-1">{{ r.description }}</span>
                            <span v-if="r.is_amazon_credit" class="text-xs text-indigo-500 font-medium">Amazon</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ r.reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-red-600">{{ r.debit_amount > 0 ? fmt(r.debit_amount) : '' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-green-700 font-medium">{{ r.credit_amount > 0 ? fmt(r.credit_amount) : '' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-600">{{ r.balance != null ? fmt(r.balance) : '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} transactions</span>
            <div class="flex gap-2">
                <button @click="() => { filters.page--; load() }" :disabled="filters.page <= 1" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="() => { filters.page++; load() }" :disabled="filters.page >= meta.last_page" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
