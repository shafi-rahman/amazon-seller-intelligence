<?php

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Resources\ConversationResource;
use App\Modules\AI\Services\AIRouter;
use App\Modules\AI\Services\CopilotService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class CopilotController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CopilotService $copilot,
        private readonly AIRouter       $router,
    ) {}

    // GET /workspaces/{id}/ai/conversations
    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $conversations = AiConversation::where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return $this->paginated($conversations);
    }

    // POST /workspaces/{id}/ai/conversations
    public function store(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $validated = $request->validate([
            'context_type' => ['sometimes', Rule::in(['financial', 'listing', 'competitor', 'general'])],
            'context_id'   => ['nullable', 'integer'],
            'title'        => ['nullable', 'string', 'max:500'],
        ]);

        $conversation = AiConversation::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $request->user()->id,
            'context_type' => $validated['context_type'] ?? 'general',
            'context_id'   => $validated['context_id'] ?? null,
            'title'        => $validated['title'] ?? null,
        ]);

        return $this->created(new ConversationResource($conversation));
    }

    // GET /workspaces/{id}/ai/conversations/{conversationId}
    public function show(Request $request, int $workspaceId, int $conversationId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $conversation = AiConversation::where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($conversationId);

        return $this->success(new ConversationResource($conversation));
    }

    // DELETE /workspaces/{id}/ai/conversations/{conversationId}
    public function destroy(Request $request, int $workspaceId, int $conversationId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $conversation = AiConversation::where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->findOrFail($conversationId);

        $conversation->delete();
        return $this->noContent();
    }

    // POST /workspaces/{id}/ai/conversations/{conversationId}/messages
    public function sendMessage(Request $request, int $workspaceId, int $conversationId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        // Rate limit: 30 requests/minute per workspace
        $rateLimitKey = "ai_chat:{$workspaceId}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            return $this->error(
                "Rate limit exceeded. Please wait {$retryAfter} seconds.",
                429
            );
        }
        RateLimiter::hit($rateLimitKey, 60); // 60-second decay

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $conversation = AiConversation::where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($conversationId);

        if (!$this->router->isAnyProviderConfigured()) {
            return $this->error(
                'No AI provider configured. Add GROQ_API_KEY, ANTHROPIC_API_KEY, or OPENAI_API_KEY to .env to enable the AI Copilot.',
                422
            );
        }

        $assistantMsg = $this->copilot->chat($conversation, $validated['message']);

        return $this->success([
            'id'               => $assistantMsg->id,
            'role'             => $assistantMsg->role,
            'content'          => $assistantMsg->content,
            'provider'         => $assistantMsg->provider,
            'model'            => $assistantMsg->model,
            'rag_sources'      => $assistantMsg->rag_sources ?? [],
            'prompt_tokens'    => $assistantMsg->prompt_tokens,
            'completion_tokens'=> $assistantMsg->completion_tokens,
            'created_at'       => $assistantMsg->created_at?->toISOString(),
        ]);
    }

    // GET /workspaces/{id}/ai/status
    public function status(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        return $this->success([
            'ai_configured'        => $this->router->isAnyProviderConfigured(),
            'active_provider'      => $this->router->activeProvider(),
            'embeddings_available' => app(\App\Modules\AI\Services\VectorSearchService::class)->isAvailable(),
        ]);
    }
}
