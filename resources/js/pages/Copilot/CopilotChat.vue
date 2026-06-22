<script setup lang="ts">
import { ref, onMounted, nextTick, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useWorkspaceStore } from '@/stores/workspace'
import { useCopilotStore } from '@/stores/copilot'
import type { Message } from '@/stores/copilot'

const route          = useRoute()
const workspaceStore = useWorkspaceStore()
const copilot        = useCopilotStore()

const inputText    = ref('')
const messagesEnd  = ref<HTMLElement | null>(null)
const showNewModal = ref(false)
const newCtx       = ref('general')
const copiedId     = ref<number | null>(null)
const expandedSources = ref<Set<number>>(new Set())

const CONTEXT_LABELS: Record<string, string> = {
    general: 'General',
    financial: 'Finance',
    listing: 'Listing',
    competitor: 'Competitor',
}
const CONTEXT_COLORS: Record<string, string> = {
    general: 'bg-gray-100 text-gray-600',
    financial: 'bg-green-100 text-green-700',
    listing: 'bg-blue-100 text-blue-700',
    competitor: 'bg-orange-100 text-orange-700',
}

// Pre-select context from route query
const routeProductId = route.query.product_id ? Number(route.query.product_id) : null
const routeContext   = (route.query.context as string) || 'general'

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await Promise.all([
        copilot.fetchStatus(wsId),
        copilot.fetchConversations(wsId),
    ])

    // Auto-open if conversation_id in query
    if (route.query.conversation_id) {
        await copilot.openConversation(wsId, Number(route.query.conversation_id))
        scrollToBottom()
    }
})

async function startNewConversation() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return

    const conv = await copilot.createConversation(wsId, {
        context_type: newCtx.value,
        context_id:   newCtx.value === 'listing' && routeProductId ? routeProductId : undefined,
    })
    await copilot.openConversation(wsId, conv.id)
    showNewModal.value = false
    scrollToBottom()
}

async function sendMessage() {
    const wsId = workspaceStore.current?.id
    const convId = copilot.current?.id
    if (!wsId || !convId || !inputText.value.trim() || copilot.sending) return

    const msg = inputText.value.trim()
    inputText.value = ''
    await scrollToBottom()
    await copilot.sendMessage(wsId, convId, msg)
    await scrollToBottom()
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        sendMessage()
    }
}

async function scrollToBottom() {
    await nextTick()
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' })
}

async function copyMessage(msg: Message) {
    await navigator.clipboard.writeText(msg.content)
    copiedId.value = msg.id
    setTimeout(() => { copiedId.value = null }, 2000)
}

function toggleSources(msgId: number) {
    if (expandedSources.value.has(msgId)) {
        expandedSources.value.delete(msgId)
    } else {
        expandedSources.value.add(msgId)
    }
}

function formatMarkdown(text: string): string {
    // SECURITY: escape all HTML first so AI/user content can never inject markup
    // (prevents stored/reflected XSS via v-html), THEN apply our limited markdown.
    const esc = String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
    return esc
        .replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs my-2 overflow-x-auto"><code>$2</code></pre>')
        .replace(/`([^`]+)`/g, '<code class="bg-gray-100 px-1 rounded text-xs font-mono">$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>')
}
</script>

<template>
    <div class="flex h-full" style="height: calc(100vh - 0px)">

        <!-- Conversation sidebar -->
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">AI Copilot</h2>
                    <div class="flex items-center gap-1 mt-0.5">
                        <div :class="['w-1.5 h-1.5 rounded-full', copilot.aiStatus?.ai_configured ? 'bg-green-500' : 'bg-gray-300']" />
                        <span class="text-xs text-gray-400">
                            {{ copilot.aiStatus?.active_provider ?? 'Not configured' }}
                        </span>
                    </div>
                </div>
                <button @click="showNewModal = true"
                    class="text-indigo-600 text-sm hover:text-indigo-700 font-medium">
                    + New
                </button>
            </div>

            <!-- Conversation list -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="copilot.conversations.length === 0" class="p-4 text-center text-gray-400 text-xs">
                    No conversations yet.<br>Click "+ New" to start.
                </div>
                <div v-for="conv in copilot.conversations" :key="conv.id"
                    @click="copilot.openConversation(workspaceStore.current?.id!, conv.id)"
                    :class="['px-4 py-3 border-b border-gray-50 cursor-pointer hover:bg-gray-50 transition-colors',
                        copilot.current?.id === conv.id ? 'bg-indigo-50 border-l-2 border-l-indigo-600' : '']">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span :class="['px-1.5 py-0.5 rounded text-xs', CONTEXT_COLORS[conv.context_type] ?? 'bg-gray-100 text-gray-600']">
                            {{ CONTEXT_LABELS[conv.context_type] ?? conv.context_type }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-800 truncate">{{ conv.title ?? 'New conversation' }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ new Date(conv.updated_at).toLocaleDateString('en-IN', { day:'2-digit', month:'short' }) }}
                    </p>
                </div>
            </div>
        </aside>

        <!-- Chat area -->
        <div class="flex-1 flex flex-col bg-gray-50">

            <!-- Empty state -->
            <div v-if="!copilot.current" class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">ASIP AI Copilot</h3>
                    <p class="text-sm text-gray-500 mb-4 max-w-sm">
                        Ask questions about your Amazon business. Get answers grounded in your actual data.
                    </p>
                    <div v-if="!copilot.aiStatus?.ai_configured" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-700 mb-4 max-w-sm">
                        No AI provider configured. Add a key to <code>.env</code> to enable the Copilot.
                    </div>
                    <button @click="showNewModal = true"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                        Start a conversation
                    </button>

                    <!-- Quick starters -->
                    <div v-if="copilot.aiStatus?.ai_configured" class="mt-6 grid grid-cols-2 gap-2 max-w-md">
                        <button v-for="q in [
                            { text: 'Where did my money go?', ctx: 'financial' },
                            { text: 'Why aren\'t my listings selling?', ctx: 'listing' },
                            { text: 'What are my missing settlements?', ctx: 'financial' },
                            { text: 'Which keywords am I missing?', ctx: 'listing' },
                        ]" :key="q.text"
                            @click="async () => {
                                const conv = await copilot.createConversation(workspaceStore.current?.id!, { context_type: q.ctx })
                                await copilot.openConversation(workspaceStore.current?.id!, conv.id)
                                inputText = q.text
                                sendMessage()
                            }"
                            class="text-left text-xs p-3 bg-white border border-gray-200 rounded-lg hover:border-indigo-300 hover:bg-indigo-50 transition-colors text-gray-700">
                            {{ q.text }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Message thread -->
            <template v-else>
                <!-- Header -->
                <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span :class="['px-2 py-0.5 rounded text-xs font-medium', CONTEXT_COLORS[copilot.current.context_type] ?? '']">
                            {{ CONTEXT_LABELS[copilot.current.context_type] }}
                        </span>
                        <span class="text-sm text-gray-700">{{ copilot.current.title ?? 'New conversation' }}</span>
                    </div>
                    <button @click="copilot.deleteConversation(workspaceStore.current?.id!, copilot.current.id)"
                        class="text-xs text-gray-400 hover:text-red-500 transition-colors">
                        Delete
                    </button>
                </div>

                <!-- Messages -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <div v-if="copilot.messages.length === 0" class="text-center text-gray-400 text-sm py-8">
                        Ask anything about your Amazon business…
                    </div>

                    <div v-for="msg in copilot.messages" :key="msg.id"
                        :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                        <div :class="['max-w-2xl', msg.role === 'user' ? 'order-2' : 'order-1']">
                            <!-- Message bubble -->
                            <div :class="[
                                'px-4 py-3 rounded-2xl text-sm',
                                msg.role === 'user'
                                    ? 'bg-indigo-600 text-white rounded-br-sm'
                                    : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm shadow-sm'
                            ]">
                                <div v-if="msg.role === 'assistant'"
                                    class="prose prose-sm max-w-none"
                                    v-html="formatMarkdown(msg.content)" />
                                <p v-else class="whitespace-pre-wrap">{{ msg.content }}</p>
                            </div>

                            <!-- Assistant message footer -->
                            <div v-if="msg.role === 'assistant'" class="flex items-center gap-3 mt-1.5 px-1">
                                <span class="text-xs text-gray-400">{{ msg.model }}</span>

                                <!-- Copy button -->
                                <button @click="copyMessage(msg)"
                                    class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1 transition-colors">
                                    <svg v-if="copiedId !== msg.id" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ copiedId === msg.id ? 'Copied' : 'Copy' }}
                                </button>

                                <!-- RAG sources toggle -->
                                <button v-if="msg.rag_sources?.length"
                                    @click="toggleSources(msg.id)"
                                    class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                    {{ expandedSources.has(msg.id) ? '▲' : '▼' }}
                                    {{ msg.rag_sources.length }} source{{ msg.rag_sources.length > 1 ? 's' : '' }}
                                </button>
                            </div>

                            <!-- RAG sources panel -->
                            <div v-if="msg.role === 'assistant' && expandedSources.has(msg.id) && msg.rag_sources?.length"
                                class="mt-2 space-y-1">
                                <div v-for="src in msg.rag_sources" :key="`${src.type}-${src.id}`"
                                    class="bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 text-xs text-indigo-700">
                                    <span class="font-medium">{{ src.type }} #{{ src.id }}</span>
                                    <span class="text-indigo-400 ml-1">({{ src.similarity }}% match)</span>
                                    <p class="text-indigo-600 mt-0.5 truncate">{{ src.excerpt }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div v-if="copilot.sending" class="flex justify-start">
                        <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                            <div class="flex gap-1.5">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms" />
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms" />
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms" />
                            </div>
                        </div>
                    </div>

                    <div ref="messagesEnd" />
                </div>

                <!-- Input -->
                <div class="bg-white border-t border-gray-200 px-6 py-4">
                    <div class="flex gap-3 items-end">
                        <textarea
                            v-model="inputText"
                            @keydown="handleKeydown"
                            :disabled="copilot.sending"
                            rows="1"
                            placeholder="Ask about your orders, listings, settlements… (Enter to send)"
                            class="flex-1 resize-none border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 max-h-40"
                            style="min-height: 44px"
                        />
                        <button @click="sendMessage"
                            :disabled="!inputText.trim() || copilot.sending"
                            class="flex-shrink-0 w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center hover:bg-indigo-700 disabled:opacity-40 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 text-center">
                        Powered by {{ copilot.aiStatus?.active_provider ?? 'AI' }} · Shift+Enter for newline
                    </p>
                </div>
            </template>
        </div>

        <!-- New Conversation Modal -->
        <div v-if="showNewModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showNewModal = false">
            <div class="bg-white rounded-xl shadow-xl p-6 w-96">
                <h3 class="text-base font-semibold text-gray-900 mb-4">New Conversation</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Context</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="(label, type) in CONTEXT_LABELS" :key="type"
                                @click="newCtx = type"
                                :class="['py-2 px-3 text-sm rounded-lg border-2 transition-colors',
                                    newCtx === type ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-medium' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                                {{ label }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{
                                { general: 'General Amazon questions', financial: 'Orders, settlements, bank, GST', listing: 'Product listing optimization', competitor: 'Competitive intelligence' }[newCtx]
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-5">
                    <button @click="showNewModal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button @click="startNewConversation"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                        Start
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
