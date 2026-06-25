<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Services\PickResolver;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsTest extends TestCase
{
    use RefreshDatabase;

    private function makePool(array $tiebreakers, ?int $finalScoreA = null, ?int $finalScoreB = null): Pool
    {
        $owner = User::factory()->create();
        $pool = Pool::create([
            'name' => 'Standings Pool',
            'status' => 'complete',
            'timezone' => 'America/Mexico_City',
            'tiebreaker_order' => $tiebreakers,
            'created_by' => $owner->id,
        ]);
        $pool->matches()->create([
            'round' => 'FINAL',
            'position' => 1,
            'final_actual_score_a' => $finalScoreA,
            'final_actual_score_b' => $finalScoreB,
        ]);

        return $pool;
    }

    private function addMember(Pool $pool, array $attrs): User
    {
        $user = User::factory()->create();
        $pool->memberships()->create(array_merge([
            'user_id' => $user->id,
            'role' => 'player',
            'joined_at' => now(),
        ], $attrs));

        return $user;
    }

    public function test_standings_page_renders_with_champion_banner(): void
    {
        $pool = $this->makePool(['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'], finalScoreA: 2, finalScoreB: 1);
        $champ = $pool->teams()->create(['name' => 'Brazil', 'country_code' => 'br']);
        $pool->matches()->where('round', 'FINAL')->first()->update(['actual_winner_team_id' => $champ->id]);

        $viewer = $this->addMember($pool, ['score' => 10]);

        $this->actingAs($viewer)
            ->get(route('pools.standings', $pool))
            ->assertOk()
            ->assertSee('Champion')
            ->assertSee('Brazil');
    }

    public function test_higher_score_ranks_first(): void
    {
        $pool = $this->makePool(['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission']);
        $low = $this->addMember($pool, ['score' => 5]);
        $high = $this->addMember($pool, ['score' => 40]);
        $mid = $this->addMember($pool, ['score' => 20]);

        $ranked = (new StandingsService)->rank($pool);

        $this->assertSame($high->id, $ranked[0]['membership']->user_id);
        $this->assertSame($mid->id, $ranked[1]['membership']->user_id);
        $this->assertSame($low->id, $ranked[2]['membership']->user_id);
        $this->assertSame([1, 2, 3], array_column($ranked, 'rank'));
    }

    public function test_most_correct_breaks_ties_when_scores_equal(): void
    {
        $pool = $this->makePool(['most_correct', 'final_goals_closest', 'earliest_submission']);
        $a = $this->addMember($pool, ['score' => 10, 'correct_picks' => 5]);
        $b = $this->addMember($pool, ['score' => 10, 'correct_picks' => 8]);

        $ranked = (new StandingsService)->rank($pool);

        $this->assertSame($b->id, $ranked[0]['membership']->user_id);
        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame($a->id, $ranked[1]['membership']->user_id);
        $this->assertSame(2, $ranked[1]['rank']);
    }

    public function test_exact_score_match_breaks_ties_first(): void
    {
        // Actual Final score 2–1.
        $pool = $this->makePool(['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'], finalScoreA: 2, finalScoreB: 1);
        $a = $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 2, 'final_score_b' => 1]); // exact
        $b = $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 3, 'final_score_b' => 1]); // not exact

        $ranked = (new StandingsService)->rank($pool);

        $this->assertSame($a->id, $ranked[0]['membership']->user_id);
        $this->assertSame($b->id, $ranked[1]['membership']->user_id);
    }

    public function test_closest_final_goals_breaks_ties(): void
    {
        // Actual Final score 2–1 (total 3).
        $pool = $this->makePool(['final_goals_closest', 'most_correct', 'earliest_submission'], finalScoreA: 2, finalScoreB: 1);
        $a = $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 3, 'final_score_b' => 2]); // total 5, dist 2
        $b = $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 1, 'final_score_b' => 1]); // total 2, dist 1

        $ranked = (new StandingsService)->rank($pool);

        $this->assertSame($b->id, $ranked[0]['membership']->user_id);
        $this->assertSame($a->id, $ranked[1]['membership']->user_id);
    }

    public function test_tied_members_share_rank(): void
    {
        $pool = $this->makePool(['most_correct', 'final_goals_closest', 'earliest_submission'], finalScoreA: 2, finalScoreB: 1);
        $t = now()->subHour();
        $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 2, 'final_score_b' => 1, 'picks_submitted_at' => $t]);
        $this->addMember($pool, ['score' => 10, 'correct_picks' => 5, 'final_score_a' => 2, 'final_score_b' => 1, 'picks_submitted_at' => $t]);
        $this->addMember($pool, ['score' => 5, 'correct_picks' => 2]);

        $ranked = (new StandingsService)->rank($pool);

        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame(1, $ranked[1]['rank']);
        $this->assertSame(3, $ranked[2]['rank']);
    }

    // --- Pick visibility ---

    /** @return array<int, array{a: string, b: string}> */
    private function sampleMatchups(): array
    {
        $matchups = [];
        for ($i = 0; $i < 16; $i++) {
            $matchups[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $matchups;
    }

    private function openPoolWithBracket(User $manager): Pool
    {
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::where('name', 'WC Pool')->first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['deadline_utc' => now()->addDays(2), 'status' => 'open']);

        return $pool->fresh();
    }

    private function addPlayerWithPicks(Pool $pool): User
    {
        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $matches = $pool->matches()->get();
        $resolver = new PickResolver($matches);
        $picks = [];
        foreach (['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'] as $round) {
            foreach ($matches->where('round', $round) as $m) {
                [$a] = $resolver->participants($m->id, $picks);
                $picks[$m->id] = $a;
            }
        }
        $this->actingAs($player)->put(route('pools.picks.update', $pool), ['picks' => $picks, 'final_score_a' => 1, 'final_score_b' => 1]);

        return $player;
    }

    public function test_player_can_view_their_own_picks(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayerWithPicks($pool);

        $this->actingAs($player)
            ->get(route('pools.picks.show', [$pool, $player->id]))
            ->assertOk();
    }

    public function test_player_cannot_view_others_picks_before_reveal(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $p1 = $this->addPlayerWithPicks($pool);
        $p2 = $this->addPlayerWithPicks($pool);

        $this->actingAs($p1)
            ->get(route('pools.picks.show', [$pool, $p2->id]))
            ->assertForbidden();
    }

    public function test_others_picks_visible_after_pool_closed(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $p1 = $this->addPlayerWithPicks($pool);
        $p2 = $this->addPlayerWithPicks($pool);

        $pool->update(['status' => 'locked']); // manager closed picks -> revealed

        $this->actingAs($p1)
            ->get(route('pools.picks.show', [$pool, $p2->id]))
            ->assertOk();
    }

    public function test_manager_can_view_picks_before_reveal(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $p2 = $this->addPlayerWithPicks($pool);

        $this->actingAs($manager)
            ->get(route('pools.picks.show', [$pool, $p2->id]))
            ->assertOk();
    }
}
