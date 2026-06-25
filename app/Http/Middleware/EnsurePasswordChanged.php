<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Users carrying a manager-issued temporary password must set a new one
     * before they can use the rest of the app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $allowed = ['password.change.show', 'password.change.update', 'logout'];

            if (! in_array($request->route()?->getName(), $allowed, true)) {
                return redirect()->route('password.change.show');
            }
        }

        return $next($request);
    }
}
