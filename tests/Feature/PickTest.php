<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Services\PickResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickTest extends TestCase
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

    private function addPlayer(Pool $pool): User
    {
        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        return $player;
    }

    /**
     * Build a consistent, complete bracket by always advancing the slot-A team.
     *
     * @return array<int, int>
     */
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

    public function test_player_can_submit_a_complete_bracket(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $picks = $this->consistentPicks($pool);
        $this->assertCount(32, $picks);

        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $picks, 'final_score_a' => 2, 'final_score_b' => 1])
            ->assertRedirect(route('pools.show', $pool));

        $this->assertSame(32, $pool->picks()->where('user_id', $player->id)->count());

        $membership = $pool->memberships()->where('user_id', $player->id)->first();
        $this->assertSame(2, $membership->final_score_a);
        $this->assertSame(1, $membership->final_score_b);
        $this->assertNotNull($membership->picks_submitted_at);
    }

    public function test_full_pick_sheet_renders_with_bracket(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $this->actingAs($player)->get(route('pools.picks.edit', $pool))->assertOk();
    }

    public function test_incomplete_bracket_is_rejected(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $picks = $this->consistentPicks($pool);
        // Drop the Final pick.
        $finalId = $pool->matches()->where('round', 'FINAL')->value('id');
        unset($picks[$finalId]);

        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $picks, 'final_score_a' => 2, 'final_score_b' => 1])
            ->assertSessionHasErrors('picks');

        $this->assertSame(0, $pool->picks()->where('user_id', $player->id)->count());
    }

    public function test_final_score_must_match_champion(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        // consistentPicks makes the slot-A finalist the champion; give them FEWER goals.
        $picks = $this->consistentPicks($pool);

        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $picks, 'final_score_a' => 1, 'final_score_b' => 3])
            ->assertSessionHasErrors('final_score');

        $this->assertSame(0, $pool->picks()->where('user_id', $player->id)->count());
    }

    public function test_inconsistent_pick_is_rejected(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $picks = $this->consistentPicks($pool);
        // Set the champion to a team that lost in the Round of 32 (impossible).
        $r32 = $pool->matches()->where('round', 'R32')->where('position', 1)->first();
        $finalId = $pool->matches()->where('round', 'FINAL')->value('id');
        $picks[$finalId] = $r32->team_b_id; // slot-B team lost round 1 in our consistent picks

        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $picks, 'final_score_a' => 2, 'final_score_b' => 1])
            ->assertSessionHasErrors('picks');

        $this->assertSame(0, $pool->picks()->where('user_id', $player->id)->count());
    }

    public function test_picks_blocked_when_pool_not_open(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        // Pool left in 'setup' (not open).
        $player = $this->addPlayer($pool);

        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $this->consistentPicks($pool), 'final_score_a' => 2, 'final_score_b' => 1])
            ->assertForbidden();
    }

    public function test_manager_can_close_picks_to_lock_changes(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $this->actingAs($manager)
            ->post(route('pools.close', $pool))
            ->assertRedirect(route('pools.show', $pool));

        $this->assertSame('locked', $pool->fresh()->status);

        // Players can no longer submit once closed.
        $this->actingAs($player)
            ->put(route('pools.picks.update', $pool), ['picks' => $this->consistentPicks($pool), 'final_score_a' => 2, 'final_score_b' => 1])
            ->assertForbidden();
    }

    public function test_manager_can_reopen_a_closed_pool(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $pool->update(['status' => 'locked']);

        $this->actingAs($manager)->post(route('pools.reopen', $pool));

        $this->assertSame('open', $pool->fresh()->status);
    }

    public function test_player_cannot_close_pool(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->addPlayer($pool);

        $this->actingAs($player)
            ->post(route('pools.close', $pool))
            ->assertForbidden();

        $this->assertSame('open', $pool->fresh()->status);
    }

    public function test_open_requires_teams_and_deadline(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();
        $pool->update(['approved_at' => now()]); // isolate the teams/deadline check from approval
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);

        // No deadline yet -> cannot open.
        $this->actingAs($manager)->post(route('pools.open', $pool));
        $this->assertSame('setup', $pool->fresh()->status);

        // With a deadline -> opens.
        $pool->update(['deadline_utc' => now()->addDays(2)]);
        $this->actingAs($manager)->post(route('pools.open', $pool));
        $this->assertSame('open', $pool->fresh()->status);
    }
}
