<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import api from '@/api/axios'

const route          = useRoute()
const workspaceStore = useWorkspaceStore()

const gaps    = ref<any[]>([])
const meta    = ref<any>({})
const loading = ref(false)
const gapType = ref('')
const page    = ref(1)
const productId = route.params.productId as string  // UUID

const GAP_COLORS: Record<string, string> = {
    missing:   'bg-red-100 text-red-700',
    underused: 'bg-orange-100 text-orange-700',
    advantage: 'bg-green-100 text-green-700',
}
const GAP_LABELS: Record<string, string> = {
    missing:   'Missing',
    underused: 'Underused',
    advantage: 'Advantage',
}

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    loading.value = true
    try {
        const params: any = { page: page.value, per_page: 50 }
        if (gapType.value) params.gap_type = gapType.value
        const { data } = await api.get(`/workspaces/${wsId}/products/${productId}/keyword-gaps`, { params })
        gaps.value = data.data
        meta.value = data.meta ?? {}
    } finally {
        loading.value = false
    }
}

onMounted(load)

function priorityColor(score: number) {
    if (score >= 75) return 'text-red-600 font-bold'
    if (score >= 50) return 'text-orange-600 font-medium'
    return 'text-gray-500'
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="`/products/${productId}/competitors`" class="text-sm text-indigo-600 hover:underline">← Competitors</RouterLink>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900">Keyword Gaps</h1>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 mb-4">
            <button v-for="(label, type) in { '': 'All', missing: 'Missing', underused: 'Underused', advantage: 'Advantages' }"
                :key="type"
                @click="gapType = type; page = 1; load()"
                :class="['px-3 py-1.5 text-xs rounded-full font-medium transition-colors',
                    gapType === type ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                {{ label }}
            </button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">Loading keyword gaps…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Keyword</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Gap Type</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Our Freq</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Their Freq</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Priority</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="gaps.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                            No keyword gaps found. Run competitor analysis first.
                        </td>
                    </tr>
                    <tr v-for="g in gaps" :key="g.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-800">{{ g.keyword }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', GAP_COLORS[g.gap_type] ?? 'bg-gray-100 text-gray-600']">
                                {{ GAP_LABELS[g.gap_type] ?? g.gap_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">{{ g.our_frequency }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">{{ g.their_frequency }}</td>
                        <td class="px-4 py-3 text-right text-xs" :class="priorityColor(g.priority_score)">
                            {{ g.priority_score }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} keywords</span>
            <div class="flex gap-2">
                <button @click="() => { page--; load() }" :disabled="page <= 1" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40">Previous</button>
                <button @click="() => { page++; load() }" :disabled="page >= meta.last_page" class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>
</template>
