<?php

use App\Models\Team;
use App\Support\CountryFlags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('country_code', 8)->nullable()->after('short_code');
        });

        // Backfill existing teams from their names.
        foreach (Team::all() as $team) {
            if ($code = CountryFlags::codeFor($team->name)) {
                $team->forceFill(['country_code' => $code])->save();
            }
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
