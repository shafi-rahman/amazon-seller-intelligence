<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useSeoStore } from '@/stores/seo'

const router         = useRouter()
const workspaceStore = useWorkspaceStore()
const seoStore       = useSeoStore()

const statusFilter = ref('')

const STATUS_COLORS: Record<string, string> = {
    pending:           'bg-gray-100 text-gray-600',
    generating:        'bg-blue-100 text-blue-600',
    awaiting_approval: 'bg-yellow-100 text-yellow-700',
    approved:          'bg-green-100 text-green-700',
    published:         'bg-indigo-100 text-indigo-700',
    failed:            'bg-red-100 text-red-600',
}
const STATUS_LABELS: Record<string, string> = {
    pending:           'Pending',
    generating:        'Generating...',
    awaiting_approval: 'Needs Review',
    approved:          'Approved',
    published:         'Published',
    failed:            'Failed',
}

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (wsId) await seoStore.fetchCampaigns(wsId)
})

async function filter() {
    const wsId = workspaceStore.current?.id
    if (wsId) await seoStore.fetchCampaigns(wsId, statusFilter.value || undefined)
}

function openCampaign(id: string) {
    router.push(`/seo/campaigns/${id}`)
}

const PLATFORM_ICONS: Record<string, string> = {
    instagram: '📸', facebook: '📘', linkedin: '💼', google_business: '🔍',
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">SEO Agent</h1>
                <p class="text-gray-500 text-sm mt-1">AI-generated social media posts for your products</p>
            </div>
            <RouterLink to="/products"
                class="px-4 py-2 text-sm border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50 transition-colors">
                + Tag a Product
            </RouterLink>
        </div>

        <!-- Info banner -->
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <span class="text-2xl">🤖</span>
                <div>
                    <p class="text-sm font-semibold text-indigo-900">How it works</p>
                    <p class="text-xs text-indigo-700 mt-0.5">
                        Go to any product → click <strong>"DO SEO"</strong> → NVIDIA generates trend-aware posts for
                        Instagram, Facebook, LinkedIn & Google → review & approve → copy to your social apps.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex gap-2 mb-4">
            <select v-model="statusFilter" @change="filter"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="awaiting_approval">Needs Review</option>
                <option value="approved">Approved</option>
                <option value="generating">Generating</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <!-- Campaign grid -->
        <div v-if="seoStore.loading" class="py-10 text-center text-gray-400 text-sm">Loading campaigns…</div>

        <div v-else-if="seoStore.campaigns.length === 0" class="bg-white rounded-xl border border-gray-200 p-10 text-center">
            <div class="text-4xl mb-3">🎯</div>
            <h3 class="text-base font-semibold text-gray-800 mb-1">No SEO campaigns yet</h3>
            <p class="text-sm text-gray-500 mb-4">Go to any product and click "DO SEO" to start generating content</p>
            <RouterLink to="/products" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                Browse Products
            </RouterLink>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="c in seoStore.campaigns" :key="c.id"
                @click="openCampaign(c.id)"
                class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300 hover:shadow-sm transition-all cursor-pointer">

                <!-- Product info -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-400 font-mono mb-0.5">{{ c.product?.asin }}</p>
                        <p class="text-sm font-semibold text-gray-800 leading-snug line-clamp-2">
                            {{ c.product?.title }}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ c.product?.brand }} · ₹{{ c.product?.price?.toLocaleString('en-IN') }}</p>
                    </div>
                    <span :class="['ml-2 flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[c.status]]">
                        {{ STATUS_LABELS[c.status] }}
                    </span>
                </div>

                <!-- Platform chips -->
                <div class="flex gap-1 mb-3">
                    <span v-for="p in ['instagram','facebook','linkedin','google_business']" :key="p"
                        class="text-base" :title="seoStore.PLATFORM_LABELS[p]">
                        {{ PLATFORM_ICONS[p] }}
                    </span>
                </div>

                <!-- Progress / stats -->
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span v-if="c.status === 'generating'" class="text-blue-600 animate-pulse">
                        ⚡ NVIDIA generating content…
                    </span>
                    <span v-else-if="c.posts_count > 0">
                        {{ c.approved_count }}/{{ c.posts_count }} posts approved
                    </span>
                    <span v-else>No posts yet</span>
                    <span>{{ new Date(c.created_at).toLocaleDateString('en-IN', { day:'2-digit', month:'short' }) }}</span>
                </div>

                <!-- Needs review badge -->
                <div v-if="c.status === 'awaiting_approval' && c.approved_count < c.posts_count"
                    class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse" />
                    <span class="text-xs font-medium text-yellow-700">Tap to review & approve</span>
                </div>
            </div>
        </div>
    </div>
</template>
