<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'accepted'])->default('pending');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['pool_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
