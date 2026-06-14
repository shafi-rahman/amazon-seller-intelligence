<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
    // Existing product to edit (omit for "create")
    product?: Record<string, any> | null
    saving?: boolean
}>()
const emit = defineEmits<{
    (e: 'save', payload: Record<string, any>): void
    (e: 'cancel'): void
}>()

const blank = () => ({
    asin: '', sku: '', title: '', brand: '', category: '', sub_category: '',
    bullet_1: '', bullet_2: '', bullet_3: '', bullet_4: '', bullet_5: '',
    description: '', price: '', currency: 'INR', rating: '', review_count: '',
})

const form = ref<Record<string, any>>(blank())

watch(() => props.product, (p) => {
    if (p) {
        form.value = {
            asin: p.asin ?? '', sku: p.sku ?? '', title: p.title ?? '', brand: p.brand ?? '',
            category: p.category ?? '', sub_category: p.sub_category ?? '',
            bullet_1: p.bullet_1 ?? '', bullet_2: p.bullet_2 ?? '', bullet_3: p.bullet_3 ?? '',
            bullet_4: p.bullet_4 ?? '', bullet_5: p.bullet_5 ?? '',
            description: p.description ?? '', price: p.price ?? '', currency: p.currency ?? 'INR',
            rating: p.rating ?? '', review_count: p.review_count ?? '',
        }
    } else {
        form.value = blank()
    }
}, { immediate: true })

const errors = ref<Record<string, string>>({})

function submit() {
    errors.value = {}
    if (!form.value.asin?.trim()) errors.value.asin = 'ASIN is required'
    if (!form.value.title?.trim()) errors.value.title = 'Title is required'
    if (Object.keys(errors.value).length) return

    // Strip empty strings so optional fields go through as null
    const payload: Record<string, any> = {}
    for (const [k, v] of Object.entries(form.value)) {
        payload[k] = v === '' ? null : v
    }
    emit('save', payload)
}
</script>

<template>
    <div class="space-y-4">
        <!-- Identifiers -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ASIN <span class="text-red-500">*</span></label>
                <input v-model="form.asin" type="text" placeholder="B0XXXXXXXX"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :class="errors.asin ? 'border-red-400' : 'border-gray-300'" />
                <p v-if="errors.asin" class="text-xs text-red-500 mt-0.5">{{ errors.asin }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                <input v-model="form.sku" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
        </div>

        <!-- Title -->
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
            <textarea v-model="form.title" rows="2"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                :class="errors.title ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.title" class="text-xs text-red-500 mt-0.5">{{ errors.title }}</p>
        </div>

        <!-- Brand / category -->
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Brand</label>
                <input v-model="form.brand" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                <input v-model="form.category" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Sub-category</label>
                <input v-model="form.sub_category" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
        </div>

        <!-- Pricing / ratings -->
        <div class="grid grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Price</label>
                <input v-model="form.price" type="number" step="0.01" min="0"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Currency</label>
                <input v-model="form.currency" type="text" maxlength="5"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Rating (0–5)</label>
                <input v-model="form.rating" type="number" step="0.1" min="0" max="5"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Reviews</label>
                <input v-model="form.review_count" type="number" min="0"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
        </div>

        <!-- Bullets -->
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Bullet points</label>
            <div class="space-y-2">
                <input v-for="n in 5" :key="n" v-model="form['bullet_' + n]" type="text"
                    :placeholder="`Bullet ${n}`"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
            <textarea v-model="form.description" rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" />
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-2">
            <button @click="emit('cancel')" :disabled="saving"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">
                Cancel
            </button>
            <button @click="submit" :disabled="saving"
                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                {{ saving ? 'Saving…' : (product ? 'Save changes' : 'Add product') }}
            </button>
        </div>
    </div>
</template>
