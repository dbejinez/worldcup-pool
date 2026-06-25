<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Services\StandingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StandingsController extends Controller
{
    public function index(Request $request, Pool $pool, StandingsService $service): View
    {
        Gate::authorize('view', $pool);

        $standings = $service->rank($pool);
        $viewer = $pool->memberships()->where('user_id', $request->user()->id)->first();
        $final = $pool->matches()->where('round', 'FINAL')->with('actualWinner')->first();

        // Each player's predicted champion (their FINAL winner pick), shown only
        // once Final picks are revealed (or to the manager / the player themselves).
        $finalPicks = $final
            ? $pool->picks()->where('bracket_match_id', $final->id)->pluck('predicted_winner_team_id', 'user_id')
            : collect();
        $championsVisible = $pool->isIncremental() ? $pool->roundRevealed('FINAL') : $pool->picksRevealed();

        return view('pools.standings.index', [
            'pool' => $pool,
            'standings' => $standings,
            'revealed' => $pool->picksRevealed(),
            'viewerIsManager' => (bool) $viewer?->isManager(),
            'viewerId' => $request->user()->id,
            'finalScoreA' => $final?->final_actual_score_a,
            'finalScoreB' => $final?->final_actual_score_b,
            'champion' => $final?->actualWinner,
            'finalPicks' => $finalPicks,
            'championsVisible' => $championsVisible,
            'teamNames' => $pool->teams()->pluck('name', 'id'),
            'teamCodes' => $pool->teams()->pluck('country_code', 'id'),
        ]);
    }
}
