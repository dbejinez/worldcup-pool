<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePoolRequest;
use App\Http\Requests\UpdatePoolSettingsRequest;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PoolController extends Controller
{
    /**
     * List the pools the current user belongs to.
     */
    public function index(Request $request): View
    {
        $memberships = $request->user()
            ->memberships()
            ->with('pool.creator')
            ->latest()
            ->get();

        return view('pools.index', compact('memberships'));
    }

    /**
     * Show the "create pool" form.
     */
    public function create(): View
    {
        return view('pools.create');
    }

    /**
     * Create a pool, make the creator a manager, and seed default scoring.
     */
    public function store(StorePoolRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Admins' pools are auto-approved; everyone else's await admin approval.
        $autoApprove = (bool) $user->is_admin;

        $pool = DB::transaction(function () use ($request, $user, $autoApprove) {
            $method = $request->validated('method') ?? 'full';
            $pool = Pool::create([
                'name' => $request->validated('name'),
                'method' => $method,
                'start_round' => $method === 'full' ? ($request->validated('start_round') ?? 'R32') : 'R32',
                'status' => 'setup',
                'timezone' => 'America/Mexico_City',
                'tiebreaker_order' => ['exact_score', 'final_goals_closest', 'most_correct', 'earliest_submission'],
                'join_token' => Str::random(32),
                'created_by' => $user->id,
                'approved_at' => $autoApprove ? now() : null,
                'approved_by' => $autoApprove ? $user->id : null,
            ]);

            // Default per-round points come from the migration defaults.
            $pool->scoringConfig()->create([]);

            $pool->memberships()->create([
                'user_id' => $user->id,
                'role' => 'manager',
                'joined_at' => now(),
            ]);

            return $pool;
        });

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', $autoApprove
                ? 'Pool created — you are its manager.'
                : 'Pool created — it will be active once an admin approves it.');
    }

    /**
     * Show a single pool (members only).
     */
    public function show(Request $request, Pool $pool): View
    {
        Gate::authorize('view', $pool);

        $membership = $pool->memberships()
            ->where('user_id', $request->user()->id)
            ->first();

        $pool->load(['scoringConfig', 'memberships.user', 'teams', 'creator', 'invites']);

        return view('pools.show', compact('pool', 'membership'));
    }

    /**
     * Manager settings: scoring, tie-breakers, deadline, name.
     */
    public function settings(Pool $pool): View
    {
        Gate::authorize('manage', $pool);

        $pool->load('scoringConfig');

        return view('pools.settings', compact('pool'));
    }

    /**
     * Persist manager settings.
     */
    public function updateSettings(UpdatePoolSettingsRequest $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $data = $request->validated();

        // Convert the local (Mexico City) deadline to UTC for storage.
        $deadlineUtc = null;
        if (! empty($data['deadline_local'])) {
            $deadlineUtc = Carbon::parse($data['deadline_local'], $pool->timezone)->utc();
        }

        DB::transaction(function () use ($pool, $data, $deadlineUtc) {
            $pool->update([
                'name' => $data['name'],
                'tiebreaker_order' => $data['tiebreakers'],
                'deadline_utc' => $deadlineUtc,
                'settings_saved_at' => now(),
            ]);

            $pool->scoringConfig()->update([
                'pts_r32' => $data['pts_r32'],
                'pts_r16' => $data['pts_r16'],
                'pts_qf' => $data['pts_qf'],
                'pts_sf' => $data['pts_sf'],
                'pts_third' => $data['pts_third'],
                'pts_final' => $data['pts_final'],
            ]);
        });

        return redirect()
            ->route('pools.settings', $pool)
            ->with('status', 'Settings saved.');
    }

    /**
     * Open the pool for picks (setup -> open). Requires 32 teams and a deadline.
     */
    public function open(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        if (! $pool->isApproved()) {
            return back()->with('error', 'This pool is awaiting admin approval before it can open.');
        }

        if (! $pool->isReadyToOpen()) {
            return back()->with('error', 'Load all 32 teams and set a pick deadline before opening the pool.');
        }

        $pool->update(['status' => 'open']);

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', 'Pool is now open for picks!');
    }

    /**
     * Close picks (open -> locked). Players can no longer make changes, and
     * everyone's picks become visible.
     */
    public function close(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        if ($pool->isIncremental()) {
            return back()->with('error', 'Incremental pools lock picks per round, not all at once.');
        }

        abort_unless($pool->status === 'open', 403, 'Only an open pool can be closed.');

        $pool->update(['status' => 'locked']);

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', 'Picks are now closed — players can no longer make changes.');
    }

    /**
     * Reopen a closed pool for picks (locked -> open), e.g. if closed by mistake.
     */
    public function reopen(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        abort_unless($pool->status === 'locked', 403, 'Only a closed pool can be reopened.');

        $pool->update(['status' => 'open']);

        return redirect()
            ->route('pools.show', $pool)
            ->with('status', 'Pool reopened for picks.');
    }

    /**
     * Incremental: lock a round's picks (manager). Players can no longer change them,
     * and the round's picks become visible to everyone.
     */
    public function lockRound(Pool $pool, string $round): RedirectResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->isIncremental() && in_array($round, Pool::ROUND_SEQUENCE, true), 404);

        $locked = $pool->locked_rounds ?? [];
        if (! in_array($round, $locked, true)) {
            $locked[] = $round;
            $pool->update(['locked_rounds' => array_values($locked)]);
        }

        return back()->with('status', 'Round locked — those picks are now final and visible.');
    }

    /**
     * Incremental: unlock a round's picks (only if no results have been entered yet).
     */
    public function unlockRound(Pool $pool, string $round): RedirectResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->isIncremental() && in_array($round, Pool::ROUND_SEQUENCE, true), 404);
        abort_if($pool->roundComplete($round), 403, 'This round already has results and cannot be reopened.');

        $locked = array_values(array_filter($pool->locked_rounds ?? [], fn ($r) => $r !== $round));
        $pool->update(['locked_rounds' => $locked]);

        return back()->with('status', 'Round unlocked.');
    }

    /**
     * Permanently delete a pool (manager only). Cascades to memberships, teams,
     * bracket matches, picks, invites, scoring config and audits via FKs.
     */
    public function destroy(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $name = $pool->name;
        $pool->delete();

        return redirect()
            ->route('pools.index')
            ->with('status', "Pool \"{$name}\" was deleted.");
    }

    /**
     * Regenerate the pool's public join link (invalidates the old one).
     */
    public function regenerateJoinLink(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $pool->update(['join_token' => Str::random(32)]);

        return back()->with('status', 'Join link regenerated — the old link is now invalid.');
    }

    /**
     * Manager-issued password reset (no email): generate a temporary password for
     * a pool member and show it once so the manager can hand it off.
     */
    public function resetMemberPassword(Pool $pool, User $user): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        abort_unless(
            $pool->memberships()->where('user_id', $user->id)->exists(),
            404,
            'That user is not in this pool.'
        );

        $temp = (new \App\Services\PasswordIssuer)->issueTemporary($user);

        return back()->with('reset_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temp,
        ]);
    }
}
