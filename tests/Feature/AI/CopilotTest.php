<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Services\AIRouter;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    }

    // ─── AIRouter ─────────────────────────────────────────────────────────

    public function test_ai_router_detects_groq_as_configured(): void
    {
        config(['ai.providers.groq.api_key' => 'test-groq-key']);
        $router = new AIRouter();
        $this->assertTrue($router->isAnyProviderConfigured());
        $this->assertEquals('groq', $router->activeProvider());
    }

    public function test_ai_router_returns_unconfigured_when_no_keys(): void
    {
        config(['ai.providers.groq.api_key'      => null]);
        config(['ai.providers.anthropic.api_key' => null]);
        config(['ai.providers.openai.api_key'    => null]);

        $router = new AIRouter();
        $this->assertFalse($router->isAnyProviderConfigured());
        $this->assertNull($router->activeProvider());
    }

    public function test_ai_router_calls_groq_api_with_correct_format(): void
    {
        config(['ai.providers.groq.api_key' => 'test-groq-key']);
        config(['ai.providers.groq.model'   => 'llama-3.3-70b-versatile']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Test response from Groq']]],
                'usage'   => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ], 200),
        ]);

        $router   = new AIRouter();
        $messages = [
            ['role' => 'system',    'content' => 'You are a helpful assistant.'],
            ['role' => 'user',      'content' => 'Hello'],
        ];

        $result = $router->chat($messages, 'general', 1024, workspaceId: 1);

        $this->assertEquals('Test response from Groq', $result['content']);
        $this->assertEquals('groq', $result['provider']);
        $this->assertEquals(100, $result['prompt_tokens']);
        Http::assertSent(fn($req) => str_contains($req->url(), 'groq.com'));
    }

    public function test_ai_router_falls_back_to_anthropic_when_groq_fails(): void
    {
        config(['ai.providers.groq.api_key'      => 'test-groq-key']);
        config(['ai.providers.anthropic.api_key' => 'test-anthropic-key']);

        Http::fake([
            'api.groq.com/*'           => Http::response(['error' => 'rate limit'], 429),
            'api.anthropic.com/*'      => Http::response([
                'content' => [['text' => 'Fallback from Claude']],
                'model'   => 'claude-sonnet',
                'usage'   => ['input_tokens' => 80, 'output_tokens' => 40],
            ], 200),
        ]);

        $router = new AIRouter();
        $result = $router->chat([['role' => 'user', 'content' => 'Hi']], workspaceId: 0);

        $this->assertEquals('Fallback from Claude', $result['content']);
        $this->assertEquals('anthropic', $result['provider']);
    }

    public function test_ai_router_throws_when_all_providers_fail(): void
    {
        config(['ai.providers.groq.api_key' => 'bad-key']);
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/All AI providers failed/');

        $router = new AIRouter();
        $router->chat([['role' => 'user', 'content' => 'Hi']], workspaceId: 0);
    }

    // ─── Conversation endpoints ───────────────────────────────────────────

    public function test_can_create_conversation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations", [
                'context_type' => 'financial',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.context_type', 'financial');

        $this->assertDatabaseHas('ai_conversations', [
            'workspace_id' => $this->workspace->id,
            'context_type' => 'financial',
        ]);
    }

    public function test_can_list_conversations(): void
    {
        AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
            'title'        => 'Test conversation',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_show_conversation_with_messages(): void
    {
        $conv = AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);
        AiMessage::create([
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => 'Hello',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'messages']]);
    }

    public function test_can_delete_conversation(): void
    {
        $conv = AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conv->id]);
    }

    // ─── Send message endpoint ────────────────────────────────────────────

    public function test_send_message_calls_ai_and_stores_response(): void
    {
        config(['ai.providers.groq.api_key' => 'test-key']);
        config(['ai.providers.groq.model'   => 'llama-3.3-70b-versatile']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Your missing settlements are X, Y, Z.']]],
                'usage'   => ['prompt_tokens' => 150, 'completion_tokens' => 30],
            ], 200),
        ]);

        $conv = AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'financial',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => 'What are my missing settlements?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.provider', 'groq');

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conv->id,
            'role'            => 'assistant',
        ]);
        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conv->id,
            'role'            => 'user',
            'content'         => 'What are my missing settlements?',
        ]);
    }

    public function test_send_message_returns_422_when_no_provider(): void
    {
        config(['ai.providers.groq.api_key'      => null]);
        config(['ai.providers.anthropic.api_key' => null]);
        config(['ai.providers.openai.api_key'    => null]);

        $conv = AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => 'Hello',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', fn($msg) => str_contains($msg, 'No AI provider configured'));
    }

    // ─── Rate limiting ────────────────────────────────────────────────────

    public function test_rate_limit_returns_429_after_30_requests(): void
    {
        config(['ai.providers.groq.api_key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
                'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $conv = AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        // Exhaust the rate limit manually
        $rateLimitKey = "ai_chat:{$this->workspace->id}";
        for ($i = 0; $i < 31; $i++) {
            RateLimiter::hit($rateLimitKey, 60);
        }

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => 'This should be rate limited',
            ]);

        $response->assertStatus(429);
    }

    // ─── Status endpoint ──────────────────────────────────────────────────

    public function test_status_endpoint_shows_provider_info(): void
    {
        config(['ai.providers.groq.api_key' => 'test-key']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/ai/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.ai_configured', true)
            ->assertJsonPath('data.active_provider', 'groq');
    }
}
