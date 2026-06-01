<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const route          = useRoute()
const router         = useRouter()
const workspaceStore = useWorkspaceStore()

const product    = ref<any>(null)
const competitors = ref<any[]>([])
const meta       = ref<any>({})
const loading    = ref(false)
const page       = ref(1)

const productId = Number(route.params.productId)

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const [prodRes, compRes] = await Promise.all([
            api.get(`/workspaces/${wsId}/products/${productId}`),
            api.get(`/workspaces/${wsId}/products/${productId}/competitors`, { params: { page: page.value } }),
        ])
        product.value     = prodRes.data.data ?? prodRes.data
        competitors.value = compRes.data.data
        meta.value        = compRes.data.meta ?? {}
    } finally {
        loading.value = false
    }
}

onMounted(load)

async function triggerAnalysis(competitorId: number) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await api.post(`/workspaces/${wsId}/products/${productId}/competitors/${competitorId}/analyze`)
    await load()
}

const fmt = (v: number | null) => v == null ? '—' : '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="`/products/${productId}`" class="text-sm text-indigo-600 hover:underline">← {{ product?.asin }}</RouterLink>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900">Competitors</h1>
        </div>

        <!-- Our product summary -->
        <div v-if="product" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-indigo-600 mb-0.5">OUR PRODUCT</div>
                <div class="text-sm font-medium text-gray-900 truncate max-w-sm">{{ product.title }}</div>
                <div class="text-xs text-gray-500 mt-1">Score: {{ product.listing_score ?? 'Unanalyzed' }}/100 · {{ product.asin }}</div>
            </div>
            <div class="flex gap-2">
                <RouterLink :to="`/products/${productId}/keyword-gaps`"
                    class="px-3 py-1.5 text-xs border border-indigo-300 text-indigo-700 rounded-md hover:bg-indigo-100 transition-colors">
                    Keyword Gaps
                </RouterLink>
                <RouterLink :to="`/products/${productId}/benchmark`"
                    class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                    Benchmark
                </RouterLink>
            </div>
        </div>

        <!-- Competitors table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading competitors…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">ASIN</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Title</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Brand</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Rating</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Source</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="competitors.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                            No competitors added yet.
                            <RouterLink to="/imports/html" class="text-indigo-600 hover:underline">Paste HTML</RouterLink>
                            or
                            <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">upload CSV</RouterLink>
                            to add competitors.
                        </td>
                    </tr>
                    <tr v-for="c in competitors" :key="c.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ c.asin }}</td>
                        <td class="px-4 py-3 max-w-xs">
                            <div class="text-xs text-gray-800 truncate">{{ c.title ?? '—' }}</div>
                            <div v-if="c.low_confidence_fields?.length" class="text-xs text-orange-500 mt-0.5">
                                ⚠ Low confidence: {{ c.low_confidence_fields.join(', ') }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ c.brand ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">{{ fmt(c.price) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">
                            {{ c.rating ? `${c.rating}★ (${c.review_count})` : '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span :class="c.source_type === 'html' ? 'text-blue-600' : 'text-gray-500'">
                                {{ c.source_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-xs space-x-2">
                            <RouterLink :to="`/products/${productId}/competitors/${c.id}`" class="text-indigo-600 hover:underline">View</RouterLink>
                            <button @click="triggerAnalysis(c.id)" class="text-gray-500 hover:text-indigo-600">Re-analyze</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <button @click="() => { page--; load() }" :disabled="page <= 1" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="() => { page++; load() }" :disabled="page >= meta.last_page" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
