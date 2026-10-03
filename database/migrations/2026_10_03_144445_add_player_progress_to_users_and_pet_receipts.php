<?php

use App\Modules\Players\Calculators\PlayerLevelRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN experience TYPE numeric USING experience::numeric');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_experience_nonnegative_integer CHECK (experience >= 0 AND experience = trunc(experience))');

        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('pet_statistics')->default('{}');
            $table->unsignedInteger('active_days')->default(0);
            $table->timestamp('last_pet_action_at')->nullable();
        });

        foreach (['pet_care_actions', 'dog_work_shifts', 'pet_skill_lessons', 'veterinary_visits'] as $receiptTable) {
            Schema::table($receiptTable, function (Blueprint $table): void {
                $table->unsignedBigInteger('experience_awarded')->nullable();
            });
        }

        DB::statement(<<<'SQL'
            WITH completed_actions AS (
                SELECT user_id,
                    CASE WHEN "group" = 'training' THEN 'training' ELSE 'care.' || variant END AS code,
                    completed_at AS performed_at,
                    "group" = 'walk' AS is_walk,
                    "group" = 'training' AS is_training
                FROM pet_care_actions WHERE completed_at IS NOT NULL
                UNION ALL
                SELECT user_id, 'work', completed_at, false, false
                FROM dog_work_shifts WHERE completed_at IS NOT NULL
                UNION ALL
                SELECT user_id, 'skill_training', trained_at, false, true
                FROM pet_skill_lessons
                UNION ALL
                SELECT user_id, 'veterinary.' || service, performed_at, false, false
                FROM veterinary_visits
            ), event_counts AS (
                SELECT user_id, code, count(*) AS actions_count
                FROM completed_actions WHERE user_id IS NOT NULL GROUP BY user_id, code
            ), event_statistics AS (
                SELECT user_id, jsonb_object_agg(code, actions_count) AS statistics
                FROM event_counts GROUP BY user_id
            ), totals AS (
                SELECT user_id, count(DISTINCT (performed_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date) AS active_days,
                    max(performed_at) AS last_action_at,
                    count(*) FILTER (WHERE is_walk) AS walks_count,
                    count(*) FILTER (WHERE is_training) AS trainings_count
                FROM completed_actions WHERE user_id IS NOT NULL GROUP BY user_id
            )
            UPDATE users SET
                pet_statistics = event_statistics.statistics,
                active_days = totals.active_days,
                last_pet_action_at = totals.last_action_at,
                walks_count = GREATEST(users.walks_count, totals.walks_count),
                trainings_count = GREATEST(users.trainings_count, totals.trainings_count)
            FROM totals JOIN event_statistics USING (user_id)
            WHERE users.id = totals.user_id
            SQL, [config('doglive.work_timezone', 'Europe/Moscow')]);

        foreach (['pet_care_actions' => 'completed_at', 'dog_work_shifts' => 'completed_at',
            'pet_skill_lessons' => 'trained_at', 'veterinary_visits' => 'performed_at'] as $table => $completedColumn) {
            DB::table($table)->whereNotNull($completedColumn)->update(['experience_awarded' => 0]);
        }

        foreach (DB::table('users')->select(['id', 'experience'])->lazyById(500) as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'level' => PlayerLevelRules::progress((string) $user->experience)['level'],
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_experience_nonnegative_integer');
        DB::statement('ALTER TABLE users ALTER COLUMN experience TYPE bigint USING experience::bigint');

        foreach (['pet_care_actions', 'dog_work_shifts', 'pet_skill_lessons', 'veterinary_visits'] as $receiptTable) {
            Schema::table($receiptTable, function (Blueprint $table): void {
                $table->dropColumn('experience_awarded');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pet_statistics', 'active_days', 'last_pet_action_at']);
        });
    }
};
