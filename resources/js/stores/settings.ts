import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export interface SocialAccountStatus {
    platform: string
    id?: number
    account_name?: string
    account_id?: string
    is_connected: boolean
    is_active?: boolean
    has_token?: boolean
    token_expires_at?: string | null
    meta?: Record<string, string>
}

export interface AiKeysStatus {
    nvidia_api_key: string | null
    nvidia_model: string
    groq_api_key: string | null
    groq_model: string
    anthropic_api_key: string | null
    openai_api_key: string | null
    ai_default_provider: string
    active_provider: string | null
}

export const useSettingsStore = defineStore('settings', () => {
    const socialAccounts = ref<Record<string, SocialAccountStatus>>({})
    const aiKeys         = ref<AiKeysStatus | null>(null)
    const notifications  = ref<Record<string, string> | null>(null)
    const loading        = ref(false)
    const testing        = ref<string | null>(null)
    const saving         = ref(false)

    async function fetchSocialAccounts(workspaceId: number): Promise<void> {
        const { data } = await api.get(`/workspaces/${workspaceId}/settings/social-accounts`)
        socialAccounts.value = data.data ?? data
    }

    async function saveSocialAccount(workspaceId: number, platform: string, payload: {
        account_name?: string
        account_id?: string
        access_token?: string
        meta?: Record<string, string>
    }): Promise<SocialAccountStatus> {
        saving.value = true
        try {
            const { data } = await api.put(`/workspaces/${workspaceId}/settings/social-accounts/${platform}`, payload)
            const updated = data.data ?? data
            socialAccounts.value[platform] = { ...socialAccounts.value[platform], ...updated }
            return updated
        } finally {
            saving.value = false
        }
    }

    async function testConnection(workspaceId: number, platform: string): Promise<{ is_connected: boolean; message: string }> {
        testing.value = platform
        try {
            const { data } = await api.post(`/workspaces/${workspaceId}/settings/social-accounts/${platform}/test`)
            const result = data.data ?? data
            if (socialAccounts.value[platform]) {
                socialAccounts.value[platform].is_connected = result.is_connected
            }
            return result
        } finally {
            testing.value = null
        }
    }

    async function disconnectAccount(workspaceId: number, platform: string): Promise<void> {
        await api.delete(`/workspaces/${workspaceId}/settings/social-accounts/${platform}`)
        if (socialAccounts.value[platform]) {
            socialAccounts.value[platform].is_connected = false
        }
    }

    async function fetchAiKeys(workspaceId: number): Promise<void> {
        const { data } = await api.get(`/workspaces/${workspaceId}/settings/ai-keys`)
        aiKeys.value = data.data ?? data
    }

    async function saveAiKeys(workspaceId: number, keys: Partial<AiKeysStatus>): Promise<void> {
        saving.value = true
        try {
            await api.put(`/workspaces/${workspaceId}/settings/ai-keys`, keys)
            await fetchAiKeys(workspaceId)
        } finally {
            saving.value = false
        }
    }

    async function fetchNotifications(workspaceId: number): Promise<void> {
        const { data } = await api.get(`/workspaces/${workspaceId}/settings/notifications`)
        notifications.value = data.data ?? data
    }

    async function regenerateToken(workspaceId: number): Promise<string> {
        const { data } = await api.post(`/workspaces/${workspaceId}/settings/notifications/regenerate-token`)
        const result = data.data ?? data
        if (notifications.value) notifications.value.seo_webhook_token = result.seo_webhook_token
        return result.seo_webhook_token
    }

    async function publishPost(postId: number): Promise<void> {
        await api.post(`/api/v1/seo/posts/${postId}/publish`)
    }

    return {
        socialAccounts, aiKeys, notifications, loading, testing, saving,
        fetchSocialAccounts, saveSocialAccount, testConnection, disconnectAccount,
        fetchAiKeys, saveAiKeys, fetchNotifications, regenerateToken, publishPost,
    }
})
