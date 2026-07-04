<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBracketRequest;
use App\Models\Pool;
use App\Services\BracketBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BracketController extends Controller
{
    private const ROUND_LABELS = [
        'R32' => 'Round of 32', 'R16' => 'Round of 16', 'QF' => 'Quarterfinals',
        'SF' => 'Semifinals', 'THIRD' => 'Third Place Match', 'FINAL' => 'Final',
    ];

    /**
     * Show the team/matchup setup form, or the loaded bracket if already built.
     */
    public function edit(Request $request, Pool $pool): View
    {
        Gate::authorize('manage', $pool);

        $startRound = $pool->isIncremental() ? 'R32' : ($pool->start_round ?? 'R32');

        $startMatches = $pool->matches()
            ->where('round', $startRound)
            ->with(['teamA', 'teamB'])
            ->orderBy('position')
            ->get();

        return view('pools.bracket.setup', [
            'pool' => $pool,
            'startMatches' => $startMatches,
            'startRound' => $startRound,
            'startRoundLabel' => self::ROUND_LABELS[$startRound],
            'matchupCount' => $pool->startRoundMatchCount(),
        ]);
    }

    /**
     * Build the bracket from the starting-round matchups.
     */
    public function store(StoreBracketRequest $request, Pool $pool, BracketBuilder $builder): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        if ($pool->teams()->exists()) {
            return back()->with('error', 'This pool already has teams loaded. Reset the bracket first.');
        }

        abort_unless($pool->status === 'setup', 403, 'Teams can only be loaded while the pool is in setup.');

        $builder->build($pool, $request->validated('matchups'));

        $teamCount = $pool->startRoundTeamCount();
        $label = self::ROUND_LABELS[$pool->start_round ?? 'R32'];

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', "Bracket created — {$teamCount} teams and the {$label} onwards are set up.");
    }

    /**
     * Reset the bracket (teams + matches) while the pool is still in setup.
     */
    public function destroy(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        abort_unless($pool->status === 'setup', 403, 'The bracket can only be reset during setup.');

        $pool->matches()->delete();
        $pool->teams()->delete();

        return redirect()
            ->route('pools.bracket.edit', $pool)
            ->with('status', 'Bracket reset. You can load the teams again.');
    }
}
