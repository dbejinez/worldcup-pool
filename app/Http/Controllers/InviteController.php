<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Pool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InviteController extends Controller
{
    /**
     * Manager: list invites and the add-emails form.
     */
    public function index(Pool $pool): View
    {
        Gate::authorize('manage', $pool);

        $invites = $pool->invites()->latest()->get();
        $memberEmails = $pool->memberships()->with('user')->get()
            ->pluck('user.email')->filter()->values();

        return view('pools.invites.index', compact('pool', 'invites', 'memberEmails'));
    }

    /**
     * Manager: create invites from a list of emails.
     */
    public function store(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $validated = $request->validate([
            'emails' => ['required', 'string'],
        ]);

        // Parse emails split by newline, comma, semicolon or whitespace.
        $candidates = collect(preg_split('/[\s,;]+/', $validated['emails']))
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return back()->with('error', 'No valid email addresses found.');
        }

        $existingMembers = $pool->memberships()->with('user')->get()
            ->pluck('user.email')->map(fn ($e) => mb_strtolower((string) $e))->all();
        $existingInvites = $pool->invites()->where('status', 'pending')->pluck('email')
            ->map(fn ($e) => mb_strtolower($e))->all();

        $created = 0;
        $skipped = 0;
        foreach ($candidates as $email) {
            if (in_array($email, $existingMembers, true) || in_array($email, $existingInvites, true)) {
                $skipped++;
                continue;
            }

            $pool->invites()->create([
                'email' => $email,
                'token' => Str::random(40),
                'status' => 'pending',
                'invited_by' => $request->user()->id,
            ]);
            $created++;
        }

        return back()->with('status', "Created {$created} invite(s)" . ($skipped ? ", skipped {$skipped} duplicate(s)." : '.'));
    }

    /**
     * Manager: revoke a pending invite.
     */
    public function destroy(Pool $pool, Invite $invite): RedirectResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($invite->pool_id === $pool->id, 404);

        $invite->delete();

        return back()->with('status', 'Invite revoked.');
    }

    /**
     * Public invite landing. Authenticated users are auto-joined as players and
     * sent to the pool; guests are shown log in / register (then return here).
     */
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invite = Invite::where('token', $token)->with('pool')->first();

        if (! $this->inviteIsValid($invite)) {
            return view('invites.show', [
                'invite' => $invite, 'valid' => false, 'token' => $token,
                'mismatch' => false, 'currentUser' => null,
            ]);
        }

        if (Auth::check()) {
            $user = $request->user();

            // Already in this pool → just go there (keeps their existing role).
            if ($invite->pool->memberships()->where('user_id', $user->id)->exists()) {
                return redirect()->route('pools.show', $invite->pool)
                    ->with('status', "You're already in this pool.");
            }

            // Auto-join ONLY when the signed-in account matches the invited email.
            // This prevents an invite opened in someone else's session (e.g. the
            // manager's) from silently attaching to the wrong account.
            if (strcasecmp($user->email, $invite->email) === 0) {
                $this->joinAsPlayer($invite, $user);

                return redirect()->route('pools.show', $invite->pool)
                    ->with('status', "You've joined {$invite->pool->name}!");
            }

            // Signed in as a different person → let them choose.
            return view('invites.show', [
                'invite' => $invite, 'valid' => true, 'token' => $token,
                'mismatch' => true, 'currentUser' => $user,
            ]);
        }

        // Guests: remember this page so login/registration returns here.
        $request->session()->put('url.intended', route('invite.show', $token));

        return view('invites.show', [
            'invite' => $invite, 'valid' => true, 'token' => $token,
            'mismatch' => false, 'currentUser' => null,
        ]);
    }

    /**
     * POST fallback for the join action (e.g. the explicit "Join" button).
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = Invite::where('token', $token)->with('pool')->first();

        if (! $this->inviteIsValid($invite)) {
            return redirect()->route('invite.show', $token);
        }

        $joined = $this->joinAsPlayer($invite, $request->user());

        return redirect()
            ->route('pools.show', $invite->pool)
            ->with('status', $joined
                ? "You've joined {$invite->pool->name}!"
                : "You're already in this pool.");
    }

    /**
     * Add the user to the invite's pool as a PLAYER (idempotent). Existing
     * members keep their current role. Returns true if a new membership was made.
     */
    private function joinAsPlayer(Invite $invite, $user): bool
    {
        $pool = $invite->pool;

        if ($pool->memberships()->where('user_id', $user->id)->exists()) {
            return false;
        }

        DB::transaction(function () use ($pool, $user, $invite) {
            $pool->memberships()->create([
                'user_id' => $user->id,
                'role' => 'player',
                'joined_at' => now(),
            ]);

            $invite->update([
                'status' => 'accepted',
                'accepted_by' => $user->id,
            ]);
        });

        return true;
    }

    private function inviteIsValid(?Invite $invite): bool
    {
        return $invite !== null
            && $invite->status === 'pending'
            && ($invite->expires_at === null || $invite->expires_at->isFuture());
    }
}
