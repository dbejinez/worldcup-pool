<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->string('join_token', 32)->nullable()->unique()->after('locked_rounds');
        });

        // Backfill existing pools.
        foreach (\App\Models\Pool::all() as $pool) {
            $pool->update(['join_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('join_token');
        });
    }
};
