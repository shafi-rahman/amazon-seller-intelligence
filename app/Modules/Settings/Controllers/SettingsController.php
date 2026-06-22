<?php

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\SocialAccount;
use App\Modules\Settings\Services\SocialPublisherService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SocialPublisherService $publisher) {}

    /**
     * Settings that manage secrets (API keys, webhook token) or external accounts
     * must be restricted to the workspace OWNER — plain membership is not enough.
     * Returns the workspace after asserting ownership.
     */
    private function ownerOnly(Request $request, int $workspaceId): Workspace
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->owner_id === $request->user()->id, 403,
            'Only the workspace owner can manage these settings.');
        return $workspace;
    }

    // ─── Social Accounts ──────────────────────────────────────────────────

    // GET /workspaces/{id}/settings/social-accounts
    public function socialAccounts(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $platforms = ['facebook', 'instagram', 'linkedin', 'google_business'];
        $accounts  = SocialAccount::where('workspace_id', $workspaceId)->get()->keyBy('platform');

        $result = [];
        foreach ($platforms as $platform) {
            $account = $accounts->get($platform);
            $result[$platform] = $account ? [
                'id'           => $account->id,
                'platform'     => $platform,
                'account_name' => $account->account_name,
                'account_id'   => $account->account_id,
                'is_connected' => $account->is_connected,
                'is_active'    => $account->is_active,
                'token_expires_at' => $account->token_expires_at?->toISOString(),
                'meta'         => $account->meta,
                // Never return actual tokens to frontend
                'has_token'    => !empty($account->access_token),
            ] : [
                'platform'     => $platform,
                'is_connected' => false,
            ];
        }

        return $this->success($result);
    }

    // PUT /workspaces/{id}/settings/social-accounts/{platform}
    public function updateSocialAccount(Request $request, int $workspaceId, string $platform): JsonResponse
    {
        $this->ownerOnly($request, $workspaceId);

        $validated = $request->validate([
            'account_name'  => ['nullable', 'string', 'max:200'],
            'account_id'    => ['nullable', 'string', 'max:200'],
            'access_token'  => ['nullable', 'string'],
            'meta'          => ['nullable', 'array'],
        ]);

        $account = SocialAccount::updateOrCreate(
            ['workspace_id' => $workspaceId, 'platform' => $platform],
            array_filter([
                'account_name' => $validated['account_name'] ?? null,
                'account_id'   => $validated['account_id'] ?? null,
                'access_token' => $validated['access_token'] ?? null,
                'meta'         => $validated['meta'] ?? null,
                'is_active'    => true,
            ], fn($v) => $v !== null)
        );

        return $this->success([
            'platform'     => $platform,
            'account_name' => $account->account_name,
            'account_id'   => $account->account_id,
            'is_connected' => $account->is_connected,
            'has_token'    => !empty($account->access_token),
        ]);
    }

    // POST /workspaces/{id}/settings/social-accounts/{platform}/test
    public function testSocialAccount(Request $request, int $workspaceId, string $platform): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $account = SocialAccount::where('workspace_id', $workspaceId)
            ->where('platform', $platform)
            ->first();

        if (!$account || !$account->access_token) {
            return $this->error("No {$platform} account configured.", 422);
        }

        $connected = $this->publisher->testConnection($account);

        $account->update(['is_connected' => $connected]);

        return $this->success([
            'platform'     => $platform,
            'is_connected' => $connected,
            'message'      => $connected
                ? "✅ Connected to {$platform} successfully!"
                : "❌ Connection failed. Check your access token.",
        ]);
    }

    // DELETE /workspaces/{id}/settings/social-accounts/{platform}
    public function disconnectSocialAccount(Request $request, int $workspaceId, string $platform): JsonResponse
    {
        $this->ownerOnly($request, $workspaceId);

        SocialAccount::where('workspace_id', $workspaceId)
            ->where('platform', $platform)
            ->update(['is_connected' => false, 'is_active' => false]);

        return $this->success(['platform' => $platform, 'is_connected' => false]);
    }

    // ─── AI Keys ──────────────────────────────────────────────────────────

    // GET /workspaces/{id}/settings/ai-keys
    public function aiKeys(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        // Only show masked values — never expose full keys
        return $this->success([
            'nvidia_api_key'       => $this->maskKey(env('NVIDIA_API_KEY')),
            'nvidia_model'         => env('NVIDIA_MODEL', 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning'),
            'groq_api_key'         => $this->maskKey(env('GROQ_API_KEY')),
            'groq_model'           => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'anthropic_api_key'    => $this->maskKey(env('ANTHROPIC_API_KEY')),
            'openai_api_key'       => $this->maskKey(env('OPENAI_API_KEY')),
            'ai_default_provider'  => env('AI_DEFAULT_PROVIDER', 'nvidia'),
            'active_provider'      => app(\App\Modules\AI\Services\AIRouter::class)->activeProvider(),
        ]);
    }

    // PUT /workspaces/{id}/settings/ai-keys
    public function updateAiKeys(Request $request, int $workspaceId): JsonResponse
    {
        $this->ownerOnly($request, $workspaceId);

        // Reject any control character (esp. CR/LF) so a value can never inject a
        // second line into .env (e.g. "x\nAPP_DEBUG=true").
        $noControl = ['nullable', 'string', 'max:500', 'regex:/^[^\r\n]*$/'];
        $validated = $request->validate([
            'nvidia_api_key'      => $noControl,
            'nvidia_model'        => $noControl,
            'groq_api_key'        => $noControl,
            'groq_model'          => $noControl,
            'anthropic_api_key'   => $noControl,
            'openai_api_key'      => $noControl,
            'ai_default_provider' => ['nullable', Rule::in(['nvidia', 'groq', 'anthropic', 'openai'])],
        ]);

        $this->writeEnvValues(array_filter([
            'NVIDIA_API_KEY'      => $validated['nvidia_api_key'] ?? null,
            'NVIDIA_MODEL'        => $validated['nvidia_model'] ?? null,
            'GROQ_API_KEY'        => $validated['groq_api_key'] ?? null,
            'GROQ_MODEL'          => $validated['groq_model'] ?? null,
            'ANTHROPIC_API_KEY'   => $validated['anthropic_api_key'] ?? null,
            'OPENAI_API_KEY'      => $validated['openai_api_key'] ?? null,
            'AI_DEFAULT_PROVIDER' => $validated['ai_default_provider'] ?? null,
        ], fn($v) => $v !== null));

        Artisan::call('config:clear');

        return $this->success(['message' => 'AI keys updated. Config cache cleared.']);
    }

    // ─── Notifications ────────────────────────────────────────────────────

    // GET /workspaces/{id}/settings/notifications
    public function notifications(Request $request, int $workspaceId): JsonResponse
    {
        $this->ownerOnly($request, $workspaceId);

        return $this->success([
            // Never return the raw token here — it grants access to the unauthenticated
            // SEO webhook endpoints. Show a masked value + whether it's configured.
            // The full token is revealed once, transiently, by regenerateToken().
            'seo_webhook_token' => $this->maskKey(env('SEO_WEBHOOK_TOKEN')),
            'seo_webhook_configured' => !empty(env('SEO_WEBHOOK_TOKEN')),
            'app_url'           => env('APP_URL', 'http://localhost:7801'),
            'openclaw_command'  => 'cd openclaw-skill && node seo-agent.js',
        ]);
    }

    // POST /workspaces/{id}/settings/notifications/regenerate-token
    public function regenerateToken(Request $request, int $workspaceId): JsonResponse
    {
        $this->ownerOnly($request, $workspaceId);

        $newToken = bin2hex(random_bytes(32));
        $this->writeEnvValues(['SEO_WEBHOOK_TOKEN' => $newToken]);
        Artisan::call('config:clear');

        return $this->success(['seo_webhook_token' => $newToken]);
    }

    // ─── Publish endpoint for SEO posts ───────────────────────────────────

    // POST /seo/posts/{postId}/publish
    public function publishPost(Request $request, int $postId): JsonResponse
    {
        $post      = \App\Modules\SEO\Models\SeoPost::with(['campaign'])->findOrFail($postId);
        // Publishing uses the owner's connected social-account tokens, so restrict to owner.
        $workspace = Workspace::findOrFail($post->campaign->workspace_id);
        abort_unless($workspace->owner_id === $request->user()->id, 403,
            'Only the workspace owner can publish posts.');
        abort_unless($post->status === 'approved', 422, 'Post must be approved before publishing.');

        \App\Modules\SEO\Jobs\PublishSeoPostJob::dispatch($post->id)->onQueue('default');

        return $this->success(['post_id' => $post->id, 'status' => 'publishing']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function maskKey(?string $key): ?string
    {
        if (!$key) return null;
        $len = strlen($key);
        if ($len <= 8) return str_repeat('*', $len);
        return substr($key, 0, 6) . str_repeat('*', $len - 10) . substr($key, -4);
    }

    private function writeEnvValues(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            // Defense in depth: strip any CR/LF (callers also validate) so a value
            // can never inject extra .env lines, then always double-quote with
            // backslash/quote escaping. preg_quote the key so it can't be a regex.
            $value   = str_replace(["\r", "\n"], '', (string) $value);
            $escaped = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            $replace = "{$key}={$escaped}";

            if (preg_match($pattern, $content)) {
                // Use a callback so $ / \ sequences in the value aren't treated as
                // preg_replace backreferences.
                $content = preg_replace_callback($pattern, fn() => $replace, $content);
            } else {
                $content .= "\n{$replace}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
