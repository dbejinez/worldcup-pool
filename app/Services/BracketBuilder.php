<?php

namespace App\Services;

use App\Models\Pool;
use App\Support\CountryFlags;
use Illuminate\Support\Facades\DB;

class BracketBuilder
{
    /**
     * Build the full knockout bracket for a pool from 16 Round-of-32 matchups.
     *
     * Each matchup is ['a' => 'Team Name', 'b' => 'Team Name']. This creates the
     * 32 teams, the 16 R32 matches (seeded with those teams), and the empty
     * skeleton above them (R16, QF, SF, Third Place, Final) wired together so
     * results propagate up the tree and SF losers drop into the Third Place match.
     *
     * @param  array<int, array{a: string, b: string}>  $matchups
     */
    public function build(Pool $pool, array $matchups): void
    {
        DB::transaction(function () use ($pool, $matchups) {
            // 1. Create the 32 teams, keeping their R32 pairings in order.
            $pairs = [];
            foreach ($matchups as $m) {
                $nameA = trim($m['a']);
                $nameB = trim($m['b']);
                $a = $pool->teams()->create(['name' => $nameA, 'country_code' => CountryFlags::codeFor($nameA)]);
                $b = $pool->teams()->create(['name' => $nameB, 'country_code' => CountryFlags::codeFor($nameB)]);
                $pairs[] = [$a->id, $b->id];
            }

            // 2. Create the upper bracket first so we have IDs to wire winners into.
            $final = $pool->matches()->create(['round' => 'FINAL', 'position' => 1]);
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

            $qf = $this->createRound($pool, 'QF', 4, $sf);
            $r16 = $this->createRound($pool, 'R16', 8, $qf);

            // 3. R32: seed the teams and wire winners into R16.
            for ($i = 1; $i <= 16; $i++) {
                $parent = $r16[intdiv($i + 1, 2)]; // ceil(i/2)
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
            $parent = $parents[intdiv($i + 1, 2)]; // ceil(i/2)
            $matches[$i] = $pool->matches()->create([
                'round' => $round,
                'position' => $i,
                'winner_to_match_id' => $parent->id,
                'winner_to_slot' => $i % 2 === 1 ? 'A' : 'B',
            ]);
        }

        return $matches;
    }
}
