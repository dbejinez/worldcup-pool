<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bracket_matches', function (Blueprint $table) {
            $table->dropColumn('final_actual_total_goals');
            // Actual Final scoreline (goals for the actual team A / team B). FINAL only.
            $table->unsignedSmallInteger('final_actual_score_a')->nullable();
            $table->unsignedSmallInteger('final_actual_score_b')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bracket_matches', function (Blueprint $table) {
            $table->dropColumn(['final_actual_score_a', 'final_actual_score_b']);
            $table->unsignedSmallInteger('final_actual_total_goals')->nullable();
        });
    }
};
