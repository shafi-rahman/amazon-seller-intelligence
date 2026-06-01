import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useProductsStore = defineStore('products', () => {
    const products    = ref<any[]>([])
    const current     = ref<any | null>(null)
    const loading     = ref(false)
    const rewrite     = ref<any | null>(null)
    const rewriteLoading = ref(false)

    async function fetchAll(workspaceId: number, params: Record<string, any> = {}): Promise<any> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/products`, { params })
            products.value = data.data
            return data
        } finally {
            loading.value = false
        }
    }

    async function fetchOne(workspaceId: number, productId: number): Promise<any> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/products/${productId}`)
            current.value  = data.data ?? data
            return current.value
        } finally {
            loading.value = false
        }
    }

    async function triggerAnalysis(workspaceId: number, productId: number): Promise<void> {
        await api.post(`/workspaces/${workspaceId}/products/${productId}/analyze`)
    }

    async function generateRewrite(workspaceId: number, productId: number): Promise<void> {
        rewriteLoading.value = true
        try {
            const { data } = await api.post(`/workspaces/${workspaceId}/products/${productId}/rewrite`)
            rewrite.value  = (data.data ?? data).rewrite
        } finally {
            rewriteLoading.value = false
        }
    }

    async function applyRewrite(workspaceId: number, productId: number, fields: Record<string, string>): Promise<any> {
        const { data } = await api.post(`/workspaces/${workspaceId}/products/${productId}/apply-rewrite`, fields)
        current.value  = data.data ?? data
        rewrite.value  = null
        return current.value
    }

    return { products, current, loading, rewrite, rewriteLoading, fetchAll, fetchOne, triggerAnalysis, generateRewrite, applyRewrite }
})
