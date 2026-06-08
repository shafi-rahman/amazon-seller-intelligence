<script setup lang="ts">
import { onMounted } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import { useReconciliationStore } from '@/stores/reconciliation'

const workspaceStore      = useWorkspaceStore()
const reconciliationStore = useReconciliationStore()

const STATUS_COLORS: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    running:   'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    failed:    'bg-red-100 text-red-600',
}

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (wsId) await reconciliationStore.fetchRuns(wsId)
})

const fmt = (v: number) => '₹' + (v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reconciliation</h1>
                <p class="text-gray-500 text-sm mt-1">History of all reconciliation runs</p>
            </div>
            <RouterLink to="/reconciliation/run"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors">
                + New Run
            </RouterLink>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="reconciliationStore.loading" class="py-10 text-center text-gray-400 text-sm">Loading…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Run #</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Period</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Status</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Orders</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Matched</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Unmatched</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="reconciliationStore.runs.length === 0">
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                            No reconciliation runs yet.
                            <RouterLink to="/reconciliation/run" class="text-indigo-600 hover:underline">Run your first reconciliation</RouterLink>
                        </td>
                    </tr>
                    <tr v-for="run in reconciliationStore.runs" :key="run.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs" :title="run.id">{{ run.id?.slice(0, 8) }}…</td>
                        <td class="px-4 py-3 text-gray-700 text-xs">{{ run.period_start }} → {{ run.period_end }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[run.status] ?? 'bg-gray-100 text-gray-600']">
                                {{ run.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-800">{{ run.summary?.total_orders?.toLocaleString('en-IN') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-green-700">{{ run.summary?.matched_orders?.toLocaleString('en-IN') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs" :class="(run.summary?.unmatched_orders ?? 0) > 0 ? 'text-red-600' : 'text-gray-400'">
                            {{ run.summary?.unmatched_orders?.toLocaleString('en-IN') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ new Date(run.created_at).toLocaleDateString('en-IN') }}</td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink v-if="run.status === 'completed'" :to="`/reconciliation/${run.id}`"
                                class="text-xs text-indigo-600 hover:underline">View</RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
