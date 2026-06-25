<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    protected $fillable = [
        'pool_id',
        'name',
        'short_code',
        'country_code',
        'aliases',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }
}
