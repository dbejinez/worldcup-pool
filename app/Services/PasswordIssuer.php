<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class PasswordIssuer
{
    /**
     * Set a random temporary password on the user (hashed by the model cast)
     * and force them to change it on next sign-in. Returns the plain temp value.
     */
    public function issueTemporary(User $user): string
    {
        $temp = Str::password(12, letters: true, numbers: true, symbols: false);

        $user->forceFill([
            'password' => $temp,
            'must_change_password' => true,
        ])->save();

        return $temp;
    }
}
