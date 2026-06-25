<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createPoolAsManager(User $user): Pool
    {
        $this->actingAs($user)->post(route('pools.store'), ['name' => 'WC Pool']);

        return Pool::first();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Renamed Pool',
            'pts_r32' => 2,
            'pts_r16' => 3,
            'pts_qf' => 5,
            'pts_sf' => 9,
            'pts_third' => 5,
            'pts_final' => 20,
            'tiebreakers' => ['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'],
            'deadline_local' => '2026-06-20T18:00',
        ], $overrides);
    }

    public function test_manager_can_update_settings_and_deadline_is_stored_in_utc(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)
            ->patch(route('pools.settings.update', $pool), $this->validPayload())
            ->assertRedirect(route('pools.settings', $pool));

        $pool->refresh();
        $this->assertSame('Renamed Pool', $pool->name);
        $this->assertSame(['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'], $pool->tiebreaker_order);

        // 18:00 in America/Mexico_City (UTC-6, no DST) -> 00:00 UTC the next day.
        $this->assertSame('2026-06-21 00:00:00', $pool->deadline_utc->toDateTimeString());

        $this->assertSame(20, $pool->scoringConfig->pts_final);
        $this->assertSame(2, $pool->scoringConfig->pts_r32);

        // Saving the form marks the settings step done.
        $this->assertNotNull($pool->settings_saved_at);
    }

    public function test_settings_step_is_not_done_until_saved(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        // A freshly created pool hasn't had its settings saved yet.
        $this->assertNull($pool->settings_saved_at);
    }

    public function test_tiebreakers_must_be_distinct(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)
            ->patch(route('pools.settings.update', $pool), $this->validPayload([
                'tiebreakers' => ['exact_score', 'exact_score', 'most_correct', 'earliest_submission'],
            ]))
            ->assertSessionHasErrors();

        // Unchanged from the default order.
        $this->assertSame(
            ['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'],
            $pool->refresh()->tiebreaker_order
        );
    }

    public function test_negative_points_are_rejected(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)
            ->patch(route('pools.settings.update', $pool), $this->validPayload(['pts_r32' => -1]))
            ->assertSessionHasErrors('pts_r32');
    }

    public function test_player_cannot_update_settings(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->patch(route('pools.settings.update', $pool), $this->validPayload())
            ->assertForbidden();
    }
}
