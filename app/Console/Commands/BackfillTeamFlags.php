<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Support\CountryFlags;
use Illuminate\Console\Command;

class BackfillTeamFlags extends Command
{
    protected $signature = 'teams:backfill-flags {--dry-run : Show what would change without saving}';

    protected $description = 'Fill in missing country_code on teams using the CountryFlags map';

    public function handle(): int
    {
        $teams = Team::whereNull('country_code')->get();

        if ($teams->isEmpty()) {
            $this->info('All teams already have a country code.');
            return 0;
        }

        $updated = 0;
        $missed = [];

        foreach ($teams as $team) {
            $code = CountryFlags::codeFor($team->name);
            if ($code) {
                if (! $this->option('dry-run')) {
                    $team->update(['country_code' => $code]);
                }
                $this->line("  <info>✓</info> {$team->name} → {$code}");
                $updated++;
            } else {
                $missed[] = $team->name;
            }
        }

        if ($missed) {
            $this->newLine();
            $this->warn('No code found for: ' . implode(', ', $missed));
        }

        $action = $this->option('dry-run') ? 'Would update' : 'Updated';
        $this->newLine();
        $this->info("{$action} {$updated} team(s). " . count($missed) . ' unmatched.');

        return 0;
    }
}
