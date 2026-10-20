<?php

use App\Modules\Pets\Calculators\GameplayTiming;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('trainings')->select(['id', 'code', 'duration_seconds', 'cooldown_seconds'])->lazyById(200) as $training) {
            DB::table('trainings')->where('id', $training->id)->update([
                'duration_seconds' => $this->trainingSeconds((int) $training->duration_seconds, GameplayTiming::TRAINING_DURATION_SECONDS[$training->code] ?? GameplayTiming::DEFAULT_TRAINING_DURATION_SECONDS),
                'cooldown_seconds' => $this->trainingSeconds((int) $training->cooldown_seconds, GameplayTiming::TRAINING_COOLDOWN_SECONDS[$training->code] ?? GameplayTiming::DEFAULT_TRAINING_COOLDOWN_SECONDS),
            ]);
        }

        foreach (['dog_work_types', 'dog_work_offers'] as $table) {
            foreach (DB::table($table)->select(['id', 'duration_seconds'])->lazyById(200) as $job) {
                DB::table($table)->where('id', $job->id)->update([
                    'duration_seconds' => max(GameplayTiming::MIN_SECONDS, min(GameplayTiming::DOG_WORK_DURATION_SECONDS, (int) $job->duration_seconds)),
                ]);
            }
        }
    }

    /**
     * Prior catalogue values cannot be recovered; launched receipts retain their original timestamps.
     */
    public function down(): void {}

    private function trainingSeconds(int $seconds, int $default): int
    {
        return $seconds > GameplayTiming::MAX_SECONDS ? $default : max(GameplayTiming::MIN_SECONDS, $seconds);
    }
};
