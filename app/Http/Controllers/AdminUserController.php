<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PasswordIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    /**
     * List all users (global admin only), with optional search.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->withCount('memberships')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('admin.users.index', compact('users', 'q'));
    }

    /**
     * Issue a temporary password for any user, regardless of pool membership.
     */
    public function resetPassword(Request $request, User $user, PasswordIssuer $issuer): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $temp = $issuer->issueTemporary($user);

        return back()->with('reset_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temp,
        ]);
    }
}
