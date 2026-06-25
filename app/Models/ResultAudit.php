<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultAudit extends Model
{
    protected $fillable = [
        'pool_id',
        'bracket_match_id',
        'old_winner_team_id',
        'new_winner_team_id',
        'old_total_goals',
        'new_total_goals',
        'changed_by',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(BracketMatch::class, 'bracket_match_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
