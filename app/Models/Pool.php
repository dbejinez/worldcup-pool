<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pool extends Model
{
    /**
     * Available tie-breakers (key => human label), applied in the manager's chosen order.
     */
    public const TIEBREAKERS = [
        'exact_score' => 'Exact Final score match',
        'final_goals_closest' => 'Closest total goals in the Final',
        'most_correct' => 'Most correct match picks',
        'earliest_submission' => 'Earliest submission time',
    ];

    protected $fillable = [
        'name',
        'method',
        'status',
        'deadline_utc',
        'timezone',
        'tiebreaker_order',
        'settings_saved_at',
        'locked_rounds',
        'created_by',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'deadline_utc' => 'datetime',
        'approved_at' => 'datetime',
        'settings_saved_at' => 'datetime',
        'tiebreaker_order' => 'array',
        'locked_rounds' => 'array',
    ];

    /** Knockout rounds in order, and each round's feeder (previous) round. */
    public const ROUND_SEQUENCE = ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

    public const ROUND_FEEDER = [
        'R16' => 'R32',
        'QF' => 'R16',
        'SF' => 'QF',
        'THIRD' => 'SF',
        'FINAL' => 'SF',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scoringConfig(): HasOne
    {
        return $this->hasOne(ScoringConfig::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(PoolMembership::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->status === 'complete';
    }

    /**
     * Whether an admin has approved this pool's creation.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Players may submit/edit picks only while the pool is open. The deadline is
     * informational; the manager explicitly closes picks (open -> locked).
     */
    public function picksOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Picks become visible to other players once the manager closes the pool.
     */
    public function picksRevealed(): bool
    {
        return in_array($this->status, ['locked', 'complete'], true);
    }

    /**
     * A pool can be opened for picks once 32 teams are loaded (full pools also
     * require a deadline; incremental pools lock per round so a deadline is optional).
     */
    public function isReadyToOpen(): bool
    {
        return $this->status === 'setup'
            && $this->teams()->count() === 32
            && ($this->isIncremental() || $this->deadline_utc !== null);
    }

    public function isIncremental(): bool
    {
        return $this->method === 'incremental';
    }

    // ---- Incremental per-round state ----

    /** All matches of a round have an actual winner. */
    public function roundComplete(string $round): bool
    {
        $total = $this->matches()->where('round', $round)->count();

        return $total > 0
            && $this->matches()->where('round', $round)->whereNotNull('actual_winner_team_id')->count() === $total;
    }

    /** A round is reachable when the pool is open and its feeder round is complete (R32 needs only an open pool). */
    public function roundReachable(string $round): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if ($round === 'R32') {
            return true;
        }

        return $this->roundComplete(self::ROUND_FEEDER[$round]);
    }

    public function roundLocked(string $round): bool
    {
        return in_array($round, $this->locked_rounds ?? [], true);
    }

    /** Players may pick a round when it's reachable, not locked, and not already finished. */
    public function roundPicksOpen(string $round): bool
    {
        return $this->isIncremental()
            && $this->roundReachable($round)
            && ! $this->roundLocked($round)
            && ! $this->roundComplete($round);
    }

    /** A round's picks are revealed to others once it's locked or finished. */
    public function roundRevealed(string $round): bool
    {
        return $this->roundLocked($round) || $this->roundComplete($round);
    }
}
