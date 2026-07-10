<?php

namespace App\Policies;

use App\Models\Pool;
use App\Models\User;

class PoolPolicy
{
    /** Admins bypass all pool-level checks. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /**
     * Any member (manager or player) may view the pool.
     */
    public function view(User $user, Pool $pool): bool
    {
        return $pool->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * Only managers may manage (configure / load / enter results).
     */
    public function manage(User $user, Pool $pool): bool
    {
        return $pool->memberships()
            ->where('user_id', $user->id)
            ->where('role', 'manager')
            ->exists();
    }
}
