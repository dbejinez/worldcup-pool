<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->enum('round', ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL']);
            $table->unsignedSmallInteger('position'); // slot within the round (1-based)

            // Participants. Filled by the manager for R32; later rounds fill as results advance.
            $table->foreignId('team_a_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('teams')->nullOnDelete();

            // Bracket wiring (app-managed, no DB FK to keep this self-referencing tree simple).
            $table->unsignedBigInteger('winner_to_match_id')->nullable();
            $table->enum('winner_to_slot', ['A', 'B'])->nullable();
            $table->unsignedBigInteger('loser_to_match_id')->nullable(); // SF losers -> Third Place
            $table->enum('loser_to_slot', ['A', 'B'])->nullable();

            // Results (entered manually by a manager).
            $table->foreignId('actual_winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedSmallInteger('final_actual_total_goals')->nullable(); // FINAL only, for tie-breaker

            $table->timestamps();
            $table->unique(['pool_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_matches');
    }
};
