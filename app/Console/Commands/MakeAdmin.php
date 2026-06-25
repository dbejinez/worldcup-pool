<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email} {--revoke : Remove admin instead of granting}';

    protected $description = 'Grant (or revoke) global admin for a user by email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke')
            ? "{$user->email} is no longer an admin."
            : "{$user->email} is now a global admin.");

        return self::SUCCESS;
    }
}
