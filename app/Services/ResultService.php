<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\ResultAudit;
use Illuminate\Support\Facades\DB;

/**
 * Records actual match results, keeps the real bracket propagated (winners
 * advance; SF losers drop into the Third Place match), prunes downstream
 * results when an earlier result is corrected, and recomputes standings.
 */
class ResultService
{
    private const ROUND_ORDER = ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

    /**
     * Apply a batch of winner selections (matchId => teamId|'') plus the Final
     * scoreline, then propagate and recompute scores.
     *
     * @param  array<int|string, mixed>  $winners
     */
    public function recordResults(Pool $pool, array $winners, ?int $finalScoreA, ?int $finalScoreB, int $changedBy): void
    {
        DB::transaction(function () use ($pool, $winners, $finalScoreA, $finalScoreB, $changedBy) {
            $matches = $pool->matches()->get()->keyBy('id');

            foreach (self::ROUND_ORDER as $round) {
                foreach ($matches->where('round', $round) as $m) {
                    if (! array_key_exists($m->id, $winners)) {
                        continue;
                    }

                    $raw = $winners[$m->id];
                    $teamId = ($raw === '' || $raw === null) ? null : (int) $raw;

                    // Only a current participant (or null to clear) is valid.
                    if ($teamId !== null && ! in_array($teamId, [$m->team_a_id, $m->team_b_id], true)) {
                        continue;
                    }

                    $newA = ($m->round === 'FINAL' && $finalScoreA !== null) ? (int) $finalScoreA : null;
                    $newB = ($m->round === 'FINAL' && $finalScoreB !== null) ? (int) $finalScoreB : null;

                    $oldTotal = ($m->final_actual_score_a !== null && $m->final_actual_score_b !== null)
                        ? $m->final_actual_score_a + $m->final_actual_score_b
                        : null;
                    $newTotal = ($newA !== null && $newB !== null) ? $newA + $newB : null;

                    $winnerChanged = $m->actual_winner_team_id !== $teamId;
                    $scoreChanged = $m->round === 'FINAL'
                        && ($m->final_actual_score_a !== $newA || $m->final_actual_score_b !== $newB);

                    if (! $winnerChanged && ! $scoreChanged) {
                        continue;
                    }

                    ResultAudit::create([
                        'pool_id' => $pool->id,
                        'bracket_match_id' => $m->id,
                        'old_winner_team_id' => $m->actual_winner_team_id,
                        'new_winner_team_id' => $teamId,
                        'old_total_goals' => $oldTotal,
                        'new_total_goals' => $newTotal,
                        'changed_by' => $changedBy,
                    ]);

                    $m->actual_winner_team_id = $teamId;
                    if ($m->round === 'FINAL') {
                        $m->final_actual_score_a = $newA;
                        $m->final_actual_score_b = $newB;
                    }
                    $m->save();
                }
            }

            $this->propagateAndPrune($pool);
            $this->recomputeScores($pool);

            // Mark complete once the Final has a winner.
            $finalDecided = $pool->matches()
                ->where('round', 'FINAL')->whereNotNull('actual_winner_team_id')->exists();
            if ($finalDecided && $pool->status !== 'complete') {
                $pool->update(['status' => 'complete']);
            }
        });
    }

    /**
     * Rebuild each non-R32 match's participants from recorded results, and clear
     * any recorded winner that is no longer one of its participants.
     */
    public function propagateAndPrune(Pool $pool): void
    {
        $matches = $pool->matches()->get()->keyBy('id');

        // sources[parentId][slot] = ['child' => id, 'type' => 'winner'|'loser']
        $sources = [];
        foreach ($matches as $m) {
            if ($m->winner_to_match_id) {
                $sources[$m->winner_to_match_id][$m->winner_to_slot] = ['child' => $m->id, 'type' => 'winner'];
            }
            if ($m->loser_to_match_id) {
                $sources[$m->loser_to_match_id][$m->loser_to_slot] = ['child' => $m->id, 'type' => 'loser'];
            }
        }

        foreach (['R16', 'QF', 'SF', 'THIRD', 'FINAL'] as $round) {
            foreach ($matches->where('round', $round) as $m) {
                foreach (['A', 'B'] as $slot) {
                    $src = $sources[$m->id][$slot] ?? null;

                    // No feeder means this slot belongs to the starting round —
                    // its team was seeded by the manager and must not be overwritten.
                    if (! $src) {
                        continue;
                    }

                    $team = null;
                    $child = $matches[$src['child']];
                    if ($src['type'] === 'winner') {
                        $team = $child->actual_winner_team_id;
                    } elseif ($child->actual_winner_team_id !== null) {
                        // loser = the participant who isn't the winner
                        $team = $child->actual_winner_team_id === $child->team_a_id
                            ? $child->team_b_id
                            : $child->team_a_id;
                    }

                    $column = $slot === 'A' ? 'team_a_id' : 'team_b_id';
                    $m->{$column} = $team;
                }

                // Prune a winner that is no longer valid after participants changed.
                if ($m->actual_winner_team_id !== null
                    && ! in_array($m->actual_winner_team_id, [$m->team_a_id, $m->team_b_id], true)) {
                    $m->actual_winner_team_id = null;
                    if ($m->round === 'FINAL') {
                        $m->final_actual_score_a = null;
                        $m->final_actual_score_b = null;
                    }
                }

                $m->save();
            }
        }
    }

    /**
     * Recompute each member's cached score and correct-pick count.
     *
     * Scoring rule: a player earns a round's points iff their predicted winner
     * for a match equals the actual winner of that match (broken-bracket rule).
     */
    public function recomputeScores(Pool $pool): void
    {
        $config = $pool->scoringConfig;
        $points = [
            'R32' => $config->pts_r32,
            'R16' => $config->pts_r16,
            'QF' => $config->pts_qf,
            'SF' => $config->pts_sf,
            'THIRD' => $config->pts_third,
            'FINAL' => $config->pts_final,
        ];

        $matches = $pool->matches()->get()->keyBy('id');
        $picksByUser = $pool->picks()->get()->groupBy('user_id');

        foreach ($pool->memberships as $membership) {
            $score = 0;
            $correct = 0;

            foreach ($picksByUser->get($membership->user_id, collect()) as $pick) {
                $match = $matches->get($pick->bracket_match_id);
                if ($match
                    && $match->actual_winner_team_id !== null
                    && $match->actual_winner_team_id === $pick->predicted_winner_team_id) {
                    $score += $points[$match->round];
                    $correct++;
                }
            }

            $membership->update(['score' => $score, 'correct_picks' => $correct]);
        }
    }
}
