<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useImportStore } from '@/stores/imports'
import type { ApiError } from '@/types'

const router         = useRouter()
const workspaceStore = useWorkspaceStore()
const importStore    = useImportStore()

const IMPORT_TYPES = [
    { value: 'orders',          label: 'Amazon Orders Report',     accept: '.csv,.txt' },
    { value: 'settlements',     label: 'Amazon Settlement Report', accept: '.csv,.txt' },
    { value: 'bank_statement',  label: 'Bank Statement',           accept: '.csv' },
    { value: 'gst_report',      label: 'Amazon GST Report',        accept: '.csv' },
    { value: 'products',        label: 'Product Listings',         accept: '.csv' },
    { value: 'competitors_csv', label: 'Competitor Data (CSV)',    accept: '.csv' },
]

const selectedType  = ref('')
const selectedFile  = ref<File | null>(null)
const isDragging    = ref(false)
const uploading     = ref(false)
const error         = ref('')

// After upload: column mapping step
const batchResult   = ref<null | { import_batch_id: number; suggested_mapping: Record<string, string | null>; detected_columns: string[]; row_sample: Record<string, string>[] }>(null)
const editedMapping = ref<Record<string, string | null>>({})
const confirming    = ref(false)

const acceptAttr = computed(() => IMPORT_TYPES.find(t => t.value === selectedType.value)?.accept ?? '.csv')

function onDrop(e: DragEvent) {
    isDragging.value = false
    const file = e.dataTransfer?.files[0]
    if (file) selectedFile.value = file
}

function onFileInput(e: Event) {
    const input = e.target as HTMLInputElement
    if (input.files?.[0]) selectedFile.value = input.files[0]
}

function removeFile() {
    selectedFile.value = null
    batchResult.value  = null
    error.value        = ''
}

async function upload() {
    if (!selectedFile.value || !selectedType.value) return
    const wsId = workspaceStore.current?.id
    if (!wsId) return

    uploading.value = true
    error.value     = ''

    try {
        const result = await importStore.upload(wsId, selectedType.value, selectedFile.value)
        batchResult.value  = result as any
        editedMapping.value = { ...(result as any).suggested_mapping }
    } catch (e: unknown) {
        const err = e as { response?: { data?: ApiError } }
        error.value = err.response?.data?.message ?? 'Upload failed. Please try again.'
    } finally {
        uploading.value = false
    }
}

async function confirmMapping() {
    if (!batchResult.value) return
    confirming.value = true
    try {
        await importStore.confirmMapping(batchResult.value.import_batch_id, editedMapping.value)
        router.push(`/imports/${batchResult.value.import_batch_id}/progress`)
    } finally {
        confirming.value = false
    }
}

function formatSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
</script>

<template>
    <div class="p-6 max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">New Import</h1>
            <p class="text-gray-500 text-sm mt-1">Upload a CSV file to import data into ASIP</p>
        </div>

        <!-- Step 1: Select type + file -->
        <div v-if="!batchResult" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Import type</label>
                <select v-model="selectedType" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="" disabled>Select what you're importing…</option>
                    <option v-for="t in IMPORT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <!-- Drop zone -->
            <div
                v-if="selectedType"
                class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
                :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="onDrop"
                @click="($refs.fileInput as HTMLInputElement).click()"
            >
                <input ref="fileInput" type="file" class="hidden" :accept="acceptAttr" @change="onFileInput" />

                <div v-if="!selectedFile">
                    <svg class="mx-auto mb-3 w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-sm text-gray-600">Drop your CSV file here, or <span class="text-indigo-600 font-medium">browse</span></p>
                    <p class="text-xs text-gray-400 mt-1">Max 50 MB · {{ acceptAttr }} files</p>
                </div>

                <div v-else class="flex items-center justify-between bg-gray-50 rounded-md px-4 py-3" @click.stop>
                    <div class="flex items-center gap-3">
                        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="text-left">
                            <div class="text-sm font-medium text-gray-800">{{ selectedFile.name }}</div>
                            <div class="text-xs text-gray-400">{{ formatSize(selectedFile.size) }}</div>
                        </div>
                    </div>
                    <button @click="removeFile" class="text-gray-400 hover:text-red-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="error" class="text-sm text-red-600 bg-red-50 px-4 py-2 rounded-md">{{ error }}</div>

            <div class="flex justify-end gap-3">
                <RouterLink to="/imports" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</RouterLink>
                <button
                    :disabled="!selectedFile || !selectedType || uploading"
                    @click="upload"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ uploading ? 'Uploading…' : 'Upload & Detect Columns' }}
                </button>
            </div>
        </div>

        <!-- Step 2: Column mapping confirmation -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Confirm Column Mapping</h2>
            <p class="text-sm text-gray-500 mb-4">
                We detected {{ batchResult.detected_columns.length }} columns. Verify the mapping below before processing.
            </p>

            <div class="overflow-auto max-h-96 border border-gray-200 rounded-md">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium text-gray-600 w-1/2">CSV column</th>
                            <th class="text-left px-4 py-2 font-medium text-gray-600 w-1/2">Maps to</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="col in batchResult.detected_columns" :key="col">
                            <td class="px-4 py-2 text-gray-700 font-mono text-xs">{{ col }}</td>
                            <td class="px-4 py-2">
                                <input
                                    v-model="editedMapping[col]"
                                    type="text"
                                    class="w-full text-xs border border-gray-200 rounded px-2 py-1 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    :placeholder="editedMapping[col] ? '' : '(skip this column)'"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <button @click="batchResult = null" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Back</button>
                <button
                    :disabled="confirming"
                    @click="confirmMapping"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ confirming ? 'Starting import…' : 'Confirm & Start Import' }}
                </button>
            </div>
        </div>
    </div>
</template>
