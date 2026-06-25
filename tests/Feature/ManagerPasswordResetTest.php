<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createPoolAsManager(User $user): Pool
    {
        $this->actingAs($user)->post(route('pools.store'), ['name' => 'WC Pool']);

        return Pool::first();
    }

    public function test_manager_can_issue_a_temporary_password_for_a_member(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $response = $this->actingAs($manager)
            ->post(route('pools.members.reset-password', [$pool, $player->id]));

        $response->assertSessionHas('reset_password');
        $temp = session('reset_password')['password'];

        // The member can now sign in with the issued temporary password...
        $this->assertTrue(Hash::check($temp, $player->fresh()->password));
        // ...and is flagged to change it on next sign-in.
        $this->assertTrue($player->fresh()->must_change_password);
    }

    public function test_temporary_password_forces_a_change_before_using_the_app(): void
    {
        $player = User::factory()->create();
        $player->forceFill(['must_change_password' => true])->save();

        $this->actingAs($player)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change.show'));
    }

    public function test_setting_a_new_password_clears_the_flag(): void
    {
        $player = User::factory()->create();
        $player->forceFill(['must_change_password' => true])->save();

        $this->actingAs($player)
            ->post(route('password.change.update'), [
                'password' => 'BrandNewPass123',
                'password_confirmation' => 'BrandNewPass123',
            ])
            ->assertRedirect(route('dashboard'));

        $fresh = $player->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('BrandNewPass123', $fresh->password));
    }

    public function test_player_cannot_reset_passwords(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);
        $other = User::factory()->create();
        $pool->memberships()->create(['user_id' => $other->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->post(route('pools.members.reset-password', [$pool, $other->id]))
            ->assertForbidden();
    }

    public function test_cannot_reset_password_of_non_member(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $stranger = User::factory()->create();

        $this->actingAs($manager)
            ->post(route('pools.members.reset-password', [$pool, $stranger->id]))
            ->assertNotFound();
    }
}
