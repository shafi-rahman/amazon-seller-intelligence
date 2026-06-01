<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const workspaceStore = useWorkspaceStore()

const loading    = ref(false)
const dashboard  = ref<Record<string, any> | null>(null)
const dateFrom   = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const dateTo     = ref(new Date().toISOString().split('T')[0])

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const { data } = await api.get(`/workspaces/${wsId}/finance/dashboard`, {
            params: { date_from: dateFrom.value, date_to: dateTo.value },
        })
        dashboard.value = data.data
    } finally {
        loading.value = false
    }
}

onMounted(load)

function formatCurrency(val: number) {
    return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function formatNum(val: number) {
    return val.toLocaleString('en-IN')
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Financial Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">Summary for the selected period</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" v-model="dateFrom" @change="load"
                    class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" v-model="dateTo" @change="load"
                    class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-gray-400 text-sm py-8 text-center">Loading financial data…</div>

        <template v-else-if="dashboard">
            <!-- Data availability -->
            <div class="flex gap-2 mb-6">
                <span v-for="(available, type) in dashboard.data_availability" :key="type"
                    :class="['px-3 py-1 rounded-full text-xs font-medium', available ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400']">
                    {{ { orders: 'Orders', settlements: 'Settlements', bank_statement: 'Bank', gst_report: 'GST' }[type] }}
                    {{ available ? '✓' : '—' }}
                </span>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="text-xs font-medium text-gray-500 mb-1">Total Revenue</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboard.orders_summary.total_revenue) }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ formatNum(dashboard.orders_summary.total_orders) }} orders</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="text-xs font-medium text-gray-500 mb-1">Total Settled</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboard.settlements_summary.total_deposited) }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ dashboard.settlements_summary.settlement_cycles }} cycles</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="text-xs font-medium text-gray-500 mb-1">Bank Credits</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboard.bank_summary.amazon_credits) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Amazon credits received</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="text-xs font-medium text-gray-500 mb-1">Tax Collected</div>
                    <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboard.gst_summary.total_tax) }}</div>
                    <div class="text-xs text-gray-400 mt-1">IGST + CGST + SGST</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Orders by status -->
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Orders by Status</h3>
                    <div class="space-y-2">
                        <div v-for="(count, status) in dashboard.orders_summary.by_status" :key="status"
                            class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ status }}</span>
                            <span class="font-medium text-gray-900">{{ formatNum(count) }}</span>
                        </div>
                        <div v-if="!Object.keys(dashboard.orders_summary.by_status || {}).length"
                            class="text-gray-400 text-xs">No orders imported yet</div>
                    </div>
                </div>

                <!-- Orders by fulfillment -->
                <div class="bg-white rounded-lg border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Fulfillment Channel</h3>
                    <div class="space-y-2">
                        <div v-for="(count, channel) in dashboard.orders_summary.by_fulfillment" :key="channel"
                            class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ channel === 'AFN' ? 'FBA (Amazon Fulfilled)' : channel === 'MFN' ? 'Self-ship (MFN)' : channel }}</span>
                            <span class="font-medium text-gray-900">{{ formatNum(count) }}</span>
                        </div>
                        <div v-if="!Object.keys(dashboard.orders_summary.by_fulfillment || {}).length"
                            class="text-gray-400 text-xs">No fulfillment data</div>
                    </div>
                </div>

                <!-- Top products -->
                <div class="bg-white rounded-lg border border-gray-200 p-5 lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Top Products by Revenue</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                                <th class="pb-2 font-medium">SKU</th>
                                <th class="pb-2 font-medium">ASIN</th>
                                <th class="pb-2 font-medium text-right">Units Sold</th>
                                <th class="pb-2 font-medium text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="p in dashboard.top_products" :key="p.sku" class="py-2">
                                <td class="py-2 text-gray-800 font-mono text-xs">{{ p.sku }}</td>
                                <td class="py-2 text-gray-600 font-mono text-xs">{{ p.asin }}</td>
                                <td class="py-2 text-right text-gray-800">{{ formatNum(p.units) }}</td>
                                <td class="py-2 text-right text-gray-800 font-medium">{{ formatCurrency(p.revenue) }}</td>
                            </tr>
                            <tr v-if="!dashboard.top_products?.length">
                                <td colspan="4" class="py-4 text-center text-gray-400 text-xs">No shipped orders for this period</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <div v-else class="text-center py-16 text-gray-400">
            <p class="text-lg font-medium mb-2">No financial data yet</p>
            <p class="text-sm">Import your Amazon Orders, Settlements, Bank Statement, or GST Report to get started.</p>
            <RouterLink to="/imports/upload" class="mt-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 transition-colors">
                Upload data
            </RouterLink>
        </div>
    </div>
</template>
