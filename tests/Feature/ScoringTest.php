<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Services\PickResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringTest extends TestCase
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

    private function openPoolWithBracket(User $manager): Pool
    {
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['deadline_utc' => now()->addDays(2), 'status' => 'open']);

        return $pool->fresh();
    }

    /** @return array<int, int> */
    private function consistentPicks(Pool $pool): array
    {
        $matches = $pool->matches()->get();
        $resolver = new PickResolver($matches);
        $picks = [];
        foreach (['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'] as $round) {
            foreach ($matches->where('round', $round) as $m) {
                [$a] = $resolver->participants($m->id, $picks);
                $picks[$m->id] = $a;
            }
        }

        return $picks;
    }

    private function addPlayerWithPicks(Pool $pool): User
    {
        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);
        $this->actingAs($player)->put(route('pools.picks.update', $pool), [
            'picks' => $this->consistentPicks($pool),
            'final_score_a' => 2,
            'final_score_b' => 1,
        ]);

        return $player;
    }

    /** Enter a round's results, always choosing the slot-A team as winner. */
    private function enterRound(User $manager, Pool $pool, string $round, ?int $scoreA = null, ?int $scoreB = null): void
    {
        $winners = [];
        foreach ($pool->matches()->where('round', $round)->get() as $m) {
            if ($m->team_a_id) {
                $winners[$m->id] = $m->team_a_id;
            }
        }
        $payload = ['winners' => $winners];
        if ($scoreA !== null) {
            $payload['final_score_a'] = $scoreA;
            $payload['final_score_b'] = $scoreB;
        }
        $this->actingAs($manager)->put(route('pools.results.update', $pool), $payload);
    }

    private function score(Pool $pool, User $user): int
    {
        return (int) $pool->memberships()->where('user_id', $user->id)->value('score');
    }

    public function test_r32_results_score_matching_picks_and_propagate(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayerWithPicks($pool);

        $this->enterRound($manager, $pool, 'R32');

        // 16 correct R32 picks at 1 pt each.
        $membership = $pool->memberships()->where('user_id', $player->id)->first();
        $this->assertSame(16, $membership->score);
        $this->assertSame(16, $membership->correct_picks);

        // R16 participants have been propagated from the R32 winners.
        $r16 = $pool->matches()->where('round', 'R16')->where('position', 1)->first();
        $this->assertNotNull($r16->team_a_id);
        $this->assertNotNull($r16->team_b_id);
    }

    public function test_full_correct_results_award_full_score(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayerWithPicks($pool);

        foreach (['R32', 'R16', 'QF', 'SF', 'THIRD'] as $round) {
            $this->enterRound($manager, $pool, $round);
        }
        $this->enterRound($manager, $pool, 'FINAL', scoreA: 2, scoreB: 1);

        // 16*1 + 8*2 + 4*4 + 2*8 + 1*4 (third) + 1*16 (final) = 84
        $membership = $pool->memberships()->where('user_id', $player->id)->first();
        $this->assertSame(84, $membership->score);
        $this->assertSame(32, $membership->correct_picks);
        $this->assertSame('complete', $pool->fresh()->status);
    }

    public function test_correcting_a_result_updates_score_and_logs_audit(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayerWithPicks($pool);

        $this->enterRound($manager, $pool, 'R32');
        $this->assertSame(16, $this->score($pool, $player));

        // Correct match #1: the other team actually won (player had picked team A).
        $m1 = $pool->matches()->where('round', 'R32')->where('position', 1)->first();
        $this->actingAs($manager)->put(route('pools.results.update', $pool), [
            'winners' => [$m1->id => $m1->team_b_id],
        ]);

        // That pick is now wrong: 15 correct.
        $this->assertSame(15, $this->score($pool, $player));

        $this->assertDatabaseHas('result_audits', [
            'pool_id' => $pool->id,
            'bracket_match_id' => $m1->id,
            'new_winner_team_id' => $m1->team_b_id,
        ]);
    }

    public function test_final_result_score_must_match_winner(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        foreach (['R32', 'R16', 'QF', 'SF'] as $round) {
            $this->enterRound($manager, $pool, $round);
        }

        // The Final now has participants. Record winner = team A but with a losing score.
        $final = $pool->matches()->where('round', 'FINAL')->first();
        $this->actingAs($manager)
            ->put(route('pools.results.update', $pool), [
                'winners' => [$final->id => $final->team_a_id],
                'final_score_a' => 1,
                'final_score_b' => 2,
            ])
            ->assertSessionHasErrors('final_score');

        // Nothing saved for the Final.
        $this->assertNull($pool->matches()->where('round', 'FINAL')->value('actual_winner_team_id'));
        $this->assertNotSame('complete', $pool->fresh()->status);
    }

    public function test_player_cannot_enter_results(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayerWithPicks($pool);

        $this->actingAs($player)
            ->put(route('pools.results.update', $pool), ['winners' => []])
            ->assertForbidden();
    }

    public function test_non_r32_start_pool_results_do_not_wipe_seeded_teams(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'QF Pool', 'method' => 'full', 'start_round' => 'QF']);
        $pool = Pool::first();

        $matchups = [];
        for ($i = 0; $i < 4; $i++) {
            $matchups[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $matchups]);
        $pool->update(['deadline_utc' => now()->addDays(2), 'status' => 'open']);
        $pool = $pool->fresh();

        // Record QF results.
        $this->enterRound($manager, $pool, 'QF');

        // Seeded QF teams must still be present after propagation.
        foreach ($pool->matches()->where('round', 'QF')->get() as $qf) {
            $this->assertNotNull($qf->fresh()->team_a_id, "QF match {$qf->id} team_a_id was wiped");
            $this->assertNotNull($qf->fresh()->team_b_id, "QF match {$qf->id} team_b_id was wiped");
        }

        // Winners should have propagated into the SF matches.
        foreach ($pool->matches()->where('round', 'SF')->get() as $sf) {
            $this->assertNotNull($sf->fresh()->team_a_id, "SF match {$sf->id} team_a_id was not propagated");
        }

        // Results page should be accessible (not 404).
        $this->actingAs($manager)
            ->get(route('pools.results.edit', $pool))
            ->assertOk();
    }

    public function test_sf_start_pool_results_do_not_wipe_seeded_teams(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'SF Pool', 'method' => 'full', 'start_round' => 'SF']);
        $pool = Pool::first();

        $matchups = [
            ['a' => 'Brazil', 'b' => 'France'],
            ['a' => 'Germany', 'b' => 'Argentina'],
        ];
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $matchups]);
        $pool->update(['deadline_utc' => now()->addDays(2), 'status' => 'open']);
        $pool = $pool->fresh();

        // Record SF results.
        $this->enterRound($manager, $pool, 'SF');

        // SF teams must still be seeded after propagation.
        foreach ($pool->matches()->where('round', 'SF')->get() as $sf) {
            $this->assertNotNull($sf->fresh()->team_a_id, "SF team_a_id was wiped");
            $this->assertNotNull($sf->fresh()->team_b_id, "SF team_b_id was wiped");
        }

        // FINAL and THIRD should now have participants.
        $final = $pool->matches()->where('round', 'FINAL')->first()->fresh();
        $third = $pool->matches()->where('round', 'THIRD')->first()->fresh();
        $this->assertNotNull($final->team_a_id);
        $this->assertNotNull($final->team_b_id);
        $this->assertNotNull($third->team_a_id);
        $this->assertNotNull($third->team_b_id);
    }
}
