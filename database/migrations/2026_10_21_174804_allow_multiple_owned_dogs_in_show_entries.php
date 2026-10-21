<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_event_entries', function (Blueprint $table) {
            $table->dropUnique(['game_event_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('game_event_entries')->select('game_event_id', 'user_id')->whereNotNull('user_id')
            ->groupBy('game_event_id', 'user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore one entry per owner while a show contains multiple owned dogs.');
        }
        Schema::table('game_event_entries', function (Blueprint $table) {
            $table->unique(['game_event_id', 'user_id']);
        });
    }
};
