<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{a: string, b: string}> */
    private function sampleMatchups(): array
    {
        $matchups = [];
        for ($i = 0; $i < 16; $i++) {
            $matchups[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $matchups;
    }

    private function createPoolAsManager(User $user): Pool
    {
        $this->actingAs($user)->post(route('pools.store'), ['name' => 'WC Pool']);

        return Pool::first();
    }

    public function test_manager_can_delete_pool_and_cascades(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);

        $this->assertSame(32, $pool->teams()->count());

        $this->actingAs($manager)
            ->delete(route('pools.destroy', $pool))
            ->assertRedirect(route('pools.index'));

        $this->assertDatabaseMissing('pools', ['id' => $pool->id]);
        // Cascade removed related records.
        $this->assertDatabaseMissing('pool_memberships', ['pool_id' => $pool->id]);
        $this->assertDatabaseMissing('teams', ['pool_id' => $pool->id]);
        $this->assertDatabaseMissing('bracket_matches', ['pool_id' => $pool->id]);
        $this->assertDatabaseMissing('scoring_configs', ['pool_id' => $pool->id]);
    }

    public function test_player_cannot_delete_pool(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->delete(route('pools.destroy', $pool))
            ->assertForbidden();

        $this->assertDatabaseHas('pools', ['id' => $pool->id]);
    }

    public function test_non_member_cannot_delete_pool(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->delete(route('pools.destroy', $pool))
            ->assertForbidden();

        $this->assertDatabaseHas('pools', ['id' => $pool->id]);
    }
}
