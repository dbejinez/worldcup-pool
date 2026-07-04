<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Services\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResultController extends Controller
{
    /**
     * Manager: result entry screen (winner per match + Final total goals).
     */
    public function edit(Pool $pool): View
    {
        Gate::authorize('manage', $pool);

        abort_unless($pool->teams()->exists(), 404, 'This pool has no bracket yet.');

        $matches = $pool->matches()
            ->with(['teamA', 'teamB', 'actualWinner'])
            ->orderBy('position')
            ->get()
            ->groupBy('round');

        $roundOrder = $pool->roundsFromStart();
        $final = $pool->matches()->where('round', 'FINAL')->first();

        return view('pools.results.edit', compact('pool', 'matches', 'final', 'roundOrder'));
    }

    /**
     * Manager: save results, propagate the bracket and recompute scores.
     */
    public function update(Request $request, Pool $pool, ResultService $service): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $validated = $request->validate([
            'winners' => ['array'],
            'winners.*' => ['nullable'],
            'final_score_a' => ['nullable', 'integer', 'min:0', 'max:99'],
            'final_score_b' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        // If a Final winner and a full score are being recorded, they must agree
        // (winning team has strictly more goals — no ties).
        $final = $pool->matches()->where('round', 'FINAL')->first();
        $winners = $validated['winners'] ?? [];
        $a = $validated['final_score_a'] ?? null;
        $b = $validated['final_score_b'] ?? null;
        if ($final && ! empty($winners[$final->id]) && $a !== null && $b !== null) {
            $winnerId = (int) $winners[$final->id];
            $scoreOk = ($winnerId === $final->team_a_id) ? $a > $b
                : (($winnerId === $final->team_b_id) ? $b > $a : false);
            if (! $scoreOk) {
                return back()->withErrors([
                    'final_score' => 'The Final score must match the winner: the winning team needs more goals than the other (no ties).',
                ]);
            }
        }

        $service->recordResults(
            $pool,
            $validated['winners'] ?? [],
            $validated['final_score_a'] ?? null,
            $validated['final_score_b'] ?? null,
            $request->user()->id,
        );

        return redirect()
            ->route('pools.results.edit', $pool)
            ->with('status', 'Results saved and standings updated.');
    }
}
