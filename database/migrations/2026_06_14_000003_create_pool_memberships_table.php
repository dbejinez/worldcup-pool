<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pool_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['manager', 'player'])->default('player');
            // Cached standings data, recomputed when results are entered.
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('correct_picks')->default(0);
            // Tie-breaker: predicted total goals in the Final.
            $table->unsignedSmallInteger('final_goals_prediction')->nullable();
            // Tie-breaker: when this player locked in their picks.
            $table->timestamp('picks_submitted_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_memberships');
    }
};
