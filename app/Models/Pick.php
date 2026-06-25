<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pick extends Model
{
    protected $fillable = [
        'pool_id',
        'user_id',
        'bracket_match_id',
        'predicted_winner_team_id',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(BracketMatch::class, 'bracket_match_id');
    }

    public function predictedWinner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'predicted_winner_team_id');
    }
}
