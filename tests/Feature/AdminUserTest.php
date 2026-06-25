<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_admin_can_view_users_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_admin_can_reset_any_users_password_even_without_a_pool(): void
    {
        $admin = $this->admin();
        $orphan = User::factory()->create(); // belongs to no pool

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $orphan))
            ->assertSessionHas('reset_password');

        $temp = session('reset_password')['password'];
        $fresh = $orphan->fresh();

        $this->assertTrue(Hash::check($temp, $fresh->password));
        $this->assertTrue($fresh->must_change_password);
    }

    public function test_non_admin_cannot_access_admin_area(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.reset-password', $other))->assertForbidden();
    }
}
