import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export interface Message {
    id: number
    role: 'user' | 'assistant'
    content: string
    provider?: string
    model?: string
    rag_sources?: Array<{ type: string; id: number; similarity: number; excerpt: string }>
    prompt_tokens?: number
    completion_tokens?: number
    created_at?: string
}

export interface Conversation {
    id: number
    title: string | null
    context_type: string
    context_id: number | null
    created_at: string
    updated_at: string
    messages?: Message[]
}

export const useCopilotStore = defineStore('copilot', () => {
    const conversations    = ref<Conversation[]>([])
    const current          = ref<Conversation | null>(null)
    const messages         = ref<Message[]>([])
    const loading          = ref(false)
    const sending          = ref(false)
    const aiStatus         = ref<{ ai_configured: boolean; active_provider: string | null; embeddings_available: boolean } | null>(null)

    async function fetchStatus(workspaceId: number): Promise<void> {
        const { data } = await api.get(`/workspaces/${workspaceId}/ai/status`)
        aiStatus.value = data.data ?? data
    }

    async function fetchConversations(workspaceId: number): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/ai/conversations`)
            conversations.value = data.data ?? data
        } finally {
            loading.value = false
        }
    }

    async function createConversation(workspaceId: number, payload: {
        context_type?: string
        context_id?: number | null
        title?: string
    }): Promise<Conversation> {
        const { data } = await api.post(`/workspaces/${workspaceId}/ai/conversations`, payload)
        const conv      = data.data ?? data
        conversations.value.unshift(conv)
        return conv
    }

    async function openConversation(workspaceId: number, id: number): Promise<void> {
        loading.value = true
        try {
            const { data } = await api.get(`/workspaces/${workspaceId}/ai/conversations/${id}`)
            const conv      = data.data ?? data
            current.value   = conv
            messages.value  = conv.messages ?? []
        } finally {
            loading.value = false
        }
    }

    async function sendMessage(workspaceId: number, conversationId: number, message: string): Promise<Message> {
        // Optimistically add user message
        const userMsg: Message = { id: Date.now(), role: 'user', content: message, created_at: new Date().toISOString() }
        messages.value.push(userMsg)
        sending.value = true

        try {
            const { data } = await api.post(
                `/workspaces/${workspaceId}/ai/conversations/${conversationId}/messages`,
                { message }
            )
            const assistantMsg: Message = data.data ?? data
            messages.value.push(assistantMsg)

            // Update conversation title if it was auto-set
            await fetchConversations(workspaceId)
            return assistantMsg
        } finally {
            sending.value = false
        }
    }

    async function deleteConversation(workspaceId: number, id: number): Promise<void> {
        await api.delete(`/workspaces/${workspaceId}/ai/conversations/${id}`)
        conversations.value = conversations.value.filter(c => c.id !== id)
        if (current.value?.id === id) {
            current.value  = null
            messages.value = []
        }
    }

    return {
        conversations, current, messages, loading, sending, aiStatus,
        fetchStatus, fetchConversations, createConversation, openConversation, sendMessage, deleteConversation,
    }
})
