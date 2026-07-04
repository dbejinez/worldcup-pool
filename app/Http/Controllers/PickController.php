<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Models\Pick;
use App\Models\User;
use App\Services\PickResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PickController extends Controller
{
    private const ROUND_LABELS = [
        'R32' => 'Round of 32', 'R16' => 'Round of 16', 'QF' => 'Quarterfinals',
        'SF' => 'Semifinals', 'THIRD' => 'Third Place Match', 'FINAL' => 'Final',
    ];

    /**
     * Show the player's pick sheet (cascading for Full pools, round-by-round for Incremental).
     */
    public function edit(Request $request, Pool $pool): View
    {
        Gate::authorize('view', $pool);

        $membership = $pool->memberships()->where('user_id', $request->user()->id)->firstOrFail();

        abort_unless($pool->teams()->exists(), 404, 'This pool has no bracket yet.');

        if ($pool->isIncremental()) {
            return $this->editIncremental($request, $pool, $membership);
        }

        $matches = $pool->matches()->get();
        $resolver = new PickResolver($matches);

        $teams = $pool->teams()->pluck('name', 'id');

        $picks = $pool->picks()
            ->where('user_id', $request->user()->id)
            ->pluck('predicted_winner_team_id', 'bracket_match_id');

        $canEdit = $pool->picksOpen();
        $closedReason = null;
        if (! $canEdit) {
            $closedReason = match ($pool->status) {
                'setup' => 'This pool is not open for picks yet.',
                'locked' => 'The manager has closed picks — no more changes allowed.',
                'complete' => 'The tournament is complete — picks are final.',
                default => 'Picks are closed.',
            };
        }

        return view('pools.picks.edit', [
            'pool' => $pool,
            'matchesData' => $resolver->frontendMatches(),
            'teams' => $teams,
            'teamCodes' => $pool->teams()->pluck('country_code', 'id'),
            'existingPicks' => $picks,
            'finalScoreA' => $membership->final_score_a,
            'finalScoreB' => $membership->final_score_b,
            'canEdit' => $canEdit,
            'closedReason' => $closedReason,
            'roundsFromStart' => $pool->roundsFromStart(),
        ]);
    }

    /**
     * Incremental pick sheet: one round at a time, against the real teams that advanced.
     */
    private function editIncremental(Request $request, Pool $pool, $membership): View
    {
        $matches = $pool->matches()
            ->with(['teamA', 'teamB', 'actualWinner'])
            ->orderBy('position')
            ->get()
            ->groupBy('round');

        $picks = $pool->picks()
            ->where('user_id', $request->user()->id)
            ->pluck('predicted_winner_team_id', 'bracket_match_id');

        $rounds = [];
        foreach (Pool::ROUND_SEQUENCE as $round) {
            $rounds[$round] = [
                'label' => self::ROUND_LABELS[$round],
                'matches' => $matches->get($round) ?? collect(),
                'open' => $pool->roundPicksOpen($round),
                'reachable' => $pool->roundReachable($round),
                'locked' => $pool->roundLocked($round),
                'complete' => $pool->roundComplete($round),
            ];
        }

        return view('pools.picks.incremental', [
            'pool' => $pool,
            'rounds' => $rounds,
            'picks' => $picks,
            'teams' => $pool->teams()->pluck('name', 'id'),
            'teamCodes' => $pool->teams()->pluck('country_code', 'id'),
            'finalScoreA' => $membership->final_score_a,
            'finalScoreB' => $membership->final_score_b,
            'feeders' => Pool::ROUND_FEEDER,
            'labels' => self::ROUND_LABELS,
        ]);
    }

    /**
     * Incremental: save one round's picks (and Final score on the FINAL round).
     */
    public function saveRound(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('view', $pool);
        abort_unless($pool->isIncremental(), 404);

        $membership = $pool->memberships()->where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'round' => ['required', Rule::in(Pool::ROUND_SEQUENCE)],
            'winners' => ['required', 'array'],
            'winners.*' => ['required', 'integer'],
            'final_score_a' => ['nullable', 'integer', 'min:0', 'max:99'],
            'final_score_b' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $round = $validated['round'];
        abort_unless($pool->roundPicksOpen($round), 403, 'This round is not open for picks.');

        $roundMatches = $pool->matches()->where('round', $round)->get();
        $byId = $roundMatches->keyBy('id');

        $picks = [];
        foreach ($validated['winners'] as $matchId => $teamId) {
            $match = $byId->get((int) $matchId);
            if (! $match || ! in_array((int) $teamId, [$match->team_a_id, $match->team_b_id], true)) {
                return back()->withErrors(['picks' => 'Each winner must be one of the two teams in that match.']);
            }
            $picks[(int) $matchId] = (int) $teamId;
        }
        if (count($picks) !== $roundMatches->count()) {
            return back()->withErrors(['picks' => 'Pick a winner for every match in this round.']);
        }

        $finalScore = null;
        if ($round === 'FINAL') {
            $final = $roundMatches->first();
            $a = $validated['final_score_a'] ?? null;
            $b = $validated['final_score_b'] ?? null;
            if (! is_numeric($a) || ! is_numeric($b)) {
                return back()->withErrors(['final_score' => 'Enter the Final score for both teams.']);
            }
            $a = (int) $a;
            $b = (int) $b;
            $winnerOk = $picks[$final->id] === $final->team_a_id ? $a > $b : $b > $a;
            if (! $winnerOk) {
                return back()->withErrors(['final_score' => 'The winning team must have more goals (no ties).']);
            }
            $finalScore = [$a, $b];
        }

        DB::transaction(function () use ($pool, $request, $picks, $roundMatches, $finalScore, $membership) {
            $userId = $request->user()->id;
            $pool->picks()->where('user_id', $userId)
                ->whereIn('bracket_match_id', $roundMatches->pluck('id'))->delete();

            $now = now();
            $rows = [];
            foreach ($picks as $matchId => $teamId) {
                $rows[] = [
                    'pool_id' => $pool->id, 'user_id' => $userId, 'bracket_match_id' => $matchId,
                    'predicted_winner_team_id' => $teamId, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            Pick::insert($rows);

            $update = ['picks_submitted_at' => $now];
            if ($finalScore) {
                $update['final_score_a'] = $finalScore[0];
                $update['final_score_b'] = $finalScore[1];
            }
            $membership->update($update);
        });

        return back()->with('status', self::ROUND_LABELS[$round] . ' picks saved.');
    }

    /**
     * Read-only view of a member's picks. Visibility: own picks always; managers
     * always; others once revealed (full = after close; incremental = per round).
     */
    public function show(Request $request, Pool $pool, User $user): View
    {
        Gate::authorize('view', $pool);

        $viewer = $pool->memberships()->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless(
            $pool->memberships()->where('user_id', $user->id)->exists(),
            404,
            'That user is not in this pool.'
        );

        $isSelf = $user->id === $request->user()->id;
        $isManager = $viewer->isManager();

        if ($pool->isIncremental()) {
            // Per-round reveal: others see only rounds that are locked/complete.
            $visibleRounds = ($isSelf || $isManager)
                ? Pool::ROUND_SEQUENCE
                : array_values(array_filter(Pool::ROUND_SEQUENCE, fn ($r) => $pool->roundRevealed($r)));
        } else {
            abort_unless($isSelf || $isManager || $pool->picksRevealed(), 403, "This player's picks are hidden until the deadline.");
            $visibleRounds = $pool->roundsFromStart();
        }

        $showFinalScore = $isSelf || $isManager || in_array('FINAL', $visibleRounds, true);

        $matches = $pool->matches()
            ->with(['teamA', 'teamB', 'actualWinner'])
            ->orderBy('position')
            ->get()
            ->groupBy('round');

        $picks = $pool->picks()
            ->where('user_id', $user->id)
            ->pluck('predicted_winner_team_id', 'bracket_match_id');

        $teams = $pool->teams()->pluck('name', 'id');
        $membership = $pool->memberships()->where('user_id', $user->id)->first();

        return view('pools.picks.show', [
            'pool' => $pool,
            'player' => $user,
            'matches' => $matches,
            'picks' => $picks,
            'teams' => $teams,
            'teamCodes' => $pool->teams()->pluck('country_code', 'id'),
            'membership' => $membership,
            'roundOrder' => $visibleRounds,
            'showFinalScore' => $showFinalScore,
        ]);
    }

    /**
     * Save the player's full bracket picks + Final total-goals tie-breaker.
     */
    public function update(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('view', $pool);

        $membership = $pool->memberships()->where('user_id', $request->user()->id)->firstOrFail();

        abort_unless($pool->picksOpen(), 403, 'Picks are closed for this pool.');

        $validated = $request->validate([
            'picks' => ['required', 'array'],
            'picks.*' => ['required', 'integer'],
            'final_score_a' => ['required', 'integer', 'min:0', 'max:99'],
            'final_score_b' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        // Normalize to int => int and constrain to this pool's matches and teams.
        $matchIds = $pool->matches()->pluck('id')->all();
        $teamIds = $pool->teams()->pluck('id')->all();

        $picks = [];
        foreach ($validated['picks'] as $matchId => $teamId) {
            $matchId = (int) $matchId;
            $teamId = (int) $teamId;
            if (! in_array($matchId, $matchIds, true) || ! in_array($teamId, $teamIds, true)) {
                return back()->withErrors(['picks' => 'Your picks contain an unknown match or team.']);
            }
            $picks[$matchId] = $teamId;
        }

        $resolver = new PickResolver($pool->matches()->get());
        if (! $resolver->isCompleteAndConsistent($picks)) {
            return back()->withErrors([
                'picks' => 'Your bracket is incomplete or inconsistent. Please pick a winner for every match.',
            ]);
        }

        // The predicted Final score must match the predicted champion (winner higher, no tie).
        $finalId = $pool->matches()->where('round', 'FINAL')->value('id');
        $champion = $picks[$finalId] ?? null;
        $finalistA = $resolver->slotTeam($finalId, 'A', $picks);
        $finalistB = $resolver->slotTeam($finalId, 'B', $picks);
        $a = (int) $validated['final_score_a'];
        $b = (int) $validated['final_score_b'];
        $scoreOk = ($champion === $finalistA) ? $a > $b : (($champion === $finalistB) ? $b > $a : false);
        if (! $scoreOk) {
            return back()->withErrors([
                'final_score' => 'Your Final score must match your champion: the team you picked to win needs more goals than the other (no ties).',
            ]);
        }

        DB::transaction(function () use ($pool, $request, $picks, $validated, $membership) {
            $userId = $request->user()->id;

            $pool->picks()->where('user_id', $userId)->delete();

            $rows = [];
            $now = now();
            foreach ($picks as $matchId => $teamId) {
                $rows[] = [
                    'pool_id' => $pool->id,
                    'user_id' => $userId,
                    'bracket_match_id' => $matchId,
                    'predicted_winner_team_id' => $teamId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Pick::insert($rows);

            $membership->update([
                'final_score_a' => $validated['final_score_a'],
                'final_score_b' => $validated['final_score_b'],
                'picks_submitted_at' => $now,
            ]);
        });

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', 'Your picks have been saved!');
    }
}
