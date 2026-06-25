<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['setup', 'open', 'locked', 'complete'])->default('setup');
            // Single global pick deadline, stored in UTC. Displayed in America/Mexico_City.
            $table->timestamp('deadline_utc')->nullable();
            $table->string('timezone')->default('America/Mexico_City');
            // Ordered list of tie-breaker keys, e.g. ["final_goals_closest","most_correct","earliest_submission"].
            $table->json('tiebreaker_order')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pools');
    }
};
