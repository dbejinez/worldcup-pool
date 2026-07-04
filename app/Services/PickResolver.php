<?php

namespace App\Services;

/**
 * Resolves, for a given set of player picks, which two teams are the valid
 * participants of each bracket match. A match above R32 is filled by the
 * winners (or, for the Third Place match, the losers) the player chose in the
 * feeding matches — so the candidate teams cascade up from the player's own
 * predictions. Mirrors the client-side cascade in the pick sheet.
 */
class PickResolver
{
    /** @var array<int, object> */
    private array $matchById = [];

    /** @var array<int, array<string, array{match: int, type: string}>> */
    private array $sources = [];

    /**
     * @param  iterable<object>  $matches  BracketMatch models (all rounds for one pool)
     */
    public function __construct(iterable $matches)
    {
        foreach ($matches as $m) {
            $this->matchById[$m->id] = $m;
        }

        foreach ($this->matchById as $m) {
            if ($m->winner_to_match_id) {
                $this->sources[$m->winner_to_match_id][$m->winner_to_slot] = ['match' => $m->id, 'type' => 'winner'];
            }
            if ($m->loser_to_match_id) {
                $this->sources[$m->loser_to_match_id][$m->loser_to_slot] = ['match' => $m->id, 'type' => 'loser'];
            }
        }
    }

    /**
     * The team occupying a slot ('A' or 'B') of a match, given current picks.
     *
     * @param  array<int, int>  $picks  matchId => predictedWinnerTeamId
     */
    public function slotTeam(int $matchId, string $slot, array $picks): ?int
    {
        $m = $this->matchById[$matchId] ?? null;
        if (! $m) {
            return null;
        }

        $src = $this->sources[$matchId][$slot] ?? null;

        // No feeder into this slot means it's the starting round — return seeded team.
        if (! $src) {
            return $slot === 'A' ? $m->team_a_id : $m->team_b_id;
        }

        if ($src['type'] === 'winner') {
            return $picks[$src['match']] ?? null;
        }

        // 'loser': the participant of the child match that was NOT picked to win.
        $winner = $picks[$src['match']] ?? null;
        $a = $this->slotTeam($src['match'], 'A', $picks);
        $b = $this->slotTeam($src['match'], 'B', $picks);
        if ($winner === null || $a === null || $b === null) {
            return null;
        }

        return $winner === $a ? $b : $a;
    }

    /**
     * @param  array<int, int>  $picks
     * @return array{0: ?int, 1: ?int}
     */
    public function participants(int $matchId, array $picks): array
    {
        return [
            $this->slotTeam($matchId, 'A', $picks),
            $this->slotTeam($matchId, 'B', $picks),
        ];
    }

    /**
     * Validate that every match has a pick that is one of its resolved participants.
     *
     * @param  array<int, int>  $picks
     * @return bool  true if the full bracket is consistent and complete
     */
    public function isCompleteAndConsistent(array $picks): bool
    {
        foreach ($this->matchById as $id => $m) {
            [$a, $b] = $this->participants($id, $picks);
            $pick = $picks[$id] ?? null;

            if ($pick === null || $a === null || $b === null) {
                return false;
            }
            if ($pick !== $a && $pick !== $b) {
                return false;
            }
        }

        return true;
    }

    /**
     * Front-end payload: each match with its R32 teams (if any) and slot sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function frontendMatches(): array
    {
        $out = [];
        foreach ($this->matchById as $m) {
            $out[] = [
                'id' => $m->id,
                'round' => $m->round,
                'position' => $m->position,
                'team_a_id' => $m->team_a_id,
                'team_b_id' => $m->team_b_id,
                'srcA' => $this->sources[$m->id]['A'] ?? null,
                'srcB' => $this->sources[$m->id]['B'] ?? null,
            ];
        }

        return $out;
    }
}
