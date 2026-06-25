<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    /**
     * Show the forced "set a new password" screen.
     */
    public function show(): View
    {
        return view('auth.change-password');
    }

    /**
     * Save the new password and clear the must-change flag.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'], // hashed by the model's cast
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Your password has been updated.');
    }
}
