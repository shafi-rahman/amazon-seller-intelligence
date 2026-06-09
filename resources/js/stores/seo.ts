import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export interface SeoPost {
    id: number
    platform: 'instagram' | 'facebook' | 'linkedin' | 'google_business'
    caption: string | null
    edited_caption: string | null
    hashtags: string | null
    image_prompt: string | null
    image_url: string | null
    status: 'draft' | 'approved' | 'rejected' | 'published'
    created_at: string | null
}

export interface SeoCampaign {
    id: number
    product: { id: number; asin: string; title: string; brand: string; price: number } | null
    status: 'pending' | 'generating' | 'awaiting_approval' | 'approved' | 'failed'
    trend_data: Record<string, unknown> | null
    ai_provider: string | null
    generated_at: string | null
    created_at: string
    posts_count: number
    approved_count: number
    posts?: SeoPost[]
}

export const useSeoStore = defineStore('seo', () => {
    const campaigns = ref<SeoCampaign[]>([])
    const current   = ref<SeoCampaign | null>(null)
    const loading   = ref(false)
    const tagging   = ref(false)

    const PLATFORM_LABELS: Record<string, string> = {
        instagram:       'Instagram',
        facebook:        'Facebook',
        linkedin:        'LinkedIn',
        google_business: 'Google Business',
    }

    const PLATFORM_COLORS: Record<string, string> = {
        instagram:       'bg-pink-100 text-pink-700',
        facebook:        'bg-blue-100 text-blue-700',
        linkedin:        'bg-sky-100 text-sky-700',
        google_business: 'bg-red-100 text-red-700',
    }

    async function fetchCampaigns(workspaceId: number, status?: string): Promise<void> {
        loading.value = true
        try {
            const params = status ? { status } : {}
            const { data } = await api.get(`/workspaces/${workspaceId}/seo/campaigns`, { params })
            campaigns.value = data.data ?? data
        } finally {
            loading.value = false
        }
    }

    async function fetchCampaign(workspaceId: number, id: string): Promise<SeoCampaign> {
        const { data } = await api.get(`/workspaces/${workspaceId}/seo/campaigns/${id}`)
        current.value  = data.data ?? data
        return current.value!
    }

    async function tagProduct(workspaceId: number, productId: number): Promise<SeoCampaign> {
        tagging.value = true
        try {
            const { data } = await api.post(`/workspaces/${workspaceId}/products/${productId}/seo/tag`)
            const campaign = data.data ?? data
            campaigns.value.unshift(campaign)
            return campaign
        } finally {
            tagging.value = false
        }
    }

    async function approvePost(postId: number, editedCaption?: string): Promise<void> {
        await api.post(`/api/v1/seo/posts/${postId}/approve`, { edited_caption: editedCaption })
        if (current.value?.posts) {
            const post = current.value.posts.find(p => p.id === postId)
            if (post) {
                post.status = 'approved'
                if (editedCaption) post.edited_caption = editedCaption
                current.value.approved_count++
            }
        }
    }

    async function rejectPost(postId: number): Promise<void> {
        await api.post(`/api/v1/seo/posts/${postId}/reject`)
        if (current.value?.posts) {
            const post = current.value.posts.find(p => p.id === postId)
            if (post) post.status = 'rejected'
        }
    }

    return {
        campaigns, current, loading, tagging,
        PLATFORM_LABELS, PLATFORM_COLORS,
        fetchCampaigns, fetchCampaign, tagProduct, approvePost, rejectPost,
    }
})
