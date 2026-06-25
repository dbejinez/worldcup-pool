<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bracket_match_id')->constrained('bracket_matches')->cascadeOnDelete();
            // Snapshot of the change for traceable result corrections.
            $table->foreignId('old_winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('new_winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedSmallInteger('old_total_goals')->nullable();
            $table->unsignedSmallInteger('new_total_goals')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_audits');
    }
};
