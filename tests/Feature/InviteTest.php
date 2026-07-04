<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    private function createPoolAsManager(User $user): Pool
    {
        $this->actingAs($user)->post(route('pools.store'), ['name' => 'WC Pool']);

        return Pool::first();
    }

    public function test_manager_can_create_invites(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)->post(route('pools.invites.store', $pool), [
            'emails' => "alice@example.com\nbob@example.com",
        ]);

        $this->assertDatabaseCount('invites', 2);
        $this->assertDatabaseHas('invites', ['pool_id' => $pool->id, 'email' => 'alice@example.com', 'status' => 'pending']);
    }

    public function test_duplicate_and_invalid_emails_are_skipped(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->actingAs($manager)->post(route('pools.invites.store', $pool), [
            'emails' => 'alice@example.com, alice@example.com, not-an-email',
        ]);

        $this->assertDatabaseCount('invites', 1);
    }

    public function test_player_cannot_create_invites(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->post(route('pools.invites.store', $pool), ['emails' => 'x@example.com'])
            ->assertForbidden();

        $this->assertDatabaseCount('invites', 0);
    }

    public function test_guest_sees_join_page(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.invites.store', $pool), ['emails' => 'p@example.com']);
        $invite = Invite::first();

        // Fresh guest session.
        auth()->logout();
        $this->get(route('invite.show', $invite->token))
            ->assertOk()
            ->assertSee('Create account');
    }

    public function test_invalid_token_shows_invalid_page(): void
    {
        $this->get(route('invite.show', 'this-token-does-not-exist'))
            ->assertOk()
            ->assertSee('Invite not available');
    }

    public function test_authenticated_user_auto_joins_via_invite_link_as_player(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.invites.store', $pool), ['emails' => 'player@example.com']);
        $invite = Invite::first();

        // The invited person signs in with the invited email.
        $player = User::factory()->create(['email' => 'player@example.com']);

        $this->actingAs($player)
            ->get(route('invite.show', $invite->token))
            ->assertRedirect(route('pools.show', $pool));

        $this->assertDatabaseHas('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $player->id,
            'role' => 'player',
        ]);
    }

    public function test_invite_opened_in_a_different_account_does_not_auto_join(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.invites.store', $pool), ['emails' => 'player@example.com']);
        $invite = Invite::first();

        // Someone logged in with a DIFFERENT email opens the link.
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($other)
            ->get(route('invite.show', $invite->token))
            ->assertOk()
            ->assertSee('Join as'); // shown the choice page, not silently joined

        $this->assertDatabaseMissing('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_existing_manager_keeps_role_when_opening_invite_link(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.invites.store', $pool), ['emails' => 'someone@example.com']);
        $invite = Invite::first();

        // The manager opening the link must NOT be downgraded to player.
        $this->actingAs($manager)->get(route('invite.show', $invite->token));

        $this->assertDatabaseHas('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $manager->id,
            'role' => 'manager',
        ]);
    }

    public function test_authenticated_user_can_accept_invite_and_join(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $this->actingAs($manager)->post(route('pools.invites.store', $pool), ['emails' => 'player@example.com']);
        $invite = Invite::first();

        $player = User::factory()->create();

        $this->actingAs($player)
            ->post(route('invite.accept', $invite->token))
            ->assertRedirect(route('pools.show', $pool));

        $this->assertDatabaseHas('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $player->id,
            'role' => 'player',
        ]);

        $invite->refresh();
        $this->assertSame('accepted', $invite->status);
        $this->assertSame($player->id, $invite->accepted_by);
    }

    public function test_accepting_invalid_token_does_not_create_membership(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post(route('invite.accept', 'bogus-token'))
            ->assertRedirect(route('invite.show', 'bogus-token'));

        $this->assertDatabaseCount('pool_memberships', 0);
    }

    // --- Public join link (no email) ---

    public function test_pool_has_join_token_on_creation(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);

        $this->assertNotNull($pool->join_token);
        $this->assertSame(32, strlen($pool->join_token));
    }

    public function test_guest_sees_join_page_via_pool_link(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $pool->update(['approved_at' => now()]);

        auth()->logout();
        $this->get(route('pool.join', $pool->join_token))
            ->assertOk()
            ->assertSee('Create account');
    }

    public function test_authenticated_user_auto_joins_via_pool_link(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $pool->update(['approved_at' => now()]);

        $player = User::factory()->create();

        $this->actingAs($player)
            ->get(route('pool.join', $pool->join_token))
            ->assertRedirect(route('pools.show', $pool));

        $this->assertDatabaseHas('pool_memberships', [
            'pool_id' => $pool->id,
            'user_id' => $player->id,
            'role' => 'player',
        ]);
    }

    public function test_existing_member_is_not_duplicated_via_pool_link(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $pool->update(['approved_at' => now()]);

        $this->actingAs($manager)
            ->get(route('pool.join', $pool->join_token))
            ->assertRedirect(route('pools.show', $pool));

        $this->assertSame(1, $pool->memberships()->where('user_id', $manager->id)->count());
        $this->assertDatabaseHas('pool_memberships', ['user_id' => $manager->id, 'role' => 'manager']);
    }

    public function test_invalid_pool_link_shows_error_page(): void
    {
        $this->get(route('pool.join', 'invalid-token'))
            ->assertOk()
            ->assertSee('Link not available');
    }

    public function test_manager_can_regenerate_join_link(): void
    {
        $manager = User::factory()->create();
        $pool = $this->createPoolAsManager($manager);
        $oldToken = $pool->join_token;

        $this->actingAs($manager)
            ->post(route('pools.join-link.regenerate', $pool))
            ->assertRedirect();

        $newToken = $pool->fresh()->join_token;
        $this->assertNotEquals($oldToken, $newToken);
        $this->get(route('pool.join', $oldToken))->assertOk()->assertSee('Link not available');
    }
}
