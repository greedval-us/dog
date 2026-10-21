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
        Schema::table('dog', function (Blueprint $table): void {
            $table->json('event_profile')->nullable();
        });
        Schema::table('pets', function (Blueprint $table): void {
            $table->json('event_profile')->nullable();
        });
        Schema::table('pet_titles', function (Blueprint $table): void {
            $table->unsignedInteger('rank')->nullable()->change();
            $table->string('kind', 32)->default('legacy');
            $table->json('details')->default('[]');
            $table->string('regulation_version', 80)->nullable();
            $table->index(['pet_id', 'kind', 'code', 'awarded_at'], 'pet_titles_passport_index');
        });
        Schema::table('pet_sport_records', function (Blueprint $table): void {
            $table->json('regulatory_progress')->default('[]');
        });
        Schema::table('game_event_entries', function (Blueprint $table): void {
            $table->unsignedInteger('rank')->nullable()->change();
        });
        DB::statement("CREATE UNIQUE INDEX pet_titles_championship_unique ON pet_titles (pet_id, discipline, code) WHERE kind = 'championship' AND code NOT IN ('SPORT_CH_RUS', 'SPORT_CUP_RUS', 'SPORT_CH_RKF')");

        $this->backfillProfiles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('game_event_entries')->where('rank', '>', 32767)->exists() || DB::table('pet_titles')->where('rank', '>', 32767)->exists()) {
            throw new RuntimeException('Large sporting fields cannot be rolled back without discarding results.');
        }
        Schema::table('game_event_entries', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rank')->nullable()->change();
        });
        Schema::table('pet_sport_records', function (Blueprint $table): void {
            $table->dropColumn('regulatory_progress');
        });
        DB::statement('DROP INDEX pet_titles_championship_unique');
        Schema::table('pet_titles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rank')->nullable()->change();
            $table->dropIndex('pet_titles_passport_index');
            $table->dropColumn(['kind', 'details', 'regulation_version']);
        });
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn('event_profile');
        });
        Schema::table('dog', function (Blueprint $table): void {
            $table->dropColumn('event_profile');
        });
    }

    private function backfillProfiles(): void
    {
        $profiles = [];
        foreach (DB::table('dog')->get(['id', 'breed', 'size', 'event_profile']) as $dog) {
            $fallback = match ($dog->size) {
                'small' => [18, 39], 'large' => [43, 75], default => [35, 55],
            };
            $profile = match ($dog->breed) {
                'german_shepherd' => ['recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 1, 'working_breed' => true, 'height_ranges' => ['male' => [60, 65], 'female' => [55, 60]]],
                'dachshund' => ['recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 4, 'working_breed' => true, 'height_ranges' => ['male' => [18, 25], 'female' => [18, 25]]],
                'pit_bull' => ['recognized_rkf' => false, 'recognized_fci' => false, 'fci_group' => null, 'working_breed' => false, 'height_ranges' => ['male' => [43, 53], 'female' => [40, 50]]],
                default => ['recognized_rkf' => false, 'recognized_fci' => false, 'fci_group' => null, 'working_breed' => false, 'height_ranges' => ['male' => $fallback, 'female' => $fallback]],
            };
            if ($dog->event_profile !== null && $dog->event_profile !== '[]') {
                $profile = array_replace($profile, json_decode($dog->event_profile, true, flags: JSON_THROW_ON_ERROR));
            } else {
                DB::table('dog')->where('id', $dog->id)->update(['event_profile' => json_encode($profile, JSON_THROW_ON_ERROR)]);
            }
            $profiles[$dog->id] = $profile;
        }

        DB::table('pets')->whereNull('event_profile')->orderBy('id')->chunkById(500, function ($pets) use ($profiles): void {
            foreach ($pets as $pet) {
                $profile = $profiles[$pet->dog_id];
                $range = $profile['height_ranges'][$pet->sex];
                $minimum = (int) round($range[0] * 10);
                $maximum = (int) round($range[1] * 10);
                $height = ($minimum + (int) (hexdec(substr(hash('sha256', 'legacy:'.$pet->id), 0, 8)) % ($maximum - $minimum + 1))) / 10;
                unset($profile['height_ranges']);
                DB::table('pets')->where('id', $pet->id)->update(['event_profile' => json_encode([
                    ...$profile, 'height_cm' => $height, 'registered' => $pet->is_purebred && $profile['recognized_rkf'],
                    'pedigree_verified' => false, 'working_certificate_verified' => false,
                    'race_category' => $pet->is_purebred && $profile['recognized_rkf'] ? '3' : '4',
                ], JSON_THROW_ON_ERROR)]);
            }
        });
    }
};
