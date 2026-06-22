<?php

namespace Tests\Feature\Workspace;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_workspaces(): void
    {
        $user      = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->getJson('/api/v1/workspaces');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'marketplace', 'currency']]]);
    }

    public function test_user_can_create_workspace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workspaces', [
            'name'        => 'My New Store',
            'marketplace' => 'IN',
            'currency'    => 'INR',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My New Store');

        $this->assertDatabaseHas('workspaces', ['name' => 'My New Store', 'owner_id' => $user->id]);
    }

    public function test_workspace_slug_is_unique(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create(['name' => 'My Store', 'slug' => 'my-store', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/workspaces', [
            'name' => 'My Store',
        ]);

        $response->assertStatus(201);
        $created = $response->json('data');

        $this->assertNotEquals('my-store', $created['slug']);
    }

    public function test_user_can_view_their_workspace(): void
    {
        $user      = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->public_id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $workspace->id);
    }

    public function test_user_cannot_view_another_users_workspace(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($other)->getJson("/api/v1/workspaces/{$workspace->public_id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_workspace(): void
    {
        $user      = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->putJson("/api/v1/workspaces/{$workspace->public_id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_unauthenticated_user_cannot_access_workspaces(): void
    {
        $response = $this->getJson('/api/v1/workspaces');
        $response->assertStatus(401);
    }
}
