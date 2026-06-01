import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
    id: number
    type: ToastType
    message: string
    duration: number
}

let nextId = 1

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([])

    function add(message: string, type: ToastType = 'info', duration = 4000): void {
        const id = nextId++
        toasts.value.push({ id, type, message, duration })
        setTimeout(() => remove(id), duration)
    }

    function remove(id: number): void {
        toasts.value = toasts.value.filter(t => t.id !== id)
    }

    const success = (msg: string) => add(msg, 'success')
    const error   = (msg: string) => add(msg, 'error', 6000)
    const info    = (msg: string) => add(msg, 'info')
    const warning = (msg: string) => add(msg, 'warning')

    return { toasts, add, remove, success, error, info, warning }
})
