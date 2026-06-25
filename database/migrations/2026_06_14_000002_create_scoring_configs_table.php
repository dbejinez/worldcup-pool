<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->unique()->constrained()->cascadeOnDelete();
            // Points awarded per correct winner pick, by knockout round.
            $table->unsignedInteger('pts_r32')->default(1);
            $table->unsignedInteger('pts_r16')->default(2);
            $table->unsignedInteger('pts_qf')->default(4);
            $table->unsignedInteger('pts_sf')->default(8);
            $table->unsignedInteger('pts_third')->default(4);
            $table->unsignedInteger('pts_final')->default(16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_configs');
    }
};
