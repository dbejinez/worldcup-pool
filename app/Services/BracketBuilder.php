<?php

namespace App\Services;

use App\Models\Pool;
use App\Support\CountryFlags;
use Illuminate\Support\Facades\DB;

class BracketBuilder
{
    /**
     * Build the knockout bracket for a pool from the starting-round matchups.
     *
     * For full-bracket pools the starting round is configurable (R32, R16, QF,
     * SF, or FINAL). Incremental pools always start at R32.
     *
     * Each matchup is ['a' => 'Team Name', 'b' => 'Team Name']. Teams are
     * created, the upper-bracket skeleton is wired top-down, and the starting
     * round's matches are seeded with those teams.
     *
     * @param  array<int, array{a: string, b: string}>  $matchups
     */
    public function build(Pool $pool, array $matchups): void
    {
        $startRound = $pool->isIncremental() ? 'R32' : ($pool->start_round ?? 'R32');

        DB::transaction(function () use ($pool, $matchups, $startRound) {
            $pairs = [];
            foreach ($matchups as $m) {
                $nameA = trim($m['a']);
                $nameB = trim($m['b']);
                $a = $pool->teams()->create(['name' => $nameA, 'country_code' => CountryFlags::codeFor($nameA)]);
                $b = $pool->teams()->create(['name' => $nameB, 'country_code' => CountryFlags::codeFor($nameB)]);
                $pairs[] = [$a->id, $b->id];
            }

            $final = $pool->matches()->create(['round' => 'FINAL', 'position' => 1]);

            if ($startRound === 'FINAL') {
                $final->update(['team_a_id' => $pairs[0][0], 'team_b_id' => $pairs[0][1]]);
                return;
            }

            $third = $pool->matches()->create(['round' => 'THIRD', 'position' => 1]);

            $sf = [];
            for ($i = 1; $i <= 2; $i++) {
                $sf[$i] = $pool->matches()->create([
                    'round' => 'SF',
                    'position' => $i,
                    'winner_to_match_id' => $final->id,
                    'winner_to_slot' => $i === 1 ? 'A' : 'B',
                    'loser_to_match_id' => $third->id,
                    'loser_to_slot' => $i === 1 ? 'A' : 'B',
                ]);
            }

            if ($startRound === 'SF') {
                $this->seedMatchArray($sf, $pairs);
                return;
            }

            $qf = $this->createRound($pool, 'QF', 4, $sf);

            if ($startRound === 'QF') {
                $this->seedMatchArray($qf, $pairs);
                return;
            }

            $r16 = $this->createRound($pool, 'R16', 8, $qf);

            if ($startRound === 'R16') {
                $this->seedMatchArray($r16, $pairs);
                return;
            }

            // R32 (default): create and seed in one step.
            for ($i = 1; $i <= 16; $i++) {
                $parent = $r16[intdiv($i + 1, 2)];
                [$aId, $bId] = $pairs[$i - 1];
                $pool->matches()->create([
                    'round' => 'R32',
                    'position' => $i,
                    'team_a_id' => $aId,
                    'team_b_id' => $bId,
                    'winner_to_match_id' => $parent->id,
                    'winner_to_slot' => $i % 2 === 1 ? 'A' : 'B',
                ]);
            }
        });
    }

    /**
     * Create $count matches for a round, each wiring its winner into the parent round.
     *
     * @param  array<int, \App\Models\BracketMatch>  $parents
     * @return array<int, \App\Models\BracketMatch>
     */
    private function createRound(Pool $pool, string $round, int $count, array $parents): array
    {
        $matches = [];
        for ($i = 1; $i <= $count; $i++) {
            $parent = $parents[intdiv($i + 1, 2)];
            $matches[$i] = $pool->matches()->create([
                'round' => $round,
                'position' => $i,
                'winner_to_match_id' => $parent->id,
                'winner_to_slot' => $i % 2 === 1 ? 'A' : 'B',
            ]);
        }

        return $matches;
    }

    /**
     * Stamp team pairs onto an already-created set of matches (the starting round).
     *
     * @param  array<int, \App\Models\BracketMatch>  $matches  1-indexed
     * @param  array<int, array{int, int}>  $pairs
     */
    private function seedMatchArray(array $matches, array $pairs): void
    {
        foreach ($pairs as $i => $pair) {
            $matches[$i + 1]->update(['team_a_id' => $pair[0], 'team_b_id' => $pair[1]]);
        }
    }
}
