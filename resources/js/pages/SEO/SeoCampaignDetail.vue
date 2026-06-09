<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useSeoStore } from '@/stores/seo'
import { useToastStore } from '@/stores/toast'
import { useSettingsStore } from '@/stores/settings'
import type { SeoPost } from '@/stores/seo'

const route          = useRoute()
const router         = useRouter()
const workspaceStore = useWorkspaceStore()
const seoStore       = useSeoStore()
const toast          = useToastStore()

const settingsStore  = useSettingsStore()

// Track the selected post by id and derive activePost from the LIVE store array,
// so store mutations (image regenerate/upload, edits) always reflect in the UI.
// Holding a detached object reference here would miss those updates.
const activePostId   = ref<number | null>(null)
const copying        = ref<number | null>(null)
const publishing     = ref<number | null>(null)
const lightboxUrl    = ref<string | null>(null)

// Content editing
const editing        = ref(false)
const savingEdit     = ref(false)
const editForm       = ref<{ title: string; caption: string; hashtags: string }>({ title: '', caption: '', hashtags: '' })

// Image editing
const imageInputRef  = ref<HTMLInputElement | null>(null)
const uploadingImage = ref(false)
const regenerating   = ref(false)
const showImageTools = ref(false)
const refPrompt      = ref('')

const STATUS_COLORS: Record<string, string> = {
    draft:     'bg-gray-100 text-gray-600',
    approved:  'bg-green-100 text-green-700',
    rejected:  'bg-red-100 text-red-600',
    published: 'bg-indigo-100 text-indigo-700',
    failed:    'bg-red-100 text-red-700',
}

let pollTimer: ReturnType<typeof setInterval> | null = null

function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

async function loadCampaign(wsId: string, id: string) {
    await seoStore.fetchCampaign(wsId, id)
    const c = seoStore.current
    if (c?.posts?.length && activePostId.value === null) {
        activePostId.value = c.posts[0].id
    }
    // Stop polling once generation has finished (or failed).
    if (c && c.status !== 'pending' && c.status !== 'generating') {
        stopPolling()
    }
}

onMounted(async () => {
    // Workspace may not be loaded yet on a hard refresh — await it.
    const ws   = await workspaceStore.ensureLoaded()
    const wsId = ws?.id
    const id   = route.params.id as string  // UUID string — do NOT convert to Number()
    if (!wsId || !id) return

    await loadCampaign(wsId, id)

    // If the campaign is still being generated (the usual case right after DO SEO
    // redirects here), poll until NVIDIA finishes so the posts appear without a
    // manual refresh.
    const st = seoStore.current?.status
    if (st === 'pending' || st === 'generating') {
        pollTimer = setInterval(() => loadCampaign(wsId, id), 3000)
    }
})

onUnmounted(stopPolling)

const campaign = computed(() => seoStore.current)
const allApproved = computed(() =>
    campaign.value?.posts?.every(p => p.status === 'approved' || p.status === 'rejected') ?? false
)

// Always resolved from the live store array → reflects all mutations.
const activePost = computed(() =>
    campaign.value?.posts?.find(p => p.id === activePostId.value) ?? null
)

function selectPost(post: SeoPost) {
    activePostId.value = post.id
    editing.value = false
    showImageTools.value = false
    refPrompt.value = ''
}

function startEdit(post: SeoPost) {
    editForm.value = {
        title:    post.title ?? '',
        caption:  post.edited_caption ?? post.caption ?? '',
        hashtags: post.hashtags ?? '',
    }
    editing.value = true
}

function cancelEdit() {
    editing.value = false
}

async function saveEdit(thenApprove = false) {
    if (!activePost.value) return
    savingEdit.value = true
    try {
        await seoStore.updatePost(activePost.value.id, {
            title:    editForm.value.title || null,
            caption:  editForm.value.caption || null,
            hashtags: editForm.value.hashtags || null,
        })
        if (thenApprove) {
            await seoStore.approvePost(activePost.value.id, editForm.value.caption || undefined)
            toast.success('Saved & approved!')
        } else {
            toast.success('Changes saved')
        }
        editing.value = false
    } catch {
        toast.error('Failed to save changes')
    } finally {
        savingEdit.value = false
    }
}

// ─── Image editing ───────────────────────────────────────────────────────
function triggerUpload() {
    imageInputRef.value?.click()
}

async function onImageSelected(e: Event) {
    if (!activePost.value) return
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    uploadingImage.value = true
    try {
        await seoStore.uploadPostImage(activePost.value.id, file)
        toast.success('Image uploaded')
    } catch {
        toast.error('Upload failed. Max 5MB, JPG/PNG/WebP.')
    } finally {
        uploadingImage.value = false
        if (imageInputRef.value) imageInputRef.value.value = ''
    }
}

async function regenerateImage() {
    if (!activePost.value) return
    regenerating.value = true
    try {
        await seoStore.regeneratePostImage(activePost.value.id, refPrompt.value || undefined)
        toast.success('New image generated by NVIDIA FLUX')
        showImageTools.value = false
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Image generation failed. Try again.')
    } finally {
        regenerating.value = false
    }
}

async function quickApprove(post: SeoPost) {
    try {
        await seoStore.approvePost(post.id)
        toast.success(`${seoStore.PLATFORM_LABELS[post.platform]} post approved!`)
    } catch {
        toast.error('Failed to approve')
    }
}

async function rejectPost(post: SeoPost) {
    try {
        await seoStore.rejectPost(post.id)
        toast.info('Post rejected')
    } catch {
        toast.error('Failed to reject')
    }
}

async function publishPost(post: SeoPost) {
    publishing.value = post.id
    try {
        await settingsStore.publishPost(post.id)
        toast.success(`Publishing to ${seoStore.PLATFORM_LABELS[post.platform]}… check back in a moment.`)
        // Refresh campaign after short delay
        setTimeout(async () => {
            const wsId = workspaceStore.current?.id
            if (wsId && campaign.value) await seoStore.fetchCampaign(wsId, campaign.value.id)
        }, 3000)
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Publish failed. Check Settings → Social Accounts.')
    } finally {
        publishing.value = null
    }
}

async function copyToClipboard(post: SeoPost) {
    const text = [
        post.title,
        post.edited_caption ?? post.caption,
        post.hashtags,
    ].filter(Boolean).join('\n\n')

    await navigator.clipboard.writeText(text)
    copying.value = post.id
    toast.success('Copied to clipboard!')
    setTimeout(() => { copying.value = null }, 2000)
}

const PLATFORM_ICONS: Record<string, string> = {
    instagram: '📸', facebook: '📘', linkedin: '💼', google_business: '🔍',
}
const PLATFORM_GUIDE: Record<string, string> = {
    instagram:       'Copy caption + hashtags → paste in Instagram app',
    facebook:        'Copy post text → paste in Facebook Create Post',
    linkedin:        'Copy post → paste in LinkedIn Share box',
    google_business: 'Copy post → Add Update in Google Business Profile',
}
</script>
<style>
.truncate{ white-space: break-spaces !important; }
</style>
<template>
    <div class="p-6">
        <!-- Header — flex with min-w-0 to prevent title overflow causing horizontal scroll -->
        <div class="flex items-center gap-3 mb-6 min-w-0 overflow-hidden">
            <RouterLink to="/seo" class="text-sm text-indigo-600 hover:underline flex-shrink-0">← SEO Campaigns</RouterLink>
            <span class="text-gray-300 flex-shrink-0">|</span>
            <div v-if="campaign" class="min-w-0 flex-1">
                <h1 class="text-xl font-bold text-gray-900 truncate" :title="campaign.product?.title ?? ''">
                    {{ campaign.product?.title }}
                </h1>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                    {{ campaign.product?.asin }} · Generated by {{ campaign.ai_provider ?? 'NVIDIA' }}
                </p>
            </div>
        </div>

        <div v-if="!campaign" class="text-gray-400 text-sm text-center py-10">Loading…</div>

        <!-- Generating / pending state -->
        <div v-else-if="campaign.status === 'generating' || campaign.status === 'pending'"
            class="bg-white rounded-xl border border-gray-200 p-10 text-center">
            <div class="text-5xl mb-4">⚡</div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">NVIDIA is generating your content</h2>
            <p class="text-sm text-gray-500 mb-2">Researching trends → Writing 4 platform posts → Creating image prompts</p>
            <p class="text-xs text-gray-400">Usually takes 30–60 seconds. Page will update automatically.</p>
            <div class="mt-4 flex justify-center gap-2">
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:0ms" />
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:150ms" />
                <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:300ms" />
            </div>
        </div>

        <!-- Failed state -->
        <div v-else-if="campaign.status === 'failed'"
            class="bg-red-50 rounded-xl border border-red-200 p-6 text-center">
            <p class="text-red-700 font-medium mb-2">Content generation failed</p>
            <p class="text-sm text-red-600 mb-4">Check that NVIDIA_API_KEY is configured in .env</p>
        </div>

        <!-- Posts review UI -->
        <template v-else-if="campaign.posts?.length">

            <!-- Trend insight bar -->
            <div v-if="campaign.trend_data"
                class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-4 mb-6">
                <p class="text-xs font-semibold text-purple-700 mb-1">🔥 Trend Research by NVIDIA</p>
                <p class="text-sm text-purple-800">
                    <strong>Angle:</strong> {{ campaign.trend_data.content_angle }}
                    · <strong>Context:</strong> {{ campaign.trend_data.seasonal_context }}
                </p>
                <div v-if="campaign.trend_data.trending_topics?.length" class="flex flex-wrap gap-1 mt-2">
                    <span v-for="t in (campaign.trend_data.trending_topics as string[])" :key="t"
                        class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full text-xs">
                        {{ t }}
                    </span>
                </div>
            </div>

            <!-- Bulk approve all -->
            <div v-if="!allApproved" class="flex justify-end gap-2 mb-4">
                <button
                    @click="campaign.posts?.filter(p => p.status === 'draft').forEach(p => quickApprove(p))"
                    class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    ✓ Approve All
                </button>
            </div>

            <!-- All approved callout -->
            <div v-if="allApproved"
                class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
                <span class="text-2xl">🎉</span>
                <div>
                    <p class="text-sm font-semibold text-green-800">All posts approved!</p>
                    <p class="text-xs text-green-700">Copy each post and share on your social media accounts.</p>
                </div>
            </div>

            <div class="flex gap-6">
                <!-- Platform sidebar -->
                <div class="w-48 flex-shrink-0 space-y-2">
                    <button v-for="post in campaign.posts" :key="post.id"
                        @click="selectPost(post)"
                        :class="['w-full text-left px-3 py-3 rounded-xl border-2 transition-colors',
                            activePost?.id === post.id
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 bg-white hover:border-gray-300']">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">{{ PLATFORM_ICONS[post.platform] }}</span>
                                <span class="text-xs font-medium text-gray-700">
                                    {{ seoStore.PLATFORM_LABELS[post.platform] }}
                                </span>
                            </div>
                            <span :class="['w-2 h-2 rounded-full', {
                                'bg-green-500': post.status === 'approved',
                                'bg-red-400':   post.status === 'rejected',
                                'bg-gray-300':  post.status === 'draft',
                            }]" />
                        </div>
                        <span :class="['text-xs mt-1 block', STATUS_COLORS[post.status]]">
                            {{ post.status === 'draft' ? 'Needs review' : post.status }}
                        </span>
                    </button>
                </div>

                <!-- Post content panel -->
                <div class="flex-1 bg-white rounded-xl border border-gray-200 overflow-hidden" v-if="activePost">
                    <!-- Panel header -->
                    <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-indigo-50/60 to-white">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xl">{{ PLATFORM_ICONS[activePost.platform] }}</span>
                            <h3 class="font-semibold text-gray-900 truncate">{{ seoStore.PLATFORM_LABELS[activePost.platform] }}</h3>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[activePost.status]]">
                                {{ activePost.status }}
                            </span>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button @click="copyToClipboard(activePost)"
                                class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                {{ copying === activePost.id ? '✓ Copied!' : '📋 Copy' }}
                            </button>
                            <button v-if="!editing"
                                @click="startEdit(activePost)"
                                class="px-3 py-1.5 text-xs border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50 transition-colors">
                                ✏️ Edit
                            </button>
                            <button v-if="activePost.status === 'draft' && !editing"
                                @click="quickApprove(activePost)"
                                class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                ✓ Approve
                            </button>
                            <button v-if="activePost.status === 'draft' && !editing"
                                @click="rejectPost(activePost)"
                                class="px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                ✗ Reject
                            </button>
                            <button v-if="activePost.status === 'approved'"
                                @click="publishPost(activePost)" :disabled="publishing === activePost.id"
                                class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors">
                                {{ publishing === activePost.id ? '⏳ Publishing…' : '🚀 Publish' }}
                            </button>
                            <span v-else-if="activePost.status === 'published'"
                                class="px-3 py-1.5 text-xs bg-green-100 text-green-700 rounded-lg">✓ Published</span>
                        </div>
                    </div>

                    <div class="p-5">
                        <!-- How to post guide -->
                        <div class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2 mb-5">
                            📱 {{ PLATFORM_GUIDE[activePost.platform] }}
                        </div>

                        <!-- ─── IMAGE SECTION (always editable) ─────────────────── -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Post Image</p>
                                <div class="flex gap-2">
                                    <button @click="showImageTools = !showImageTools"
                                        class="text-xs text-indigo-600 hover:underline">
                                        {{ showImageTools ? 'Close' : '✨ AI Image' }}
                                    </button>
                                    <button @click="triggerUpload" :disabled="uploadingImage"
                                        class="text-xs text-indigo-600 hover:underline disabled:opacity-50">
                                        {{ uploadingImage ? 'Uploading…' : '⬆ Upload' }}
                                    </button>
                                    <a v-if="activePost.image_url" :href="activePost.image_url" download
                                        class="text-xs text-indigo-600 hover:underline">⬇ Download</a>
                                </div>
                            </div>
                            <input ref="imageInputRef" type="file" class="hidden"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" @change="onImageSelected" />

                            <!-- Image preview / placeholder -->
                            <div class="relative inline-block">
                                <img v-if="activePost.image_url" :src="activePost.image_url" alt="Post image"
                                    @click="lightboxUrl = activePost.image_url"
                                    class="w-64 h-64 object-cover rounded-xl border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity" />
                                <div v-else
                                    class="w-64 h-64 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 flex flex-col items-center justify-center text-gray-400">
                                    <span class="text-3xl mb-1">🖼</span>
                                    <span class="text-xs">No image yet</span>
                                </div>
                                <!-- Loading overlay while regenerating/uploading -->
                                <div v-if="regenerating || uploadingImage"
                                    class="absolute inset-0 bg-white/70 rounded-xl flex flex-col items-center justify-center">
                                    <svg class="animate-spin w-7 h-7 text-indigo-600 mb-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span class="text-xs text-indigo-700 font-medium">{{ regenerating ? 'Generating…' : 'Uploading…' }}</span>
                                </div>
                            </div>

                            <!-- AI image tools (collapsible) -->
                            <div v-if="showImageTools" class="mt-3 bg-purple-50 border border-purple-200 rounded-xl p-4">
                                <label class="block text-xs font-medium text-purple-800 mb-1">
                                    Describe the image you want <span class="text-purple-400">(optional — leave blank to reuse the auto prompt)</span>
                                </label>
                                <textarea v-model="refPrompt" rows="3"
                                    placeholder="e.g. The product on a marble kitchen counter, warm morning light, lifestyle shot, no text"
                                    class="w-full border border-purple-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 resize-y bg-white" />
                                <div class="flex items-center gap-2 mt-2">
                                    <button @click="regenerateImage" :disabled="regenerating"
                                        class="px-4 py-1.5 text-xs bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 disabled:opacity-50 transition-all">
                                        {{ regenerating ? '⏳ Generating…' : '✨ Generate with NVIDIA FLUX' }}
                                    </button>
                                    <span class="text-xs text-purple-500">Free · ~10–15s</span>
                                </div>
                            </div>
                        </div>

                        <!-- ─── EDIT MODE: title, caption, hashtags ─────────────── -->
                        <div v-if="editing" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                                <input v-model="editForm.title" type="text"
                                    placeholder="Optional post title / headline"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Caption / Description</label>
                                <textarea v-model="editForm.caption" rows="7"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Hashtags <span class="text-gray-400">(space-separated)</span></label>
                                <input v-model="editForm.hashtags" type="text"
                                    placeholder="#tag1 #tag2 #tag3"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="flex gap-2 pt-1">
                                <button @click="cancelEdit" :disabled="savingEdit"
                                    class="px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">Cancel</button>
                                <button @click="saveEdit(false)" :disabled="savingEdit"
                                    class="px-4 py-1.5 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    {{ savingEdit ? 'Saving…' : '💾 Save' }}
                                </button>
                                <button v-if="activePost.status === 'draft'" @click="saveEdit(true)" :disabled="savingEdit"
                                    class="px-4 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                                    ✓ Save &amp; Approve
                                </button>
                            </div>
                        </div>

                        <!-- ─── VIEW MODE ───────────────────────────────────────── -->
                        <template v-else>
                            <div v-if="activePost.title" class="mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-1">Title</p>
                                <p class="text-sm font-semibold text-gray-900">{{ activePost.title }}</p>
                            </div>

                            <div class="mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-1">Caption</p>
                                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">
                                    {{ activePost.edited_caption ?? activePost.caption }}
                                </div>
                            </div>

                            <div v-if="activePost.hashtags" class="mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-1">Hashtags</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="tag in activePost.hashtags.split(' ').filter(Boolean)" :key="tag"
                                        class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs font-mono">
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- Image lightbox -->
        <Teleport to="body">
            <div v-if="lightboxUrl" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
                @click="lightboxUrl = null">
                <img :src="lightboxUrl" alt="Generated post image"
                    class="max-w-full max-h-full rounded-xl object-contain shadow-2xl" @click.stop />
                <button @click="lightboxUrl = null"
                    class="absolute top-4 right-4 w-10 h-10 bg-white/10 text-white rounded-full flex items-center justify-center hover:bg-white/20 transition-colors text-lg">✕</button>
            </div>
        </Teleport>
    </div>
</template>
