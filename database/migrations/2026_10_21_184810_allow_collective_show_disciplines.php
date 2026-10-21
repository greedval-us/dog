<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE game_events DROP CONSTRAINT game_events_discipline_check');
        DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_discipline_check CHECK (discipline IN ('agility','nosework','canicross','conformation','progeny','couple','breeders'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('game_events')->whereIn('discipline', ['couple', 'breeders'])->exists()) {
            throw new RuntimeException('Cannot remove collective show disciplines while their events exist.');
        }
        DB::statement('ALTER TABLE game_events DROP CONSTRAINT game_events_discipline_check');
        DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_discipline_check CHECK (discipline IN ('agility','nosework','canicross','conformation','progeny'))");
    }
};
