<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pool_memberships', function (Blueprint $table) {
            $table->dropColumn('final_goals_prediction');
            // Predicted Final scoreline (goals for each of the player's two finalists).
            $table->unsignedSmallInteger('final_score_a')->nullable()->after('correct_picks');
            $table->unsignedSmallInteger('final_score_b')->nullable()->after('final_score_a');
        });
    }

    public function down(): void
    {
        Schema::table('pool_memberships', function (Blueprint $table) {
            $table->dropColumn(['final_score_a', 'final_score_b']);
            $table->unsignedSmallInteger('final_goals_prediction')->nullable()->after('correct_picks');
        });
    }
};
