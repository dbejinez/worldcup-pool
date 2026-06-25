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
    /**
     * Show the team/matchup setup form, or the loaded bracket if already built.
     */
    public function edit(Request $request, Pool $pool): View
    {
        Gate::authorize('manage', $pool);

        $r32 = $pool->matches()
            ->where('round', 'R32')
            ->with(['teamA', 'teamB'])
            ->orderBy('position')
            ->get();

        return view('pools.bracket.setup', compact('pool', 'r32'));
    }

    /**
     * Build the bracket from 16 Round-of-32 matchups.
     */
    public function store(StoreBracketRequest $request, Pool $pool, BracketBuilder $builder): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        if ($pool->teams()->exists()) {
            return back()->with('error', 'This pool already has teams loaded. Reset the bracket first.');
        }

        abort_unless($pool->status === 'setup', 403, 'Teams can only be loaded while the pool is in setup.');

        $builder->build($pool, $request->validated('matchups'));

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', 'Bracket created — 32 teams and all knockout rounds are set up.');
    }

    /**
     * Reset the bracket (teams + matches) while the pool is still in setup.
     */
    public function destroy(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        abort_unless($pool->status === 'setup', 403, 'The bracket can only be reset during setup.');

        // Cascades delete matches and picks via FKs.
        $pool->matches()->delete();
        $pool->teams()->delete();

        return redirect()
            ->route('pools.bracket.edit', $pool)
            ->with('status', 'Bracket reset. You can load the teams again.');
    }
}
