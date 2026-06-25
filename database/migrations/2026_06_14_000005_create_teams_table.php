<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_code', 8)->nullable();
            // Alternate spellings for CSV import matching (e.g. "Turkey" for "Türkiye").
            $table->json('aliases')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
