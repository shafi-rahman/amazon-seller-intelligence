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
    transaction_type: '',
    order_id: '',
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
            api.get(`/workspaces/${wsId}/gst-transactions`, { params }),
            api.get(`/workspaces/${wsId}/gst-transactions/summary`, {
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
const fmt = (v: number | null) => v == null ? '—' : '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">GST Transactions</h1>
            <RouterLink to="/imports/upload" class="text-sm text-indigo-600 hover:underline">+ Upload GST report</RouterLink>
        </div>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Invoices</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ summary.total_invoices?.toLocaleString('en-IN') }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Taxable Value</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ fmt(summary.total_taxable) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Tax</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ fmt(summary.total_tax) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500 mb-1">Breakdown</div>
                <div class="text-xs space-y-0.5">
                    <div class="flex justify-between"><span class="text-gray-500">IGST</span><span class="font-medium">{{ fmt(summary.total_igst) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">CGST</span><span class="font-medium">{{ fmt(summary.total_cgst) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">SGST</span><span class="font-medium">{{ fmt(summary.total_sgst) }}</span></div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <input type="date" v-model="filters.date_from" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input type="date" v-model="filters.date_to" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <select v-model="filters.transaction_type" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">All types</option>
                <option>SALE</option><option>RETURN</option><option>CANCELLATION</option>
            </select>
            <input v-model="filters.order_id" type="text" placeholder="Order ID…"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-40 font-mono" />
            <button @click="() => { filters.page = 1; load() }" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">Apply</button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-auto">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading GST data…</div>
            <table v-else class="w-full text-xs min-w-max">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">Invoice Date</th>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">Invoice No.</th>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">Order ID</th>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">Type</th>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">SKU</th>
                        <th class="text-left px-3 py-3 font-medium text-gray-600">Ship To</th>
                        <th class="text-right px-3 py-3 font-medium text-gray-600">Taxable</th>
                        <th class="text-right px-3 py-3 font-medium text-gray-600">IGST</th>
                        <th class="text-right px-3 py-3 font-medium text-gray-600">CGST</th>
                        <th class="text-right px-3 py-3 font-medium text-gray-600">SGST</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="rows.length === 0">
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400">No GST data. <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">Upload GST report</RouterLink></td>
                    </tr>
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-600">{{ r.invoice_date }}</td>
                        <td class="px-3 py-2 font-mono text-gray-700">{{ r.invoice_number }}</td>
                        <td class="px-3 py-2 font-mono text-gray-600">{{ r.order_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ r.transaction_type }}</td>
                        <td class="px-3 py-2 font-mono text-gray-600">{{ r.sku ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ r.ship_to_state }}</td>
                        <td class="px-3 py-2 text-right text-gray-800">{{ fmt(r.taxable_value) }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ fmt(r.igst_amount) }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ fmt(r.cgst_amount) }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ fmt(r.sgst_amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} records</span>
            <div class="flex gap-2">
                <button @click="() => { filters.page--; load() }" :disabled="filters.page <= 1" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="() => { filters.page++; load() }" :disabled="filters.page >= meta.last_page" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
