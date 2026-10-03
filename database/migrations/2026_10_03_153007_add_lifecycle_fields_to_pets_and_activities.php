<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->timestamp('died_at')->nullable();
        });
        foreach (['pet_care_actions', 'dog_work_shifts'] as $activityTable) {
            Schema::table($activityTable, function (Blueprint $table): void {
                $table->timestamp('cancelled_at')->nullable();
            });
            DB::statement('UPDATE '.$activityTable.' AS receipt SET cancelled_at = pets.retired_at FROM pets WHERE receipt.pet_id = pets.id AND pets.retired_at IS NOT NULL AND receipt.completed_at IS NULL');
        }
        DB::table('pets')->whereNotNull('retired_at')->update(['activity' => null, 'activity_token' => null, 'activity_started_at' => null, 'activity_ends_at' => null]);
        DB::statement('CREATE INDEX pets_living_owner_index ON pets (user_id, id) WHERE retired_at IS NULL AND died_at IS NULL');
        DB::statement('CREATE INDEX pets_memorial_owner_index ON pets (user_id, id DESC) WHERE retired_at IS NOT NULL OR died_at IS NOT NULL');
        foreach (['life.retirement' => ['ru' => 'Выход на пенсию', 'en' => 'Retirement'],
            'life.death' => ['ru' => 'Ушла из жизни', 'en' => 'Passed away']] as $code => $name) {
            DB::table('pet_history_events')->insertOrIgnore([
                'code' => $code, 'kind' => 'action', 'name' => json_encode($name, JSON_THROW_ON_ERROR),
                'cooldown_minutes' => 0, 'priority' => 0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX pets_living_owner_index');
        DB::statement('DROP INDEX pets_memorial_owner_index');
        DB::table('pet_history_events')->whereIn('code', ['life.retirement', 'life.death'])->delete();
        foreach (['pet_care_actions', 'dog_work_shifts'] as $activityTable) {
            Schema::table($activityTable, function (Blueprint $table): void {
                $table->dropColumn('cancelled_at');
            });
        }
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn('died_at');
        });
    }
};
