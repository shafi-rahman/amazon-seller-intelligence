<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useReconciliationStore } from '@/stores/reconciliation'

const router            = useRouter()
const workspaceStore    = useWorkspaceStore()
const reconciliationStore = useReconciliationStore()

const step = ref<'select' | 'running' | 'done' | 'error'>('select')
const runId   = ref<number | null>(null)
const runData = ref<any>(null)
const error   = ref('')
const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)

// Default to current month
const today = new Date()
const periodStart = ref(new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0])
const periodEnd   = ref(today.toISOString().split('T')[0])

async function startReconciliation() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    error.value = ''
    step.value  = 'running'
    try {
        const result = await reconciliationStore.startRun(wsId, periodStart.value, periodEnd.value)
        runId.value  = result.reconciliation_run_id
        pollTimer.value = setInterval(() => poll(wsId), 3000)
    } catch (e: any) {
        error.value = e.response?.data?.message ?? 'Failed to start reconciliation'
        step.value  = 'error'
    }
}

async function poll(wsId: number) {
    if (!runId.value) return
    const status = await reconciliationStore.pollStatus(wsId, runId.value)
    if (status.status === 'completed') {
        clearInterval(pollTimer.value!)
        runData.value = status
        step.value    = 'done'
    } else if (status.status === 'failed') {
        clearInterval(pollTimer.value!)
        error.value = status.summary?.error ?? 'Reconciliation failed'
        step.value  = 'error'
    }
}

onUnmounted(() => { if (pollTimer.value) clearInterval(pollTimer.value) })

function viewResults() {
    router.push(`/reconciliation/${runId.value}`)
}

const fmt = (v: number) => '₹' + (v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })
</script>

<template>
    <div class="p-6 max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Run Reconciliation</h1>
            <p class="text-gray-500 text-sm mt-1">Match orders → settlements → bank credits for a period</p>
        </div>

        <!-- Step 1: Select period -->
        <div v-if="step === 'select'" class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Select reconciliation period</h2>

            <div class="flex items-center gap-4 mb-6">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Period Start</label>
                    <input type="date" v-model="periodStart"
                        class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="mt-4 text-gray-400">to</div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Period End</label>
                    <input type="date" v-model="periodEnd"
                        class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6 text-sm text-blue-700">
                <strong>What this does:</strong> Matches your imported orders to settlement rows, then settlement cycles to bank credits.
                Re-running for the same period creates a new run and preserves the previous one.
            </div>

            <div class="flex justify-end gap-3">
                <RouterLink to="/reconciliation" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">View history</RouterLink>
                <button @click="startReconciliation"
                    class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Start Reconciliation
                </button>
            </div>
        </div>

        <!-- Step 2: Running -->
        <div v-else-if="step === 'running'" class="bg-white rounded-lg border border-gray-200 p-8 text-center">
            <div class="flex justify-center mb-4">
                <svg class="animate-spin w-10 h-10 text-indigo-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Reconciliation in progress…</h2>
            <p class="text-sm text-gray-500">
                Matching orders → settlements → bank credits for
                <strong>{{ periodStart }} to {{ periodEnd }}</strong>
            </p>
            <p class="text-xs text-gray-400 mt-2">This usually takes 10–60 seconds depending on data volume</p>
        </div>

        <!-- Step 3: Done -->
        <div v-else-if="step === 'done' && runData" class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Reconciliation Complete</h2>
                    <p class="text-xs text-gray-500">Run #{{ runId }} · {{ periodStart }} to {{ periodEnd }}</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-6" v-if="runData.summary">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900">{{ runData.summary.total_orders?.toLocaleString('en-IN') }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total Orders</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-700">{{ runData.summary.matched_orders?.toLocaleString('en-IN') }}</div>
                    <div class="text-xs text-gray-500 mt-1">Matched</div>
                </div>
                <div class="text-center p-4 rounded-lg" :class="(runData.summary.unmatched_orders ?? 0) > 0 ? 'bg-red-50' : 'bg-gray-50'">
                    <div class="text-2xl font-bold" :class="(runData.summary.unmatched_orders ?? 0) > 0 ? 'text-red-600' : 'text-gray-400'">
                        {{ runData.summary.unmatched_orders?.toLocaleString('en-IN') }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Unmatched</div>
                </div>
            </div>

            <button @click="viewResults"
                class="w-full py-2.5 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors">
                View Full Report →
            </button>
        </div>

        <!-- Error -->
        <div v-else-if="step === 'error'" class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 class="text-base font-semibold text-red-700 mb-2">Reconciliation Failed</h2>
            <p class="text-sm text-red-600 mb-4">{{ error }}</p>
            <button @click="step = 'select'" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">Try again</button>
        </div>
    </div>
</template>
