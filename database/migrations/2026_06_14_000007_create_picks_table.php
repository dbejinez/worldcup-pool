<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bracket_match_id')->constrained('bracket_matches')->cascadeOnDelete();
            $table->foreignId('predicted_winner_team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();
            // One pick per player per match slot.
            $table->unique(['user_id', 'bracket_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picks');
    }
};
