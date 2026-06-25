<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolMembership extends Model
{
    protected $fillable = [
        'pool_id',
        'user_id',
        'role',
        'score',
        'correct_picks',
        'final_score_a',
        'final_score_b',
        'picks_submitted_at',
        'joined_at',
    ];

    protected $casts = [
        'picks_submitted_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
}
