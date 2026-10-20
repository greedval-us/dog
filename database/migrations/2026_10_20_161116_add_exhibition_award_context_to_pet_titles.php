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
        Schema::table('pet_titles', function (Blueprint $table) {
            $table->dropUnique(['game_event_entry_id']);
            $table->foreignId('game_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('breed_id')->nullable();
            $table->string('class_key', 16)->nullable();
            $table->string('sex', 8)->nullable();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->unsignedInteger('age_months')->nullable();
            $table->unique(['game_event_entry_id', 'code']);
        });
        DB::statement("CREATE UNIQUE INDEX pet_titles_breed_award_unique ON pet_titles (game_event_id, breed_id, code) WHERE code IN ('conformation_best_male', 'conformation_best_female', 'conformation_best_of_breed')");
        DB::statement('ALTER TABLE game_event_entries DROP CONSTRAINT game_entries_money_check');
        DB::statement('ALTER TABLE game_event_entries ADD CONSTRAINT game_entries_money_check CHECK (fee >= 0 AND prize >= 0 AND (rank IS NULL OR rank >= 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('pet_titles')->select('game_event_entry_id')->groupBy('game_event_entry_id')->havingRaw('count(*) > 1')->exists()
            || DB::table('game_event_entries')->where('rank', '>', 8)->exists()) {
            throw new RuntimeException('Exhibition awards and larger show rings cannot be rolled back without discarding results.');
        }
        DB::statement('DROP INDEX pet_titles_breed_award_unique');
        Schema::table('pet_titles', function (Blueprint $table) {
            $table->dropUnique(['game_event_entry_id', 'code']);
            $table->dropConstrainedForeignId('game_event_id');
            $table->dropColumn(['breed_id', 'class_key', 'sex', 'rank', 'age_months']);
            $table->unique('game_event_entry_id');
        });
        DB::statement('ALTER TABLE game_event_entries DROP CONSTRAINT game_entries_money_check');
        DB::statement('ALTER TABLE game_event_entries ADD CONSTRAINT game_entries_money_check CHECK (fee >= 0 AND prize >= 0 AND (rank IS NULL OR rank BETWEEN 1 AND 8))');
    }
};
