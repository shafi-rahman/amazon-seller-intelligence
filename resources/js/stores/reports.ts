import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export interface ReportRecord {
    id: number
    type: string
    title: string
    file_format: string
    status: 'pending' | 'generating' | 'completed' | 'failed'
    parameters: Record<string, unknown>
    generated_at: string | null
    created_at: string
    has_file: boolean
}

export const useReportsStore = defineStore('reports', () => {
    const reports  = ref<ReportRecord[]>([])
    const types    = ref<Record<string, { title: string; formats: string[] }>>({})
    const loading  = ref(false)
    const polling  = ref<Map<number, ReturnType<typeof setInterval>>>(new Map())

    async function fetchTypes(workspaceId: number): Promise<void> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reports/types`)
        types.value = data.data ?? data
    }

    async function fetchReports(workspaceId: number, params: Record<string, string> = {}): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/reports`, { params })
            reports.value = data.data ?? data
        } finally {
            loading.value = false
        }
    }

    async function requestReport(workspaceId: number, type: string, format: string, parameters: Record<string, unknown> = {}): Promise<ReportRecord> {
        const { data } = await api.post(`/workspaces/${workspaceId}/reports`, { type, format, parameters })
        const report   = data.data ?? data
        reports.value.unshift(report)
        startPolling(workspaceId, report.id)
        return report
    }

    async function pollStatus(workspaceId: number, reportId: number): Promise<ReportRecord> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reports/${reportId}`)
        const updated  = data.data ?? data
        const idx = reports.value.findIndex(r => r.id === reportId)
        if (idx !== -1) reports.value[idx] = updated
        if (['completed', 'failed'].includes(updated.status)) {
            stopPolling(reportId)
        }
        return updated
    }

    async function getDownloadUrl(workspaceId: number, reportId: number): Promise<string> {
        const { data } = await api.get(`/workspaces/${workspaceId}/reports/${reportId}/download`)
        return (data.data ?? data).url
    }

    function startPolling(workspaceId: number, reportId: number): void {
        const timer = setInterval(() => pollStatus(workspaceId, reportId), 3000)
        polling.value.set(reportId, timer)
    }

    function stopPolling(reportId: number): void {
        const timer = polling.value.get(reportId)
        if (timer) {
            clearInterval(timer)
            polling.value.delete(reportId)
        }
    }

    return { reports, types, loading, fetchTypes, fetchReports, requestReport, pollStatus, getDownloadUrl }
})
