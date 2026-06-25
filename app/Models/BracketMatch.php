<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BracketMatch extends Model
{
    // 'match' is a reserved word in PHP 8 / MySQL, so the model is BracketMatch.
    protected $table = 'bracket_matches';

    protected $fillable = [
        'pool_id',
        'round',
        'position',
        'team_a_id',
        'team_b_id',
        'winner_to_match_id',
        'winner_to_slot',
        'loser_to_match_id',
        'loser_to_slot',
        'actual_winner_team_id',
        'final_actual_score_a',
        'final_actual_score_b',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    public function actualWinner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'actual_winner_team_id');
    }

    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    public function isFinal(): bool
    {
        return $this->round === 'FINAL';
    }
}
