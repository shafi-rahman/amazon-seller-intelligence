<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useProductsStore } from '@/stores/products'
import { useSeoStore } from '@/stores/seo'
import { useToastStore } from '@/stores/toast'

const route         = useRoute()
const router        = useRouter()
const workspaceStore = useWorkspaceStore()
const productsStore  = useProductsStore()
const seoStore       = useSeoStore()
const toast          = useToastStore()

const activeTab    = ref<'overview' | 'score' | 'optimization' | 'keywords' | 'image'>('overview')
const analyzing    = ref(false)
const applyingRewrite = ref(false)
const editedRewrite = ref<Record<string, string>>({})

// Image gallery
const imageUploading  = ref(false)
const deletingImageId = ref<string | null>(null)
const settingPrimary  = ref<string | null>(null)
const fileInputRef    = ref<HTMLInputElement | null>(null)
const lightboxUrl     = ref<string | null>(null)
import api from '@/api/axios'

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    const id   = route.params.id as string  // UUID
    if (!wsId || !id) return
    await productsStore.fetchOne(wsId, id)
})

const product = computed(() => productsStore.current)
const score   = computed(() => product.value?.score_breakdown ?? null)
const suggestions = computed(() => product.value?.ai_suggestions ?? null)

async function doSeo() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    try {
        const campaign = await seoStore.tagProduct(wsId, product.value.id)
        toast.success('SEO campaign started! NVIDIA is generating your posts…')
        router.push(`/seo/campaigns/${campaign.id}`)
    } catch {
        toast.error('Failed to start SEO campaign')
    }
}

// Gallery: computed images list
const galleryImages = computed(() => product.value?.images ?? [])
const primaryImageUrl = computed(() =>
    galleryImages.value.find((i: any) => i.is_primary)?.url ??
    galleryImages.value[0]?.url ?? null
)

async function uploadImages(event: Event) {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    const files = (event.target as HTMLInputElement).files
    if (!files || files.length === 0) return

    imageUploading.value = true
    const form = new FormData()
    Array.from(files).forEach(f => form.append('images[]', f))

    try {
        await api.post(
            `/workspaces/${wsId}/products/${product.value.id}/images`,
            form,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        // Reload product to get fresh images list
        await productsStore.fetchOne(wsId, product.value.id)
        toast.success(`${files.length} image${files.length > 1 ? 's' : ''} uploaded!`)
    } catch {
        toast.error('Upload failed. Max 5MB each, JPG/PNG/WebP only.')
    } finally {
        imageUploading.value = false
        if (fileInputRef.value) fileInputRef.value.value = ''
    }
}

async function deleteImage(imageId: string) {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    deletingImageId.value = imageId
    try {
        await api.delete(`/workspaces/${wsId}/products/${product.value.id}/images/${imageId}`)
        if (productsStore.current?.images) {
            productsStore.current.images = productsStore.current.images.filter((i: any) => i.id !== imageId)
        }
        toast.success('Image removed')
    } catch {
        toast.error('Failed to remove image')
    } finally {
        deletingImageId.value = null
    }
}

async function setPrimary(imageId: string) {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    settingPrimary.value = imageId
    try {
        await api.put(`/workspaces/${wsId}/products/${product.value.id}/images/${imageId}/primary`)
        if (productsStore.current?.images) {
            productsStore.current.images.forEach((i: any) => { i.is_primary = i.id === imageId })
        }
        toast.success('Primary image updated')
    } catch {
        toast.error('Failed to set primary image')
    } finally {
        settingPrimary.value = null
    }
}

async function runAnalysis() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    analyzing.value = true
    try {
        await productsStore.triggerAnalysis(wsId, product.value.id)
        // Poll for result after 5s
        setTimeout(async () => {
            await productsStore.fetchOne(wsId, product.value.id)
            analyzing.value = false
        }, 5000)
    } catch {
        analyzing.value = false
    }
}

async function generateRewrite() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    await productsStore.generateRewrite(wsId, product.value.id)
    if (productsStore.rewrite) {
        editedRewrite.value = { ...productsStore.rewrite }
    }
}

async function applyRewrite() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !product.value) return
    applyingRewrite.value = true
    try {
        await productsStore.applyRewrite(wsId, product.value.id, editedRewrite.value)
        activeTab.value = 'overview'
    } finally {
        applyingRewrite.value = false
    }
}

const DIMENSION_LABELS: Record<string, string> = {
    title: 'Title', bullets: 'Bullet Points', description: 'Description',
    reviews: 'Reviews & Ratings', keywords: 'Keyword Coverage',
}

function barColor(score: number, max: number) {
    const pct = score / max
    if (pct >= 0.85) return 'bg-green-500'
    if (pct >= 0.70) return 'bg-blue-500'
    if (pct >= 0.50) return 'bg-yellow-500'
    return 'bg-red-500'
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-start gap-4 mb-6 min-w-0">
            <RouterLink to="/products" class="text-sm text-indigo-600 hover:underline flex-shrink-0 mt-1">← Products</RouterLink>
            <span class="text-gray-300 flex-shrink-0 mt-1">|</span>
            <!-- Product image thumbnail in header -->
            <div v-if="product && primaryImageUrl" class="flex-shrink-0 mt-0.5">
                <img :src="primaryImageUrl" alt="" @click="activeTab = 'image'"
                    title="View product images"
                    class="w-10 h-10 rounded-lg object-cover border border-gray-200 cursor-pointer hover:ring-2 hover:ring-indigo-400 transition-all" />
            </div>
            <div v-else-if="product" class="flex-shrink-0 mt-0.5">
                <button @click="activeTab = 'image'"
                    class="w-10 h-10 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-gray-400 hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                    title="Upload product image">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
            <h1 class="text-xl font-bold text-gray-900 leading-snug break-words min-w-0" v-if="product">
                {{ product.title ?? product.asin }}
            </h1>
        </div>

        <div v-if="!product" class="text-gray-400 text-sm py-8 text-center">Loading…</div>
        <div v-else>
            <!-- Header -->
            <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4 flex items-start justify-between">
                <div class="flex-1 min-w-0 flex gap-4">
                    <!-- Product image (clickable → Images tab) -->
                    <button @click="activeTab = 'image'" class="flex-shrink-0 group" title="View / manage product images">
                        <div v-if="primaryImageUrl"
                            class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 group-hover:ring-2 group-hover:ring-indigo-400 transition-all">
                            <img :src="primaryImageUrl" alt="Product image" class="w-full h-full object-cover" />
                        </div>
                        <div v-else
                            class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex flex-col items-center justify-center text-gray-400 group-hover:border-indigo-400 group-hover:bg-indigo-50 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-[10px] mt-0.5">Add image</span>
                        </div>
                    </button>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ product.asin }}</span>
                            <span v-if="product.sku" class="text-xs text-gray-400">SKU: {{ product.sku }}</span>
                            <span v-if="product.brand" class="text-xs text-gray-500">{{ product.brand }}</span>
                        </div>
                        <h2 class="text-sm text-gray-700 leading-snug mb-2 line-clamp-2" :title="product.title">{{ product.title }}</h2>
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <span v-if="product.price">₹{{ product.price?.toLocaleString('en-IN') }}</span>
                            <span v-if="product.rating">{{ product.rating }}★ ({{ product.review_count }} reviews)</span>
                            <span v-if="product.category">{{ product.category }}</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4 text-right flex-shrink-0 space-y-2">
                    <!-- DO SEO button -->
                    <button @click="doSeo" :disabled="seoStore.tagging"
                        class="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold rounded-lg hover:from-purple-700 hover:to-indigo-700 disabled:opacity-50 transition-all shadow-sm">
                        <span>🎯</span>
                        {{ seoStore.tagging ? 'Starting…' : 'DO SEO' }}
                    </button>
                    <div v-if="product.listing_score !== null" class="mb-2">
                        <div class="text-4xl font-bold" :class="product.listing_score >= 70 ? 'text-green-600' : product.listing_score >= 50 ? 'text-yellow-600' : 'text-red-600'">
                            {{ product.listing_score }}
                        </div>
                        <div class="text-xs text-gray-400">/ 100</div>
                    </div>
                    <button @click="runAnalysis" :disabled="analyzing"
                        class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        {{ analyzing ? 'Analyzing…' : 'Re-analyze' }}
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 mb-4">
                <button v-for="tab in [
                    { key: 'overview', label: 'Listing' },
                    { key: 'score', label: 'Score Breakdown' },
                    { key: 'optimization', label: 'Optimization' },
                    { key: 'keywords', label: 'Keywords' },
                    { key: 'image', label: '🖼 Images' }
                ]" :key="tab.key"
                    @click="activeTab = tab.key as any"
                    :class="['px-4 py-2.5 text-sm font-medium transition-colors border-b-2',
                        activeTab === tab.key
                            ? 'border-indigo-600 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700']">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab: Overview -->
            <div v-if="activeTab === 'overview'" class="bg-white rounded-lg border border-gray-200 p-5">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Title</h3>
                        <p class="text-sm text-gray-800">{{ product.title }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ (product.title?.length ?? 0) }} chars</p>
                    </div>
                    <div v-for="i in 5" :key="i">
                        <div v-if="product[`bullet_${i}`]">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Bullet {{ i }}</h3>
                            <p class="text-sm text-gray-800">{{ product[`bullet_${i}`] }}</p>
                        </div>
                    </div>
                    <div v-if="product.description">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Description</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line line-clamp-6" v-html="product.description?.replace(/<[^>]*>/g, ' ')"></p>
                        <p class="text-xs text-gray-400 mt-1">{{ (product.description?.length ?? 0) }} chars</p>
                    </div>
                </div>
            </div>

            <!-- Tab: Score Breakdown -->
            <div v-else-if="activeTab === 'score'">
                <div v-if="!score" class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400">
                    <p>No score analysis yet.</p>
                    <button @click="runAnalysis" class="mt-3 px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                        Run Analysis
                    </button>
                </div>
                <div v-else class="space-y-4">
                    <div v-for="(dim, key) in score.dimensions" :key="key" class="bg-white rounded-lg border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800">{{ DIMENSION_LABELS[key] ?? key }}</h3>
                            <div class="flex items-center gap-3">
                                <div class="w-32 bg-gray-100 rounded-full h-2">
                                    <div :class="['h-2 rounded-full', barColor(dim.score, dim.max)]"
                                        :style="{ width: `${(dim.score / dim.max) * 100}%` }" />
                                </div>
                                <span class="text-sm font-bold text-gray-700">{{ dim.score }}/{{ dim.max }}</span>
                            </div>
                        </div>
                        <div v-if="dim.passes?.length" class="space-y-1 mb-2">
                            <div v-for="p in dim.passes" :key="p" class="flex items-start gap-2 text-xs text-green-700">
                                <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                {{ p }}
                            </div>
                        </div>
                        <div v-if="dim.issues?.length" class="space-y-1">
                            <div v-for="issue in dim.issues" :key="issue" class="flex items-start gap-2 text-xs text-red-600">
                                <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ issue }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Optimization (AI Rewrite) -->
            <div v-else-if="activeTab === 'optimization'" class="bg-white rounded-lg border border-gray-200 p-5">
                <div v-if="!productsStore.rewrite && !productsStore.rewriteLoading">
                    <div v-if="suggestions" class="mb-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">AI Suggestions</h3>
                        <div v-for="s in suggestions.optimization_suggestions?.slice(0,5)" :key="s.field"
                            :class="['mb-3 p-3 rounded-md border', s.priority === 'high' ? 'border-red-200 bg-red-50' : 'border-yellow-200 bg-yellow-50']">
                            <div class="text-xs font-bold text-gray-700 mb-1">
                                <span :class="s.priority === 'high' ? 'text-red-600' : 'text-yellow-700'">[{{ s.priority.toUpperCase() }}]</span>
                                {{ s.field }} — {{ s.issue }}
                            </div>
                            <div class="text-xs text-gray-600">{{ s.suggestion }}</div>
                        </div>
                    </div>
                    <button @click="generateRewrite" :disabled="productsStore.rewriteLoading"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        Generate AI Rewrite
                    </button>
                    <p class="text-xs text-gray-400 mt-2">Requires ANTHROPIC_API_KEY to be configured</p>
                </div>

                <div v-else-if="productsStore.rewriteLoading" class="text-center py-8 text-gray-400 text-sm">
                    <svg class="animate-spin w-6 h-6 mx-auto mb-2 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Generating AI rewrite…
                </div>

                <div v-else-if="productsStore.rewrite">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Review and edit the AI rewrite before applying</h3>
                    <div class="space-y-3">
                        <div v-for="field in ['title', 'bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5', 'description']" :key="field">
                            <label class="block text-xs font-medium text-gray-600 mb-1 capitalize">{{ field.replace('_', ' ') }}</label>
                            <textarea v-model="editedRewrite[field]" rows="2"
                                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-y" />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-4">
                        <button @click="productsStore.rewrite = null" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                            Discard
                        </button>
                        <button @click="applyRewrite" :disabled="applyingRewrite"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 transition-colors">
                            {{ applyingRewrite ? 'Applying…' : 'Apply Rewrite & Re-score' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab: Keywords -->
            <div v-else-if="activeTab === 'keywords'" class="bg-white rounded-lg border border-gray-200 p-5">
                <div v-if="!product.top_keywords?.length" class="text-center py-8 text-gray-400 text-sm">
                    No keywords extracted yet. Run analysis to extract keywords.
                </div>
                <div v-else>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="kw in product.top_keywords" :key="kw.keyword"
                            :class="['px-2 py-1 text-xs rounded-full',
                                kw.source === 'title' ? 'bg-indigo-100 text-indigo-700' :
                                kw.source === 'bullet' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600']">
                            {{ kw.keyword }} <span class="opacity-60">({{ kw.frequency }})</span>
                        </span>
                    </div>
                    <div class="flex gap-3 mt-4 text-xs text-gray-400">
                        <span><span class="inline-block w-3 h-3 bg-indigo-100 rounded-full mr-1"></span>Title</span>
                        <span><span class="inline-block w-3 h-3 bg-green-100 rounded-full mr-1"></span>Bullets</span>
                        <span><span class="inline-block w-3 h-3 bg-gray-100 rounded-full mr-1"></span>Description</span>
                    </div>
                </div>
            </div>

            <!-- Tab: Images (multiple) -->
            <div v-else-if="activeTab === 'image'" class="bg-white rounded-lg border border-gray-200 p-6">

                <!-- Header + Upload button -->
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Product Images</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ galleryImages.length }} image{{ galleryImages.length !== 1 ? 's' : '' }} uploaded ·
                            Primary image is used in SEO campaigns · Up to 20 images, 5MB each ·
                            JPG, JPEG, PNG, WebP
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input ref="fileInputRef" type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            multiple class="hidden" @change="uploadImages" />
                        <button @click="fileInputRef?.click()" :disabled="imageUploading"
                            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            <svg v-if="!imageUploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ imageUploading ? 'Uploading…' : 'Upload Images' }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="galleryImages.length === 0"
                    class="border-2 border-dashed border-gray-300 rounded-xl p-12 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                    @click="fileInputRef?.click()">
                    <svg class="w-14 h-14 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600 mb-1">Click to upload product images</p>
                    <p class="text-xs text-gray-400">Select multiple images at once · JPG, PNG, WebP · Max 5MB each</p>
                </div>

                <!-- Image gallery grid -->
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div v-for="(img, idx) in galleryImages" :key="img.id"
                        class="relative group rounded-xl overflow-hidden border-2 transition-colors aspect-square"
                        :class="img.is_primary ? 'border-indigo-500' : 'border-gray-200'">

                        <!-- Image -->
                        <img :src="img.url" :alt="`Product image ${idx + 1}`"
                            class="w-full h-full object-cover cursor-pointer"
                            @click="lightboxUrl = img.url" />

                        <!-- Primary badge -->
                        <div v-if="img.is_primary"
                            class="absolute top-1.5 left-1.5 bg-indigo-600 text-white text-xs font-bold px-1.5 py-0.5 rounded-md">
                            PRIMARY
                        </div>

                        <!-- Hover overlay with actions -->
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
                            <!-- Set primary button -->
                            <button v-if="!img.is_primary"
                                @click.stop="setPrimary(img.id)"
                                :disabled="settingPrimary === img.id"
                                class="px-2 py-1 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 disabled:opacity-50 w-24 text-center">
                                {{ settingPrimary === img.id ? '…' : '⭐ Set Primary' }}
                            </button>
                            <!-- Delete button -->
                            <button @click.stop="deleteImage(img.id)"
                                :disabled="deletingImageId === img.id"
                                class="px-2 py-1 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 disabled:opacity-50 w-24 text-center">
                                {{ deletingImageId === img.id ? '…' : '🗑 Remove' }}
                            </button>
                        </div>
                    </div>

                    <!-- Add more slot -->
                    <div v-if="galleryImages.length < 20"
                        class="aspect-square rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                        @click="fileInputRef?.click()">
                        <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="text-xs">Add more</span>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700">
                    <strong>📸 SEO Campaigns:</strong> The <span class="font-semibold">PRIMARY</span> image is automatically used
                    for Instagram and Facebook posts. Click any image to enlarge. Hover to set primary or remove.
                </div>
            </div>

            <!-- Lightbox -->
            <Teleport to="body">
                <div v-if="lightboxUrl" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
                    @click="lightboxUrl = null">
                    <img :src="lightboxUrl" alt="Product image"
                        class="max-w-full max-h-full rounded-xl object-contain shadow-2xl"
                        @click.stop />
                    <button @click="lightboxUrl = null"
                        class="absolute top-4 right-4 w-10 h-10 bg-white/10 text-white rounded-full flex items-center justify-center hover:bg-white/20 transition-colors text-lg">
                        ✕
                    </button>
                </div>
            </Teleport>
        </div>
    </div>
</template>
