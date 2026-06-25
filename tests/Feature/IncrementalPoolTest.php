<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncrementalPoolTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{a: string, b: string}> */
    private function sampleMatchups(): array
    {
        $m = [];
        for ($i = 0; $i < 16; $i++) {
            $m[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $m;
    }

    private function openIncrementalPool(User $manager): Pool
    {
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Inc Pool', 'method' => 'incremental']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        // approve + open (incremental needs no deadline)
        $pool->update(['approved_at' => now(), 'status' => 'open']);

        return $pool->fresh();
    }

    private function addPlayer(Pool $pool, string $email): User
    {
        $player = User::factory()->create(['email' => $email]);
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        return $player;
    }

    /** winners[matchId] = the slot-A team, for one round. */
    private function roundWinners(Pool $pool, string $round): array
    {
        $winners = [];
        foreach ($pool->matches()->where('round', $round)->get() as $m) {
            if ($m->team_a_id) {
                $winners[$m->id] = $m->team_a_id;
            }
        }

        return $winners;
    }

    private function enterResults(User $manager, Pool $pool, string $round): void
    {
        $payload = ['winners' => $this->roundWinners($pool, $round)];
        if ($round === 'FINAL') {
            $payload['final_score_a'] = 2;
            $payload['final_score_b'] = 1;
        }
        $this->actingAs($manager)->put(route('pools.results.update', $pool), $payload);
    }

    public function test_pool_is_created_with_incremental_method(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Inc', 'method' => 'incremental']);

        $this->assertSame('incremental', Pool::first()->method);
    }

    public function test_incremental_can_open_without_a_deadline(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Inc', 'method' => 'incremental']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['approved_at' => now()]); // no deadline set

        $this->actingAs($manager)->post(route('pools.open', $pool));

        $this->assertSame('open', $pool->fresh()->status);
    }

    public function test_pick_sheet_renders_before_pool_opens(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Inc', 'method' => 'incremental']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        // Pool still in 'setup' (not opened) — every round is "upcoming".
        $player = $this->addPlayer($pool, 'p@example.com');

        $this->actingAs($player)->get(route('pools.picks.edit', $pool))->assertOk();
    }

    public function test_only_r32_is_open_at_first(): void
    {
        $pool = $this->openIncrementalPool(User::factory()->create());

        $this->assertTrue($pool->roundPicksOpen('R32'));
        $this->assertFalse($pool->roundPicksOpen('R16'));
    }

    public function test_player_can_pick_open_round_but_not_a_future_round(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $player = $this->addPlayer($pool, 'p@example.com');

        // R32 is open.
        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R32',
            'winners' => $this->roundWinners($pool, 'R32'),
        ])->assertSessionHas('status');
        $this->assertSame(16, $pool->picks()->where('user_id', $player->id)->count());

        // R16 isn't open yet (send a non-empty payload so it reaches the round gate).
        $r16 = $pool->matches()->where('round', 'R16')->first();
        $someTeam = $pool->teams()->value('id');
        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R16',
            'winners' => [$r16->id => $someTeam],
        ])->assertForbidden();
    }

    public function test_next_round_opens_after_previous_results(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $player = $this->addPlayer($pool, 'p@example.com');

        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R32', 'winners' => $this->roundWinners($pool, 'R32'),
        ]);

        // Manager enters all R32 results -> R16 opens.
        $this->enterResults($manager, $pool, 'R32');
        $pool->refresh();

        $this->assertTrue($pool->roundComplete('R32'));
        $this->assertTrue($pool->roundPicksOpen('R16'));

        // Player can now pick R16 (real advancing teams).
        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R16', 'winners' => $this->roundWinners($pool, 'R16'),
        ])->assertSessionHas('status');
        $this->assertSame(8, $pool->picks()->where('user_id', $player->id)->where('bracket_match_id', '!=', null)
            ->whereIn('bracket_match_id', $pool->matches()->where('round', 'R16')->pluck('id'))->count());
    }

    public function test_manager_can_lock_a_round_and_picks_close(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $player = $this->addPlayer($pool, 'p@example.com');

        $this->actingAs($manager)->post(route('pools.rounds.lock', [$pool, 'R32']));
        $this->assertTrue($pool->fresh()->roundLocked('R32'));

        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R32', 'winners' => $this->roundWinners($pool, 'R32'),
        ])->assertForbidden();
    }

    public function test_round_picks_hidden_until_locked_then_revealed(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $p1 = $this->addPlayer($pool, 'p1@example.com');
        $p2 = $this->addPlayer($pool, 'p2@example.com');

        $this->actingAs($p2)->post(route('pools.picks.round', $pool), [
            'round' => 'R32', 'winners' => $this->roundWinners($pool, 'R32'),
        ]);

        // Before lock: p1 cannot see p2's R32 picks.
        $this->actingAs($p1)->get(route('pools.picks.show', [$pool, $p2->id]))
            ->assertOk()->assertDontSee('Team 1');

        // After lock: revealed.
        $this->actingAs($manager)->post(route('pools.rounds.lock', [$pool, 'R32']));
        $this->actingAs($p1)->get(route('pools.picks.show', [$pool, $p2->id]))
            ->assertOk()->assertSee('Team 1');
    }

    public function test_finished_round_picks_still_show_after_pool_completes(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $player = $this->addPlayer($pool, 'p@example.com');

        // Player picks R32; manager records all R32 results.
        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R32', 'winners' => $this->roundWinners($pool, 'R32'),
        ]);
        $this->enterResults($manager, $pool, 'R32');

        // Simulate the tournament finishing (status -> complete).
        $pool->update(['status' => 'complete']);

        // The player can still see their finished R32 picks (e.g. "Team 1").
        $this->actingAs($player)->get(route('pools.picks.edit', $pool))
            ->assertOk()
            ->assertSee('Team 1');
    }

    public function test_scoring_works_for_incremental(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openIncrementalPool($manager);
        $player = $this->addPlayer($pool, 'p@example.com');

        $this->actingAs($player)->post(route('pools.picks.round', $pool), [
            'round' => 'R32', 'winners' => $this->roundWinners($pool, 'R32'),
        ]);
        $this->enterResults($manager, $pool, 'R32');

        $membership = $pool->memberships()->where('user_id', $player->id)->first();
        $this->assertSame(16, $membership->score);       // 16 correct R32 at 1 pt
        $this->assertSame(16, $membership->correct_picks);
    }
}
