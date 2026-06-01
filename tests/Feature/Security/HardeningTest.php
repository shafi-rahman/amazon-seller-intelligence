<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HardeningTest extends TestCase
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

    // ─── Security Headers ──────────────────────────────────────────────────

    public function test_security_headers_present_on_all_responses(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ─── Authentication ────────────────────────────────────────────────────

    public function test_unauthenticated_requests_return_401(): void
    {
        $endpoints = [
            ['GET',  "/api/v1/workspaces"],
            ['GET',  "/api/v1/workspaces/{$this->workspace->id}/orders"],
            ['GET',  "/api/v1/workspaces/{$this->workspace->id}/ai/conversations"],
            ['POST', "/api/v1/workspaces/{$this->workspace->id}/reconciliation/run"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401, "Expected 401 for {$method} {$url}");
        }
    }

    // ─── Authorization (workspace isolation) ──────────────────────────────

    public function test_user_cannot_access_another_users_workspace(): void
    {
        $other   = User::factory()->create();
        $otherWs = Workspace::factory()->create(['owner_id' => $other->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$otherWs->id}/orders");

        $response->assertStatus(403);
    }

    public function test_user_cannot_view_another_users_conversation(): void
    {
        $other   = User::factory()->create();
        $otherWs = Workspace::factory()->create(['owner_id' => $other->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$otherWs->id}/ai/conversations");

        $response->assertStatus(403);
    }

    // ─── Input Validation (SQL Injection Prevention) ──────────────────────

    public function test_sql_injection_attempt_in_search_is_rejected_or_sanitized(): void
    {
        // SQLi payload in search parameter
        $payload = "'; DROP TABLE orders; --";

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders?search={$payload}");

        // Should return 200 with empty/safe results, NOT a DB error
        $response->assertStatus(200);

        // Database should still have tables
        $this->assertDatabaseHas('migrations', ['migration' => '0001_01_01_000000_create_users_table']);
    }

    public function test_sql_injection_in_order_id_filter_is_safe(): void
    {
        $payload = "403-111' OR '1'='1";

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/gst-transactions?order_id={$payload}");

        $response->assertStatus(200);
    }

    // ─── Input Validation ─────────────────────────────────────────────────

    public function test_empty_message_to_ai_returns_422(): void
    {
        $conv = \App\Modules\AI\Models\AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_oversized_message_to_ai_returns_422(): void
    {
        $conv = \App\Modules\AI\Models\AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => str_repeat('A', 5001), // exceeds 5000 char limit
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ─── Rate Limiting ────────────────────────────────────────────────────

    public function test_ai_rate_limit_triggers_429(): void
    {
        $conv = \App\Modules\AI\Models\AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $key = "ai_chat:{$this->workspace->id}";
        for ($i = 0; $i < 31; $i++) {
            RateLimiter::hit($key, 60);
        }

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}/messages", [
                'message' => 'test',
            ]);

        $response->assertStatus(429);
    }

    // ─── HTTP Method enforcement ───────────────────────────────────────────

    public function test_wrong_http_method_returns_405(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/auth/login', []);

        $response->assertStatus(405);
    }

    // ─── CRUD operations return correct status codes ───────────────────────

    public function test_404_for_nonexistent_resources(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports/99999");

        $response->assertStatus(404);
    }

    public function test_create_returns_201(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/workspaces', [
                'name' => 'New Workspace',
            ]);

        $response->assertStatus(201);
    }

    public function test_delete_returns_204(): void
    {
        $conv = \App\Modules\AI\Models\AiConversation::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'context_type' => 'general',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/ai/conversations/{$conv->id}");

        $response->assertStatus(204);
    }
}
