<?php

namespace App\Services;

use App\Models\Pool;

/**
 * Ranks pool members by score, then by the manager's ordered tie-breakers:
 *  - final_goals_closest: closest predicted total goals in the Final
 *  - most_correct:        most correct match picks
 *  - earliest_submission: earliest pick submission time
 */
class StandingsService
{
    /**
     * @return array<int, array{rank: int, membership: \App\Models\PoolMembership}>
     */
    public function rank(Pool $pool): array
    {
        $finalMatch = $pool->matches()->where('round', 'FINAL')->first();
        $aGoals = $finalMatch?->final_actual_score_a;
        $bGoals = $finalMatch?->final_actual_score_b;
        $actualKnown = $finalMatch && $aGoals !== null && $bGoals !== null;
        $actualTotal = $actualKnown ? $aGoals + $bGoals : null;
        $actualHigh = $actualKnown ? max($aGoals, $bGoals) : null;
        $actualLow = $actualKnown ? min($aGoals, $bGoals) : null;

        $order = $pool->tiebreaker_order
            ?: ['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'];

        $members = $pool->memberships()->with('user')->get()->all();

        $compare = function ($a, $b) use ($order, $actualKnown, $actualTotal, $actualHigh, $actualLow): int {
            if ($a->score !== $b->score) {
                return $b->score <=> $a->score; // higher score first
            }

            foreach ($order as $key) {
                if ($key === 'exact_score') {
                    if ($actualKnown) {
                        $ax = $this->isExactScore($a, $actualHigh, $actualLow);
                        $bx = $this->isExactScore($b, $actualHigh, $actualLow);
                        if ($ax !== $bx) {
                            return ($bx ? 1 : 0) <=> ($ax ? 1 : 0); // exact match first
                        }
                    }
                } elseif ($key === 'most_correct') {
                    if ($a->correct_picks !== $b->correct_picks) {
                        return $b->correct_picks <=> $a->correct_picks;
                    }
                } elseif ($key === 'final_goals_closest') {
                    if ($actualTotal !== null) {
                        $da = $this->predictedTotal($a) === null ? PHP_INT_MAX : abs($this->predictedTotal($a) - $actualTotal);
                        $db = $this->predictedTotal($b) === null ? PHP_INT_MAX : abs($this->predictedTotal($b) - $actualTotal);
                        if ($da !== $db) {
                            return $da <=> $db; // closer first
                        }
                    }
                } elseif ($key === 'earliest_submission') {
                    $ta = $a->picks_submitted_at?->getTimestamp() ?? PHP_INT_MAX;
                    $tb = $b->picks_submitted_at?->getTimestamp() ?? PHP_INT_MAX;
                    if ($ta !== $tb) {
                        return $ta <=> $tb; // earlier first
                    }
                }
            }

            return 0;
        };

        usort($members, $compare);

        return $this->withRanks($members, $compare);
    }

    /** Predicted total goals in the Final, or null if the player didn't predict a score. */
    private function predictedTotal($membership): ?int
    {
        if ($membership->final_score_a === null || $membership->final_score_b === null) {
            return null;
        }

        return $membership->final_score_a + $membership->final_score_b;
    }

    /**
     * Whether the player's predicted Final scoreline exactly matches the actual
     * one (team-agnostic: compares winner/loser goals, since the winner always
     * has the higher score — ties aren't allowed).
     */
    private function isExactScore($membership, ?int $actualHigh, ?int $actualLow): bool
    {
        if ($membership->final_score_a === null || $membership->final_score_b === null) {
            return false;
        }

        $high = max($membership->final_score_a, $membership->final_score_b);
        $low = min($membership->final_score_a, $membership->final_score_b);

        return $high === $actualHigh && $low === $actualLow;
    }

    /**
     * @param  array<int, \App\Models\PoolMembership>  $members
     * @return array<int, array{rank: int, membership: \App\Models\PoolMembership}>
     */
    private function withRanks(array $members, callable $compare): array
    {

        // Standard competition ranking: tied members share a rank.
        $result = [];
        $rank = 0;
        $position = 0;
        $previous = null;
        foreach ($members as $membership) {
            $position++;
            if ($previous === null || $compare($previous, $membership) !== 0) {
                $rank = $position;
            }
            $result[] = ['rank' => $rank, 'membership' => $membership];
            $previous = $membership;
        }

        return $result;
    }
}
