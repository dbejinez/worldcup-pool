<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            // 'full' = whole bracket up front; 'incremental' = round-by-round vs real results.
            $table->enum('method', ['full', 'incremental'])->default('full')->after('name');
            // Incremental only: which rounds the manager has locked (picks closed).
            $table->json('locked_rounds')->nullable()->after('settings_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn(['method', 'locked_rounds']);
        });
    }
};
