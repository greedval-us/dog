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
        Schema::table('pet_titles', function (Blueprint $table): void {
            $table->string('scope_key', 160)->default('');
            $table->dropUnique(['game_event_entry_id', 'code']);
            $table->unique(['game_event_entry_id', 'code', 'scope_key'], 'pet_titles_entry_code_scope_unique');
        });
        DB::statement('DROP INDEX pet_titles_championship_unique');
        DB::statement("CREATE UNIQUE INDEX pet_titles_championship_unique ON pet_titles (pet_id, discipline, code, scope_key) WHERE kind = 'championship' AND code NOT IN ('SPORT_CH_RUS', 'SPORT_CUP_RUS', 'SPORT_CH_RKF', 'SPORT_BREED_RKF')");

        Schema::create('pet_title_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('championship_title_id')->constrained('pet_titles')->restrictOnDelete();
            $table->foreignId('credential_title_id')->constrained('pet_titles')->restrictOnDelete();
            $table->string('purpose', 80);
            $table->timestamps();
            $table->unique(['credential_title_id', 'purpose']);
            $table->index('championship_title_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('pet_title_consumptions')->exists() || DB::table('pet_titles')->where('scope_key', '<>', '')->exists()
            || DB::table('pet_titles')->where('kind', 'championship')->where('code', 'SPORT_BREED_RKF')
                ->select(['pet_id', 'discipline', 'code'])->groupBy(['pet_id', 'discipline', 'code'])->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Scoped diplomas and consumed certificate evidence cannot be rolled back without discarding history.');
        }
        Schema::dropIfExists('pet_title_consumptions');
        DB::statement('DROP INDEX pet_titles_championship_unique');
        Schema::table('pet_titles', function (Blueprint $table): void {
            $table->dropUnique('pet_titles_entry_code_scope_unique');
            $table->unique(['game_event_entry_id', 'code']);
            $table->dropColumn('scope_key');
        });
        DB::statement("CREATE UNIQUE INDEX pet_titles_championship_unique ON pet_titles (pet_id, discipline, code) WHERE kind = 'championship' AND code NOT IN ('SPORT_CH_RUS', 'SPORT_CUP_RUS', 'SPORT_CH_RKF')");
    }
};
