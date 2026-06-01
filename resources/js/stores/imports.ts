import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'
import type { ImportBatch, ImportStatus } from '@/types'

export const useImportStore = defineStore('imports', () => {
    const batches = ref<ImportBatch[]>([])
    const loading = ref(false)

    async function fetchBatches(workspaceId: number, params: Record<string, string> = {}): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/imports`, { params })
            batches.value = data.data ?? data
        } finally {
            loading.value = false
        }
    }

    async function upload(workspaceId: number, type: string, file: File): Promise<ImportBatch> {
        const form = new FormData()
        form.append('workspace_id', String(workspaceId))
        form.append('type', type)
        form.append('file', file)
        const { data } = await api.post('/imports/upload', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        return data.data ?? data
    }

    async function uploadHtml(workspaceId: number, htmlContent: string, productId: number | null, asin?: string): Promise<ImportBatch> {
        const { data } = await api.post('/imports/competitors/html', {
            workspace_id: workspaceId,
            html_content: htmlContent,
            product_id: productId,
            asin,
        })
        return data.data ?? data
    }

    async function confirmMapping(batchId: number, mapping: Record<string, string | null>): Promise<void> {
        await api.post(`/imports/${batchId}/confirm-mapping`, { mapping })
    }

    async function pollStatus(batchId: number): Promise<ImportStatus> {
        const { data } = await api.get(`/imports/${batchId}/status`)
        return data.data ?? data
    }

    async function fetchErrors(batchId: number, page = 1): Promise<{ data: unknown[]; meta: unknown }> {
        const { data } = await api.get(`/imports/${batchId}/errors`, { params: { page } })
        return data
    }

    return { batches, loading, fetchBatches, upload, uploadHtml, confirmMapping, pollStatus, fetchErrors }
})
