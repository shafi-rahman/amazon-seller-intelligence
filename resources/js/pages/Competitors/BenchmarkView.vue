<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const route          = useRoute()
const workspaceStore = useWorkspaceStore()

const loading   = ref(false)
const data      = ref<any>(null)
const productId = Number(route.params.productId)

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const { data: res } = await api.get(`/workspaces/${wsId}/products/${productId}/benchmark`)
        data.value = res.data ?? res
    } finally {
        loading.value = false
    }
})

function deltaColor(val: number | null, invert = false): string {
    if (val == null) return 'text-gray-400'
    const positive = invert ? val < 0 : val > 0
    return positive ? 'text-green-600' : val === 0 ? 'text-gray-400' : 'text-red-600'
}
function verdictEmoji(v: string): string {
    return { better: '✓', worse: '✗', equal: '=', unknown: '—' }[v] ?? '—'
}
function verdictColor(v: string): string {
    return { better: 'text-green-600', worse: 'text-red-600', equal: 'text-gray-500', unknown: 'text-gray-400' }[v] ?? ''
}
const fmt  = (v: number | null) => v == null ? '—' : '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2 })
const fmtD = (v: number | null, invert = false) => {
    if (v == null) return '—'
    const s = (invert ? -v : v) >= 0 ? '+' : ''
    return s + v.toLocaleString('en-IN', { minimumFractionDigits: 2 })
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="`/products/${productId}/competitors`" class="text-sm text-indigo-600 hover:underline">← Competitors</RouterLink>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900">Benchmark</h1>
        </div>

        <div v-if="loading" class="text-gray-400 text-sm text-center py-10">Loading benchmark data…</div>

        <div v-else-if="!data || !data.competitors?.length" class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400">
            <p class="font-medium mb-2">No benchmark data yet</p>
            <p class="text-sm">Add competitors and run analysis to see how your listing compares.</p>
        </div>

        <div v-else>
            <!-- Consensus quick wins -->
            <div v-if="data.consensus_gaps?.length" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-amber-800 mb-2">
                    Consensus Quick Wins — Keywords missing from ≥50% of competitors
                </h3>
                <div class="flex flex-wrap gap-2">
                    <span v-for="gap in data.consensus_gaps" :key="gap.keyword"
                        class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full font-mono">
                        {{ gap.keyword }} ({{ gap.in_competitors }}/{{ data.competitor_count }} competitors)
                    </span>
                </div>
            </div>

            <!-- Per-competitor benchmark cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="comp in data.competitors" :key="comp.competitor_id"
                    class="bg-white rounded-lg border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-xs text-gray-500 font-mono">{{ comp.their_asin }}</div>
                            <div class="text-sm font-semibold text-gray-800 mt-0.5">vs. Competitor</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Their score</div>
                            <div class="text-xl font-bold text-gray-900">{{ comp.their_listing_score }}<span class="text-sm text-gray-400">/100</span></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Metric rows -->
                        <div v-for="(row, key) in {
                            'Listing Score': { ours: data.product.listing_score, theirs: comp.their_listing_score, delta: comp.listing_score_delta, verdict: comp.verdict?.listing_quality },
                            'Price': { ours: data.product.price, theirs: comp.their_price, delta: comp.price_delta, fmt: true, verdict: comp.verdict?.price_position },
                            'Rating': { ours: data.product.rating, theirs: comp.their_rating, delta: comp.rating_delta, verdict: null },
                            'Reviews': { ours: data.product.review_count, theirs: comp.their_review_count, delta: comp.review_count_delta, verdict: comp.verdict?.review_authority },
                        }" :key="key" class="bg-gray-50 rounded-md p-3">
                            <div class="text-xs text-gray-500 mb-1">{{ key }}</div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-sm font-bold text-gray-900">
                                    {{ key === 'Price' ? fmt(row.ours) : row.ours?.toLocaleString?.('en-IN') ?? '—' }}
                                </span>
                                <span class="text-xs text-gray-400">vs</span>
                                <span class="text-sm text-gray-600">
                                    {{ key === 'Price' ? fmt(row.theirs) : row.theirs?.toLocaleString?.('en-IN') ?? '—' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1 mt-1">
                                <span :class="['text-xs', deltaColor(row.delta, key === 'Price')]">
                                    {{ fmtD(row.delta, key === 'Price') }}
                                </span>
                                <span v-if="row.verdict" :class="['text-xs font-bold', verdictColor(row.verdict)]">
                                    {{ verdictEmoji(row.verdict) }} {{ row.verdict }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Keyword metrics -->
                    <div class="mt-3 pt-3 border-t border-gray-100 flex gap-4 text-xs">
                        <span class="text-gray-500">Overlap: <strong>{{ comp.keyword_overlap }}</strong></span>
                        <span class="text-red-500">We lack: <strong>{{ comp.keywords_we_lack }}</strong></span>
                        <span class="text-green-600">They lack: <strong>{{ comp.keywords_they_lack }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
