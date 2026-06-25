<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_pools(): void
    {
        $this->get(route('pools.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_a_pool_and_becomes_manager(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('pools.store'), [
            'name' => 'Office World Cup 2026',
        ]);

        $pool = Pool::first();

        $this->assertNotNull($pool);
        $response->assertRedirect(route('pools.show', $pool));

        $this->assertDatabaseHas('pools', [
            'name' => 'Office World Cup 2026',
            'status' => 'setup',
            'created_by' => $user->id,
        ]);

        // Creator is registered as a manager.
        $this->assertDatabaseHas('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => 'manager',
        ]);

        // A default scoring config is seeded (Final defaults to 16 points).
        $this->assertDatabaseHas('scoring_configs', [
            'pool_id' => $pool->id,
            'pts_final' => 16,
        ]);
    }

    public function test_pool_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('pools.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('pools', 0);
    }

    public function test_non_member_cannot_view_pool(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('pools.store'), ['name' => 'Private Pool']);
        $pool = Pool::first();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->get(route('pools.show', $pool))
            ->assertForbidden();
    }
}
