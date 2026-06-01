<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useImportStore } from '@/stores/imports'
import type { ApiError } from '@/types'

const router         = useRouter()
const workspaceStore = useWorkspaceStore()
const importStore    = useImportStore()

const htmlContent = ref('')
const asin        = ref('')
const submitting  = ref(false)
const error       = ref('')

async function submit() {
    const wsId = workspaceStore.current?.id
    if (!wsId || !htmlContent.value.trim()) return

    submitting.value = true
    error.value      = ''

    try {
        const batch = await importStore.uploadHtml(wsId, htmlContent.value, null, asin.value || undefined)
        router.push(`/imports/${(batch as any).import_batch_id ?? (batch as any).id}/progress`)
    } catch (e: unknown) {
        const err = e as { response?: { data?: ApiError } }
        error.value = err.response?.data?.message ?? 'Failed to parse HTML. Please ensure you copied the full page source.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="p-6 max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Add Competitor via HTML</h1>
            <p class="text-gray-500 text-sm mt-1">Paste the raw HTML source of an Amazon product page</p>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
            <strong>How to get the page source:</strong>
            <ol class="mt-2 space-y-1 list-decimal list-inside">
                <li>Open the competitor product page on Amazon</li>
                <li>Press <kbd class="bg-blue-100 px-1 rounded">Ctrl+U</kbd> (or right-click → View Page Source)</li>
                <li>Press <kbd class="bg-blue-100 px-1 rounded">Ctrl+A</kbd> to select all, then <kbd class="bg-blue-100 px-1 rounded">Ctrl+C</kbd> to copy</li>
                <li>Paste into the box below</li>
            </ol>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ASIN <span class="text-gray-400 font-normal">(optional — we'll detect it automatically)</span>
                </label>
                <input
                    v-model="asin"
                    type="text"
                    placeholder="B09XXXXXXXX"
                    maxlength="20"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    HTML source <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="htmlContent"
                    rows="12"
                    placeholder="Paste the full HTML page source here…"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                />
                <p class="text-xs text-gray-400 mt-1">
                    {{ htmlContent.length.toLocaleString() }} characters
                </p>
            </div>

            <div v-if="error" class="text-sm text-red-600 bg-red-50 px-4 py-2 rounded-md">{{ error }}</div>

            <div class="flex justify-end gap-3">
                <RouterLink to="/imports" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</RouterLink>
                <button
                    :disabled="!htmlContent.trim() || submitting"
                    @click="submit"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ submitting ? 'Parsing…' : 'Parse & Save Competitor' }}
                </button>
            </div>
        </div>
    </div>
</template>
