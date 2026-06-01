import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'
import type { Workspace } from '@/types'

export const useWorkspaceStore = defineStore('workspace', () => {
    const workspaces = ref<Workspace[]>([])
    const current = ref<Workspace | null>(null)
    const loading = ref(false)

    async function fetchAll(): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get('/workspaces')
            workspaces.value = data.data ?? data
            if (!current.value && workspaces.value.length > 0) {
                current.value = workspaces.value[0]
            }
        } finally {
            loading.value = false
        }
    }

    async function create(payload: { name: string; marketplace?: string; currency?: string }): Promise<Workspace> {
        const { data } = await api.post('/workspaces', payload)
        const ws = data.data ?? data
        workspaces.value.push(ws)
        return ws
    }

    function setCurrent(workspace: Workspace): void {
        current.value = workspace
    }

    return { workspaces, current, loading, fetchAll, create, setCurrent }
})
