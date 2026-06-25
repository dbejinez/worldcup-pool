<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    protected $fillable = [
        'pool_id',
        'email',
        'token',
        'status',
        'invited_by',
        'accepted_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
