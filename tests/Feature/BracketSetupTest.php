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

    // --- Non-R32 starting rounds ---

    private function makeMatchups(int $count): array
    {
        $matchups = [];
        for ($i = 0; $i < $count; $i++) {
            $matchups[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $matchups;
    }

    public function test_pool_defaults_to_r32_start_round(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->assertSame('R32', $pool->start_round);
    }

    public function test_manager_can_create_pool_starting_at_r16(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'R16 Pool', 'method' => 'full', 'start_round' => 'R16']);
        $pool = Pool::first();

        $this->assertSame('R16', $pool->start_round);
        $this->assertSame(8, $pool->startRoundMatchCount());
        $this->assertSame(16, $pool->startRoundTeamCount());
    }

    public function test_r16_start_bracket_has_correct_structure(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'R16 Pool', 'method' => 'full', 'start_round' => 'R16']);
        $pool = Pool::first();

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->makeMatchups(8)]);

        $this->assertSame(16, $pool->teams()->count());
        $this->assertSame(0, $pool->matches()->where('round', 'R32')->count());
        $this->assertSame(8, $pool->matches()->where('round', 'R16')->count());
        $this->assertSame(4, $pool->matches()->where('round', 'QF')->count());
        $this->assertSame(2, $pool->matches()->where('round', 'SF')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'THIRD')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'FINAL')->count());

        $r16 = $pool->matches()->where('round', 'R16')->where('position', 1)->first();
        $this->assertNotNull($r16->team_a_id);
        $this->assertNotNull($r16->team_b_id);
        $qfIds = $pool->matches()->where('round', 'QF')->pluck('id');
        $this->assertTrue($qfIds->contains($r16->winner_to_match_id));
    }

    public function test_qf_start_bracket_has_correct_structure(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'QF Pool', 'method' => 'full', 'start_round' => 'QF']);
        $pool = Pool::first();

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->makeMatchups(4)]);

        $this->assertSame(8, $pool->teams()->count());
        $this->assertSame(0, $pool->matches()->where('round', 'R32')->count());
        $this->assertSame(0, $pool->matches()->where('round', 'R16')->count());
        $this->assertSame(4, $pool->matches()->where('round', 'QF')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'THIRD')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'FINAL')->count());

        $qf = $pool->matches()->where('round', 'QF')->where('position', 1)->first();
        $this->assertNotNull($qf->team_a_id);
    }

    public function test_sf_start_bracket_has_correct_structure(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'SF Pool', 'method' => 'full', 'start_round' => 'SF']);
        $pool = Pool::first();

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->makeMatchups(2)]);

        $this->assertSame(4, $pool->teams()->count());
        $this->assertSame(2, $pool->matches()->where('round', 'SF')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'THIRD')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'FINAL')->count());

        $final = $pool->matches()->where('round', 'FINAL')->first();
        $third = $pool->matches()->where('round', 'THIRD')->first();
        foreach ($pool->matches()->where('round', 'SF')->get() as $sf) {
            $this->assertSame($final->id, $sf->winner_to_match_id);
            $this->assertSame($third->id, $sf->loser_to_match_id);
            $this->assertNotNull($sf->team_a_id);
        }
    }

    public function test_final_only_bracket_has_correct_structure(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Final Pool', 'method' => 'full', 'start_round' => 'FINAL']);
        $pool = Pool::first();

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->makeMatchups(1)]);

        $this->assertSame(2, $pool->teams()->count());
        $this->assertSame(0, $pool->matches()->where('round', 'SF')->count());
        $this->assertSame(0, $pool->matches()->where('round', 'THIRD')->count());
        $this->assertSame(1, $pool->matches()->where('round', 'FINAL')->count());

        $final = $pool->matches()->where('round', 'FINAL')->first();
        $this->assertNotNull($final->team_a_id);
        $this->assertNotNull($final->team_b_id);
    }

    public function test_wrong_matchup_count_is_rejected_for_r16_pool(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'R16 Pool', 'method' => 'full', 'start_round' => 'R16']);
        $pool = Pool::first();

        // Sending 16 matchups for an R16-start pool (expects 8) is rejected.
        $this->actingAs($manager)
            ->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()])
            ->assertSessionHasErrors('matchups');

        $this->assertSame(0, $pool->teams()->count());
    }

    public function test_is_ready_to_open_uses_start_round_team_count(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'QF Pool', 'method' => 'full', 'start_round' => 'QF']);
        $pool = Pool::first();
        $pool->update(['approved_at' => now(), 'deadline_utc' => now()->addDay()]);

        $this->assertFalse($pool->isReadyToOpen());

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->makeMatchups(4)]);

        $this->assertTrue($pool->fresh()->isReadyToOpen());
    }

    public function test_rounds_from_start_returns_correct_slice(): void
    {
        $pool = new Pool(['method' => 'full', 'start_round' => 'QF']);
        $this->assertSame(['QF', 'SF', 'THIRD', 'FINAL'], $pool->roundsFromStart());

        $pool->start_round = 'SF';
        $this->assertSame(['SF', 'THIRD', 'FINAL'], $pool->roundsFromStart());

        $pool->start_round = 'FINAL';
        $this->assertSame(['FINAL'], $pool->roundsFromStart());

        $pool->start_round = 'R32';
        $this->assertSame(Pool::ROUND_SEQUENCE, $pool->roundsFromStart());
    }
}
