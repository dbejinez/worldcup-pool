<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolApprovalTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_non_admin_pool_starts_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('pools.store'), ['name' => 'WC Pool']);

        $this->assertNull(Pool::first()->approved_at);
    }

    public function test_admin_pool_is_auto_approved(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('pools.store'), ['name' => 'Admin Pool']);

        $this->assertNotNull(Pool::first()->approved_at);
    }

    public function test_pending_pool_cannot_be_opened(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['deadline_utc' => now()->addDays(2)]);

        $this->actingAs($manager)->post(route('pools.open', $pool));

        $this->assertSame('setup', $pool->fresh()->status); // still not open
    }

    public function test_admin_approval_lets_the_pool_open(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['deadline_utc' => now()->addDays(2)]);

        // Admin approves.
        $this->actingAs($this->admin())->post(route('admin.pools.approve', $pool));
        $this->assertNotNull($pool->fresh()->approved_at);

        // Now the manager can open it.
        $this->actingAs($manager)->post(route('pools.open', $pool));
        $this->assertSame('open', $pool->fresh()->status);
    }

    public function test_admin_can_reject_pending_pool(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();

        $this->actingAs($this->admin())->delete(route('admin.pools.reject', $pool));

        $this->assertDatabaseMissing('pools', ['id' => $pool->id]);
    }

    public function test_non_admin_cannot_access_approvals(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::first();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('admin.pools.index'))->assertForbidden();
        $this->actingAs($other)->post(route('admin.pools.approve', $pool))->assertForbidden();
    }
}
