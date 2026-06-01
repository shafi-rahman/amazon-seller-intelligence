<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const workspaceStore = useWorkspaceStore()

const loading  = ref(false)
const orders   = ref<any[]>([])
const meta     = ref<any>({})
const summary  = ref<any>(null)

const filters = ref({
    date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
    status: '',
    fulfillment_channel: '',
    search: '',
    page: 1,
    per_page: 50,
})

const STATUS_COLORS: Record<string, string> = {
    Shipped: 'bg-green-100 text-green-700',
    Cancelled: 'bg-red-100 text-red-600',
    Pending: 'bg-yellow-100 text-yellow-700',
    Unshipped: 'bg-blue-100 text-blue-700',
}

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '' && v !== null)
        )
        const [listRes, sumRes] = await Promise.all([
            api.get(`/workspaces/${wsId}/orders`, { params }),
            api.get(`/workspaces/${wsId}/orders/summary`, {
                params: { date_from: filters.value.date_from, date_to: filters.value.date_to }
            }),
        ])
        orders.value = listRes.data.data
        meta.value   = listRes.data.meta
        summary.value = sumRes.data.data
    } finally {
        loading.value = false
    }
}

function changePage(page: number) {
    filters.value.page = page
    load()
}

function applyFilters() {
    filters.value.page = 1
    load()
}

onMounted(load)

const formatCurrency = (v: number) => '₹' + (v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Orders</h1>
            <RouterLink to="/imports/upload" class="text-sm text-indigo-600 hover:underline">+ Upload orders CSV</RouterLink>
        </div>

        <!-- Summary cards -->
        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Orders</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ summary.total_orders?.toLocaleString('en-IN') }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Revenue</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(summary.total_revenue) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Total Tax</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(summary.total_tax) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">Units Sold</div>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ summary.total_units?.toLocaleString('en-IN') }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-4">
            <input type="date" v-model="filters.date_from" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input type="date" v-model="filters.date_to" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <select v-model="filters.status" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option>Shipped</option><option>Cancelled</option><option>Pending</option><option>Unshipped</option>
            </select>
            <select v-model="filters.fulfillment_channel" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">All channels</option>
                <option value="AFN">FBA (AFN)</option>
                <option value="MFN">Self-ship (MFN)</option>
            </select>
            <input v-model="filters.search" type="text" placeholder="Search order ID, SKU…"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-48" />
            <button @click="applyFilters" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">Apply</button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading orders…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Order ID</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">SKU / ASIN</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Channel</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Qty</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Tax</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="orders.length === 0">
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400">No orders found for this period. <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">Upload orders CSV</RouterLink></td>
                    </tr>
                    <tr v-for="o in orders" :key="o.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ o.amazon_order_id }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ o.purchase_date?.slice(0, 10) }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[o.order_status] ?? 'bg-gray-100 text-gray-600']">
                                {{ o.order_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="font-mono text-gray-800">{{ o.sku }}</div>
                            <div class="text-gray-400 font-mono">{{ o.asin }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ o.fulfillment_channel === 'AFN' ? 'FBA' : o.fulfillment_channel === 'MFN' ? 'Self' : o.fulfillment_channel }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">{{ o.quantity }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">{{ formatCurrency(o.item_price) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ formatCurrency(o.item_tax) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">{{ formatCurrency(o.net_amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} orders</span>
            <div class="flex gap-2">
                <button @click="changePage(meta.page - 1)" :disabled="meta.page <= 1"
                    class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="changePage(meta.page + 1)" :disabled="meta.page >= meta.last_page"
                    class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
