<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketSetupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{a: string, b: string}>
     */
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

    public function test_manager_can_build_the_full_bracket(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)
            ->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()])
            ->assertRedirect(route('pools.show', $pool));

        // 32 teams, and 32 total matches (16 + 8 + 4 + 2 + 1 + 1).
        $this->assertSame(32, $pool->teams()->count());
        $this->assertSame(16, $pool->matches()->where('round', 'R32')->count());
        $this->assertSame(8, $pool->matches()->where('round', 'R16')->count());
        $this->assertSame(4, $pool->matches()->where('round', 'QF')->count());
        $this->assertSame(2, $pool->matches()->where('round', 'SF')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'THIRD')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'FINAL')->count());

        // R32 #1 has both teams and feeds an R16 match.
        $r32 = $pool->matches()->where('round', 'R32')->where('position', 1)->first();
        $this->assertNotNull($r32->team_a_id);
        $this->assertNotNull($r32->team_b_id);
        $r16Ids = $pool->matches()->where('round', 'R16')->pluck('id');
        $this->assertTrue($r16Ids->contains($r32->winner_to_match_id));
        $this->assertSame('A', $r32->winner_to_slot);

        // SF winners feed the Final; SF losers feed the Third Place match.
        $final = $pool->matches()->where('round', 'FINAL')->first();
        $third = $pool->matches()->where('round', 'THIRD')->first();
        foreach ($pool->matches()->where('round', 'SF')->get() as $sf) {
            $this->assertSame($final->id, $sf->winner_to_match_id);
            $this->assertSame($third->id, $sf->loser_to_match_id);
        }
    }

    public function test_duplicate_team_names_are_rejected(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $matchups = $this->sampleMatchups();
        $matchups[5]['b'] = $matchups[0]['a']; // create a duplicate

        $this->actingAs($manager)
            ->post(route('pools.bracket.store', $pool), ['matchups' => $matchups])
            ->assertSessionHasErrors('matchups');

        $this->assertSame(0, $pool->teams()->count());
    }

    public function test_player_cannot_build_bracket(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()])
            ->assertForbidden();

        $this->assertSame(0, $pool->teams()->count());
    }

    public function test_bracket_cannot_be_loaded_twice(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        // Second attempt should be blocked.
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);

        $this->assertSame(32, $pool->teams()->count());
    }
}
