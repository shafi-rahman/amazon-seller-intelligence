<script setup lang="ts">
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

const TOAST_STYLES = {
    success: 'bg-green-50 border-green-200 text-green-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
    warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
}

const TOAST_ICONS: Record<string, string> = {
    success: '✓',
    error:   '✗',
    info:    'ℹ',
    warning: '⚠',
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[100] space-y-2 max-w-sm w-full pointer-events-none">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toastStore.toasts"
                    :key="toast.id"
                    :class="['flex items-start gap-3 px-4 py-3 rounded-lg border shadow-md pointer-events-auto cursor-pointer text-sm',
                        TOAST_STYLES[toast.type]]"
                    @click="toastStore.remove(toast.id)"
                >
                    <span class="font-bold flex-shrink-0">{{ TOAST_ICONS[toast.type] }}</span>
                    <span class="flex-1 leading-snug">{{ toast.message }}</span>
                    <button class="flex-shrink-0 opacity-60 hover:opacity-100 ml-1 text-xs">×</button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
.toast-move {
    transition: transform 0.25s ease;
}
</style>
