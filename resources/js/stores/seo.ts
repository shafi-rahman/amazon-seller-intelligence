import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export interface SeoPost {
    id: number
    platform: 'instagram' | 'facebook' | 'linkedin' | 'google_business'
    title: string | null
    caption: string | null
    edited_caption: string | null
    hashtags: string | null
    image_prompt: string | null
    image_url: string | null
    image_path: string | null
    previous_image_url: string | null
    previous_image_path: string | null
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

    function patchPost(postId: number, changes: Partial<SeoPost>): void {
        const post = current.value?.posts?.find(p => p.id === postId)
        if (post) Object.assign(post, changes)
    }

    async function approvePost(postId: number, editedCaption?: string): Promise<void> {
        await api.post(`/seo/posts/${postId}/approve`, { edited_caption: editedCaption })
        const post = current.value?.posts?.find(p => p.id === postId)
        if (post) {
            post.status = 'approved'
            if (editedCaption) post.edited_caption = editedCaption
            if (current.value) current.value.approved_count++
        }
    }

    async function rejectPost(postId: number): Promise<void> {
        await api.post(`/seo/posts/${postId}/reject`)
        patchPost(postId, { status: 'rejected' })
    }

    // Edit any content field (title / caption / hashtags)
    async function updatePost(
        postId: number,
        payload: { title?: string | null; caption?: string | null; hashtags?: string | null },
    ): Promise<void> {
        const { data } = await api.put(`/seo/posts/${postId}`, payload)
        const d = data.data ?? data
        patchPost(postId, {
            title: d.title,
            edited_caption: d.edited_caption,
            hashtags: d.hashtags,
        })
    }

    // Common patch for all image-mutating responses
    function patchImage(postId: number, d: any): void {
        patchPost(postId, {
            image_url: d.image_url,
            image_path: d.image_path,
            image_prompt: d.image_prompt,
            previous_image_url: d.previous_image_url,
            previous_image_path: d.previous_image_path,
        })
    }

    // Upload an image from the user's computer
    async function uploadPostImage(postId: number, file: File): Promise<void> {
        const form = new FormData()
        form.append('image', file)
        const { data } = await api.post(`/seo/posts/${postId}/image/upload`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        patchImage(postId, data.data ?? data)
    }

    // (Re)generate the AI image, optionally with a custom reference prompt
    async function regeneratePostImage(postId: number, prompt?: string): Promise<void> {
        const { data } = await api.post(`/seo/posts/${postId}/image/generate`, { prompt })
        patchImage(postId, data.data ?? data)
    }

    // Reuse the image from a sibling post in the same campaign
    async function copyPostImage(postId: number, sourcePostId: number): Promise<void> {
        const { data } = await api.post(`/seo/posts/${postId}/image/copy`, { source_post_id: sourcePostId })
        patchImage(postId, data.data ?? data)
    }

    // Restore the image the post had before the last change (toggles back/forth)
    async function revertPostImage(postId: number): Promise<void> {
        const { data } = await api.post(`/seo/posts/${postId}/image/revert`)
        patchImage(postId, data.data ?? data)
    }

    // Upload a reference image + optional prompt → AI describes it and regenerates
    async function regenerateFromReference(postId: number, file: File, prompt?: string): Promise<void> {
        const form = new FormData()
        form.append('reference', file)
        if (prompt) form.append('prompt', prompt)
        const { data } = await api.post(`/seo/posts/${postId}/image/from-reference`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        patchImage(postId, data.data ?? data)
    }

    return {
        campaigns, current, loading, tagging,
        PLATFORM_LABELS, PLATFORM_COLORS,
        fetchCampaigns, fetchCampaign, tagProduct, approvePost, rejectPost,
        updatePost, uploadPostImage, regeneratePostImage, copyPostImage, revertPostImage,
        regenerateFromReference,
    }
})
