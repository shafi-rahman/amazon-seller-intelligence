<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
import { useSettingsStore } from '@/stores/settings'
import { useToastStore } from '@/stores/toast'

const workspaceStore = useWorkspaceStore()
const settingsStore  = useSettingsStore()
const toast          = useToastStore()

const activeTab = ref<'social' | 'ai' | 'notifications'>('social')
const editingPlatform = ref<string | null>(null)

// Form state per platform
const socialForms = reactive<Record<string, { account_name: string; account_id: string; access_token: string; meta: Record<string, string> }>>({
    facebook:        { account_name: '', account_id: '', access_token: '', meta: {} },
    instagram:       { account_name: '', account_id: '', access_token: '', meta: {} },
    linkedin:        { account_name: '', account_id: '', access_token: '', meta: {} },
    google_business: { account_name: '', account_id: '', access_token: '', meta: {} },
})

// AI keys form
const aiForm = reactive({
    nvidia_api_key: '',
    nvidia_model: '',
    groq_api_key: '',
    groq_model: '',
    anthropic_api_key: '',
    openai_api_key: '',
    ai_default_provider: 'nvidia',
})

const tokenCopied = ref(false)

const PLATFORM_INFO: Record<string, { label: string; icon: string; color: string; fields: { key: string; label: string; placeholder: string; type?: string }[]; guide: string[] }> = {
    facebook: {
        label: 'Facebook', icon: '📘', color: 'bg-blue-600',
        fields: [
            { key: 'account_name', label: 'Page Name', placeholder: 'e.g. My Brand Page' },
            { key: 'account_id', label: 'Page ID', placeholder: 'e.g. 123456789012345' },
            { key: 'access_token', label: 'Page Access Token', placeholder: 'EAA...', type: 'password' },
        ],
        guide: [
            '1. Go to Meta Business Suite → Settings → Pages',
            '2. Select your page → Advanced → Page Access Tokens',
            '3. Generate a permanent token (60-day or permanent via token debugger)',
            '4. Copy the Page ID from Page Settings → About',
        ],
    },
    instagram: {
        label: 'Instagram', icon: '📸', color: 'bg-pink-500',
        fields: [
            { key: 'account_name', label: 'Account Username', placeholder: 'e.g. @mybrand' },
            { key: 'account_id', label: 'Instagram Business Account ID', placeholder: 'e.g. 17841400008460056' },
            { key: 'access_token', label: 'Access Token (same as Facebook)', placeholder: 'EAA...', type: 'password' },
        ],
        guide: [
            '1. Connect Instagram Business to your Facebook Page first',
            '2. Use the same Page Access Token as Facebook',
            '3. Get your IG Business ID: Graph API Explorer → GET /me/accounts → find ig_id',
            '4. Requires an image to publish feed posts (image generation coming in Phase 3)',
        ],
    },
    linkedin: {
        label: 'LinkedIn', icon: '💼', color: 'bg-sky-700',
        fields: [
            { key: 'account_name', label: 'Profile/Page Name', placeholder: 'e.g. My Company' },
            { key: 'account_id', label: 'Author URN', placeholder: 'urn:li:person:xxx or urn:li:organization:xxx' },
            { key: 'access_token', label: 'OAuth Access Token', placeholder: 'AQX...', type: 'password' },
        ],
        guide: [
            '1. Go to LinkedIn Developer → Your Apps → Create App',
            '2. Enable "Share on LinkedIn" and "w_member_social" scopes',
            '3. Generate access token from OAuth 2.0 flow',
            '4. Get Author URN: GET https://api.linkedin.com/v2/me → id field → urn:li:person:{id}',
        ],
    },
    google_business: {
        label: 'Google Business', icon: '🔍', color: 'bg-red-500',
        fields: [
            { key: 'account_name', label: 'Business Name', placeholder: 'e.g. My Store' },
            { key: 'account_id', label: 'Location Name', placeholder: 'accounts/xxx/locations/xxx' },
            { key: 'access_token', label: 'OAuth Access Token', placeholder: 'ya29...', type: 'password' },
        ],
        guide: [
            '1. Enable Google Business Profile API in Google Cloud Console',
            '2. Create OAuth 2.0 credentials',
            '3. Get Location Name: GET https://mybusiness.googleapis.com/v4/accounts → then /locations',
            '4. Generate access token via OAuth playground: https://developers.google.com/oauthplayground',
        ],
    },
}

onMounted(async () => {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await Promise.all([
        settingsStore.fetchSocialAccounts(wsId),
        settingsStore.fetchAiKeys(wsId),
        settingsStore.fetchNotifications(wsId),
    ])
    // Pre-fill AI form
    if (settingsStore.aiKeys) {
        Object.assign(aiForm, {
            nvidia_model: settingsStore.aiKeys.nvidia_model,
            groq_model:   settingsStore.aiKeys.groq_model,
            ai_default_provider: settingsStore.aiKeys.ai_default_provider,
        })
    }
})

function startEdit(platform: string) {
    editingPlatform.value = platform
    // Reset form
    socialForms[platform].access_token = ''
}

async function saveSocial(platform: string) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    try {
        const form = socialForms[platform]
        await settingsStore.saveSocialAccount(wsId, platform, {
            account_name: form.account_name || undefined,
            account_id:   form.account_id || undefined,
            access_token: form.access_token || undefined,
        })
        editingPlatform.value = null
        toast.success(`${PLATFORM_INFO[platform].label} account saved!`)
    } catch {
        toast.error('Failed to save account')
    }
}

async function testConnection(platform: string) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    try {
        const result = await settingsStore.testConnection(wsId, platform)
        if (result.is_connected) toast.success(result.message)
        else toast.error(result.message)
    } catch {
        toast.error('Connection test failed')
    }
}

async function disconnect(platform: string) {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await settingsStore.disconnectAccount(wsId, platform)
    toast.info(`${PLATFORM_INFO[platform].label} disconnected`)
}

async function saveAiKeys() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    try {
        const payload: Record<string, string> = {}
        if (aiForm.nvidia_api_key)  payload.nvidia_api_key = aiForm.nvidia_api_key
        if (aiForm.groq_api_key)    payload.groq_api_key   = aiForm.groq_api_key
        if (aiForm.anthropic_api_key) payload.anthropic_api_key = aiForm.anthropic_api_key
        if (aiForm.openai_api_key)  payload.openai_api_key = aiForm.openai_api_key
        payload.nvidia_model         = aiForm.nvidia_model
        payload.groq_model           = aiForm.groq_model
        payload.ai_default_provider  = aiForm.ai_default_provider

        await settingsStore.saveAiKeys(wsId, payload)
        // Clear password fields
        aiForm.nvidia_api_key = aiForm.groq_api_key = aiForm.anthropic_api_key = aiForm.openai_api_key = ''
        toast.success('AI keys saved and config cleared!')
    } catch {
        toast.error('Failed to save AI keys')
    }
}

async function regenerateToken() {
    const wsId = workspaceStore.current?.id
    if (!wsId) return
    await settingsStore.regenerateToken(wsId)
    toast.success('New webhook token generated!')
}

async function copyToken() {
    const token = settingsStore.notifications?.seo_webhook_token
    if (token) {
        await navigator.clipboard.writeText(token)
        tokenCopied.value = true
        setTimeout(() => { tokenCopied.value = false }, 2000)
    }
}
</script>

<template>
    <div class="p-6 max-w-4xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="text-gray-500 text-sm mt-1">Configure social media accounts, AI keys, and notifications</p>
        </div>

        <!-- Tab bar -->
        <div class="flex border-b border-gray-200 mb-6 gap-0">
            <button v-for="tab in [
                { key: 'social', label: '📱 Social Accounts' },
                { key: 'ai', label: '🤖 AI Keys' },
                { key: 'notifications', label: '🔔 Notifications' },
            ]" :key="tab.key"
                @click="activeTab = tab.key as any"
                :class="['px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px',
                    activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-700'
                        : 'border-transparent text-gray-500 hover:text-gray-700']">
                {{ tab.label }}
            </button>
        </div>

        <!-- ═══════════ TAB: SOCIAL ACCOUNTS ═══════════ -->
        <div v-if="activeTab === 'social'" class="space-y-6">
            <div v-for="(info, platform) in PLATFORM_INFO" :key="platform"
                class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                <!-- Platform header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div :class="['w-9 h-9 rounded-lg flex items-center justify-center text-white text-lg', info.color]">
                            {{ info.icon }}
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">{{ info.label }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <div :class="['w-1.5 h-1.5 rounded-full', settingsStore.socialAccounts[platform]?.is_connected ? 'bg-green-500' : 'bg-gray-300']" />
                                <span class="text-xs text-gray-500">
                                    {{ settingsStore.socialAccounts[platform]?.is_connected ? 'Connected' : 'Not connected' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="settingsStore.socialAccounts[platform]?.has_token"
                            @click="testConnection(platform)"
                            :disabled="settingsStore.testing === platform"
                            class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors">
                            {{ settingsStore.testing === platform ? 'Testing…' : 'Test Connection' }}
                        </button>
                        <button v-if="settingsStore.socialAccounts[platform]?.is_connected"
                            @click="disconnect(platform)"
                            class="px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                            Disconnect
                        </button>
                        <button @click="startEdit(platform)"
                            class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            {{ settingsStore.socialAccounts[platform]?.has_token ? 'Update' : 'Connect' }}
                        </button>
                    </div>
                </div>

                <!-- Expanded form -->
                <div v-if="editingPlatform === platform" class="px-5 py-4">
                    <div class="grid grid-cols-1 gap-3 mb-4">
                        <div v-for="field in info.fields" :key="field.key">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ field.label }}</label>
                            <input
                                v-model="socialForms[platform][field.key as 'account_name'|'account_id'|'access_token']"
                                :type="field.type ?? 'text'"
                                :placeholder="field.placeholder"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- How to get credentials guide -->
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">📋 How to get credentials:</p>
                        <ol class="space-y-1">
                            <li v-for="step in info.guide" :key="step" class="text-xs text-gray-600">{{ step }}</li>
                        </ol>
                    </div>

                    <div class="flex gap-2">
                        <button @click="editingPlatform = null" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button @click="saveSocial(platform)" :disabled="settingsStore.saving"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            {{ settingsStore.saving ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>

                <!-- Connected account info -->
                <div v-else-if="settingsStore.socialAccounts[platform]?.account_name" class="px-5 py-3">
                    <p class="text-xs text-gray-500">
                        Account: <strong>{{ settingsStore.socialAccounts[platform].account_name }}</strong>
                        · ID: <code class="text-xs bg-gray-100 px-1 rounded">{{ settingsStore.socialAccounts[platform].account_id }}</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- ═══════════ TAB: AI KEYS ═══════════ -->
        <div v-if="activeTab === 'ai'" class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">AI Provider Keys</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Active provider: <strong class="text-indigo-600">{{ settingsStore.aiKeys?.active_provider ?? 'none' }}</strong>
                    </p>
                </div>
            </div>

            <!-- Provider priority info -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-6 text-xs text-indigo-700">
                <strong>Provider chain:</strong> NVIDIA → Groq → Anthropic → OpenAI
                · First configured key wins. OpenAI also used for RAG embeddings.
            </div>

            <div class="space-y-5">
                <!-- NVIDIA -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-base">⚡</span>
                        <h4 class="text-sm font-semibold text-gray-800">NVIDIA (Primary)</h4>
                        <span v-if="settingsStore.aiKeys?.nvidia_api_key" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Active</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">API Key</label>
                            <input v-model="aiForm.nvidia_api_key" type="password"
                                :placeholder="settingsStore.aiKeys?.nvidia_api_key ? '••••••••••••••' : 'nvapi-...'"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Model</label>
                            <input v-model="aiForm.nvidia_model"
                                placeholder="nvidia/nemotron-3-nano-omni-30b-a3b-reasoning"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Groq -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-base">🚀</span>
                        <h4 class="text-sm font-semibold text-gray-800">Groq (Fast Fallback)</h4>
                        <span v-if="settingsStore.aiKeys?.groq_api_key" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Active</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">API Key</label>
                            <input v-model="aiForm.groq_api_key" type="password"
                                :placeholder="settingsStore.aiKeys?.groq_api_key ? '••••••••••••••' : 'gsk_...'"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Model</label>
                            <input v-model="aiForm.groq_model"
                                placeholder="llama-3.3-70b-versatile"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Anthropic + OpenAI -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-base">🧠</span>
                            <h4 class="text-sm font-semibold text-gray-800">Anthropic Claude</h4>
                            <span v-if="settingsStore.aiKeys?.anthropic_api_key" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Active</span>
                        </div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">API Key</label>
                        <input v-model="aiForm.anthropic_api_key" type="password"
                            :placeholder="settingsStore.aiKeys?.anthropic_api_key ? '••••••••••••••' : 'sk-ant-...'"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-base">🔍</span>
                            <h4 class="text-sm font-semibold text-gray-800">OpenAI (Embeddings)</h4>
                            <span v-if="settingsStore.aiKeys?.openai_api_key" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Active</span>
                        </div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">API Key (for RAG)</label>
                        <input v-model="aiForm.openai_api_key" type="password"
                            :placeholder="settingsStore.aiKeys?.openai_api_key ? '••••••••••••••' : 'sk-...'"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                </div>

                <!-- Default provider -->
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 w-32">Default Provider</label>
                    <select v-model="aiForm.ai_default_provider"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="nvidia">NVIDIA (Nemotron)</option>
                        <option value="groq">Groq (Llama)</option>
                        <option value="anthropic">Anthropic (Claude)</option>
                        <option value="openai">OpenAI (GPT)</option>
                    </select>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button @click="saveAiKeys" :disabled="settingsStore.saving"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {{ settingsStore.saving ? 'Saving…' : 'Save AI Settings' }}
                </button>
            </div>
        </div>

        <!-- ═══════════ TAB: NOTIFICATIONS ═══════════ -->
        <div v-if="activeTab === 'notifications'" class="space-y-6">

            <!-- OpenClaw integration -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="text-2xl">🤖</span>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">OpenClaw Integration</h3>
                        <p class="text-xs text-gray-500">Receive WhatsApp notifications when SEO posts are ready</p>
                    </div>
                </div>

                <!-- Webhook token -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Webhook Token</label>
                    <div class="flex gap-2">
                        <input :value="settingsStore.notifications?.seo_webhook_token"
                            readonly type="text"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono bg-gray-50 text-gray-700 focus:outline-none" />
                        <button @click="copyToken"
                            class="px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            {{ tokenCopied ? '✓ Copied' : '📋 Copy' }}
                        </button>
                        <button @click="regenerateToken"
                            class="px-3 py-2 text-xs text-orange-600 border border-orange-200 rounded-lg hover:bg-orange-50 transition-colors">
                            Regenerate
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        This token authenticates OpenClaw when it calls the ASIP API.
                    </p>
                </div>

                <!-- Setup instructions -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-xs font-semibold text-gray-700 mb-3">📋 Setup OpenClaw Agent:</p>
                    <div class="space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold text-indigo-600 w-4 flex-shrink-0">1</span>
                            <p class="text-xs text-gray-600">Install OpenClaw: <code class="bg-gray-200 px-1 rounded">curl -fsSL https://openclaw.ai/install.sh | bash</code></p>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold text-indigo-600 w-4 flex-shrink-0">2</span>
                            <p class="text-xs text-gray-600">Go to project folder: <code class="bg-gray-200 px-1 rounded">cd openclaw-skill && npm install</code></p>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold text-indigo-600 w-4 flex-shrink-0">3</span>
                            <div class="text-xs text-gray-600">
                                <p>Set environment:</p>
                                <pre class="bg-gray-200 px-2 py-1 rounded mt-1 text-xs overflow-x-auto">export SEO_WEBHOOK_TOKEN={{ settingsStore.notifications?.seo_webhook_token }}
export ASIP_URL={{ settingsStore.notifications?.app_url }}</pre>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold text-indigo-600 w-4 flex-shrink-0">4</span>
                            <p class="text-xs text-gray-600">Run: <code class="bg-gray-200 px-1 rounded">node seo-agent.js</code> — or tell OpenClaw to run it every 5 minutes</p>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold text-indigo-600 w-4 flex-shrink-0">5</span>
                            <p class="text-xs text-gray-600">You'll receive a WhatsApp/Telegram message when SEO posts are ready for review!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- App URL -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Application URL</h3>
                <p class="text-xs text-gray-500 mb-2">Used in notification links. Must be accessible from your machine.</p>
                <code class="block bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700">
                    {{ settingsStore.notifications?.app_url }}
                </code>
                <p class="text-xs text-gray-400 mt-2">Change via APP_URL in .env if needed</p>
            </div>
        </div>
    </div>
</template>
