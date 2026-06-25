<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringConfig extends Model
{
    protected $fillable = [
        'pool_id',
        'pts_r32',
        'pts_r16',
        'pts_qf',
        'pts_sf',
        'pts_third',
        'pts_final',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /**
     * Points for a given round enum value (R32, R16, QF, SF, THIRD, FINAL).
     */
    public function pointsForRound(string $round): int
    {
        return (int) ($this->{'pts_' . strtolower($round)} ?? 0);
    }
}
