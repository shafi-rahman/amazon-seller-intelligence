import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'
import type { Workspace } from '@/types'

export const useWorkspaceStore = defineStore('workspace', () => {
    const workspaces = ref<Workspace[]>([])
    const current = ref<Workspace | null>(null)
    const loading = ref(false)

    // Shared in-flight promise so concurrent callers (layout + a detail page
    // mounting on hard refresh) don't trigger duplicate /workspaces requests.
    let loadPromise: Promise<void> | null = null

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

    // Guarantees the workspace list (and `current`) is loaded before use.
    // Safe to call from any page's onMounted — on a hard refresh the layout's
    // fire-and-forget fetchAll() may not have resolved yet, so pages that need
    // `current.id` immediately should await this instead of reading current directly.
    async function ensureLoaded(): Promise<Workspace | null> {
        if (current.value) return current.value
        if (!loadPromise) {
            loadPromise = fetchAll().finally(() => { loadPromise = null })
        }
        await loadPromise
        return current.value
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

    return { workspaces, current, loading, fetchAll, ensureLoaded, create, setCurrent }
})
