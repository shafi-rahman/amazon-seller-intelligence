import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useReconciliationStore = defineStore('reconciliation', () => {
    const runs    = ref<any[]>([])
    const current = ref<any | null>(null)
    const loading = ref(false)

    async function fetchRuns(workspaceId: number): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/reconciliation`)
            runs.value = data.data ?? data
        } finally {
            loading.value = false
        }
    }

    async function startRun(workspaceId: number, periodStart: string, periodEnd: string): Promise<{ reconciliation_run_id: number }> {
        const { data } = await api.post(`/workspaces/${workspaceId}/reconciliation/run`, {
            period_start: periodStart,
            period_end:   periodEnd,
        })
        return data.data ?? data
    }

    async function pollStatus(workspaceId: number, runId: number): Promise<any> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reconciliation/${runId}/status`)
        return data.data ?? data
    }

    async function fetchRun(workspaceId: number, runId: number): Promise<any> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reconciliation/${runId}`)
        current.value  = data.data ?? data
        return current.value
    }

    async function fetchReport(workspaceId: number, runId: number, type: string, page = 1): Promise<any> {
        const { data } = await api.get(
            `/workspaces/${workspaceId}/reconciliation/${runId}/reports/${type}`,
            { params: { page } }
        )
        return data.data ?? data
    }

    async function requestExport(workspaceId: number, runId: number, type: string, format: string): Promise<void> {
        await api.post(`/workspaces/${workspaceId}/reconciliation/${runId}/reports/${type}/export`, { format })
    }

    async function getDownloadUrl(workspaceId: number, reportId: number): Promise<string> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reconciliation/reports/${reportId}/download`)
        return (data.data ?? data).url
    }

    return { runs, current, loading, fetchRuns, startRun, pollStatus, fetchRun, fetchReport, requestExport, getDownloadUrl }
})
