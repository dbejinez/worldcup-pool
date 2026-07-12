<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('picks', function (Blueprint $table) {
            $table->index('pool_id');
            $table->index('bracket_match_id');
        });

        Schema::table('pool_memberships', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('picks', function (Blueprint $table) {
            $table->dropIndex(['pool_id']);
            $table->dropIndex(['bracket_match_id']);
        });

        Schema::table('pool_memberships', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
