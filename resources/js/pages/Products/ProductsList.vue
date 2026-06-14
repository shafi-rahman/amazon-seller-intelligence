<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useProductsStore } from '@/stores/products'
import { useToastStore } from '@/stores/toast'
import ProductForm from '@/components/ProductForm.vue'
import api from '@/api/axios'

const router        = useRouter()
const workspaceStore = useWorkspaceStore()
const productsStore  = useProductsStore()
const toast          = useToastStore()

const showAdd   = ref(false)
const savingNew = ref(false)

function openAdd() { showAdd.value = true }

async function createProduct(payload: Record<string, any>, images: File[] = []) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    savingNew.value = true
    try {
        const product = await productsStore.create(wsId, payload)
        if (images.length) {
            const form = new FormData()
            images.forEach(f => form.append('images[]', f))
            await api.post(`/workspaces/${wsId}/products/${product.id}/images`, form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
        }
        toast.success(images.length ? `Product added with ${images.length} image${images.length > 1 ? 's' : ''}` : 'Product added')
        showAdd.value = false
        router.push(`/products/${product.id}`)
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Could not add product (check ASIN is unique)')
    } finally {
        savingNew.value = false
    }
}

const filters = ref({ search: '', min_score: '', max_score: '', page: 1, per_page: 20 })
const meta    = ref<any>({})

const TIER_STYLE: Record<string, string> = {
    excellent:  'bg-green-100 text-green-700',
    good:       'bg-blue-100 text-blue-700',
    needs_work: 'bg-yellow-100 text-yellow-700',
    poor:       'bg-orange-100 text-orange-700',
    critical:   'bg-red-100 text-red-600',
}
const TIER_LABEL: Record<string, string> = {
    excellent: 'Excellent', good: 'Good', needs_work: 'Needs Work', poor: 'Poor', critical: 'Critical'
}

async function load() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    const params = Object.fromEntries(Object.entries(filters.value).filter(([,v]) => v !== ''))
    const result = await productsStore.fetchAll(wsId, params)
    meta.value = result.meta ?? {}
}

function goToProduct(id: number) {
    router.push(`/products/${id}`)
}

onMounted(load)

function scoreBar(score: number | null) {
    if (score === null) return 0
    return Math.max(2, score)
}
function scoreColor(score: number | null) {
    if (!score) return 'bg-gray-200'
    if (score >= 85) return 'bg-green-500'
    if (score >= 70) return 'bg-blue-500'
    if (score >= 50) return 'bg-yellow-500'
    if (score >= 30) return 'bg-orange-500'
    return 'bg-red-500'
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Products</h1>
                <p class="text-gray-500 text-sm mt-1">Listing intelligence and optimization</p>
            </div>
            <div class="flex items-center gap-3">
                <RouterLink to="/imports/upload" class="text-sm text-indigo-600 hover:underline">+ Upload products CSV</RouterLink>
                <button @click="openAdd"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + Add Product
                </button>
            </div>
        </div>

        <!-- Add Product modal -->
        <Teleport to="body">
            <div v-if="showAdd" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center overflow-y-auto p-4"
                @click.self="showAdd = false">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl my-8 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Add Product</h2>
                        <button @click="showAdd = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">✕</button>
                    </div>
                    <ProductForm :saving="savingNew" @save="createProduct" @cancel="showAdd = false" />
                </div>
            </div>
        </Teleport>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-4">
            <input v-model="filters.search" type="text" placeholder="Search ASIN, title, SKU…"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-56" />
            <input v-model="filters.min_score" type="number" min="0" max="100" placeholder="Min score"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-24" />
            <input v-model="filters.max_score" type="number" min="0" max="100" placeholder="Max score"
                class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-24" />
            <button @click="() => { filters.page = 1; load() }"
                class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                Filter
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="productsStore.loading" class="py-10 text-center text-gray-400 text-sm">Loading products…</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Product</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">ASIN / SKU</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Brand</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Price</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600 text-xs">Rating</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs w-40">Listing Score</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="productsStore.products.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                            No products yet. <RouterLink to="/imports/upload" class="text-indigo-600 hover:underline">Upload products CSV</RouterLink>
                        </td>
                    </tr>
                    <tr v-for="p in productsStore.products" :key="p.id"
                        class="hover:bg-gray-50 cursor-pointer" @click="goToProduct(p.id)">
                        <td class="px-4 py-3 max-w-xs">
                            <div class="text-gray-800 text-xs font-medium truncate">{{ p.title ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono">
                            <div class="text-gray-700">{{ p.asin }}</div>
                            <div class="text-gray-400">{{ p.sku }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ p.brand ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">
                            {{ p.price != null ? '₹' + p.price.toLocaleString('en-IN') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-700">
                            <span v-if="p.rating">{{ p.rating }}★ <span class="text-gray-400">({{ p.review_count }})</span></span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="p.listing_score !== null">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                                        <div :class="['h-1.5 rounded-full transition-all', scoreColor(p.listing_score)]"
                                            :style="{ width: `${scoreBar(p.listing_score)}%` }" />
                                    </div>
                                    <span class="text-xs font-bold w-6 text-right" :class="scoreColor(p.listing_score).replace('bg-', 'text-').replace('-500', '-700')">
                                        {{ p.listing_score }}
                                    </span>
                                </div>
                                <span :class="['text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block', TIER_STYLE[p.score_tier] ?? '']">
                                    {{ TIER_LABEL[p.score_tier] }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-gray-400 italic">Not analyzed</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-xs text-indigo-600 hover:underline" @click.stop="goToProduct(p.id)">
                                View →
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Page {{ meta.page }} of {{ meta.last_page }} · {{ meta.total?.toLocaleString('en-IN') }} products</span>
            <div class="flex gap-2">
                <button @click="() => { filters.page--; load() }" :disabled="filters.page <= 1"
                    class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button @click="() => { filters.page++; load() }" :disabled="filters.page >= meta.last_page"
                    class="px-3 py-1 border border-gray-300 rounded disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>
</template>
