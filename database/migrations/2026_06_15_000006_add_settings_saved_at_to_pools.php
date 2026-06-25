<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->timestamp('settings_saved_at')->nullable()->after('tiebreaker_order');
        });

        // Existing pools are treated as already configured.
        DB::table('pools')->whereNull('settings_saved_at')->update(['settings_saved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('settings_saved_at');
        });
    }
};
