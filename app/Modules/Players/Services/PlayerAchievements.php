<?php

namespace App\Modules\Players\Services;

use App\Models\Achievement;
use App\Models\KennelPurchase;
use App\Models\PetCareAction;
use App\Models\PlayerAchievement;
use App\Models\User;
use App\Modules\Players\Enums\AchievementMetric;
use Illuminate\Support\Facades\DB;

final class PlayerAchievements
{
    /**
     * Call within the gameplay transaction to preserve unlocks alongside their completed action.
     *
     * @param  list<AchievementMetric>|null  $affectedMetrics  Null preserves the complete backfill.
     */
    public function synchronize(User $user, ?array $affectedMetrics = null): void
    {
        DB::transaction(function () use ($user, $affectedMetrics): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $query = Achievement::query()->where('is_active', true)->orderBy('id');
            if ($affectedMetrics !== null) {
                $query->whereIn('rules->metric', array_map(fn (AchievementMetric $metric): string => $metric->value, $affectedMetrics));
            }
            $catalogue = $query->get(['id', 'rules']);

            if ($catalogue->isEmpty()) {
                return;
            }

            $states = PlayerAchievement::query()->where('user_id', $owner->id)->get()->keyBy('achievement_id');
            $pending = $catalogue->filter(fn (Achievement $achievement): bool => $states->get($achievement->id)?->unlocked_at === null);
            $pendingMetrics = $pending->map(fn (Achievement $achievement): string => $achievement->rules['metric'])->unique()->all();
            $statistics = $owner->pet_statistics;
            $metrics = [
                'trainings' => $owner->trainings_count,
                'walks' => $owner->walks_count,
                'meals' => $statistics['care.meal'] ?? 0,
                'washes' => $statistics['care.wash'] ?? 0,
                'skills' => $statistics['skill_training'] ?? 0,
                'jobs' => $statistics['work'] ?? 0,
                'veterinary' => array_sum(array_filter($statistics, static fn (string $code): bool => str_starts_with($code, 'veterinary.'), ARRAY_FILTER_USE_KEY)),
                'active_days' => $owner->active_days,
                'competition_wins' => $owner->competition_wins,
                'exhibition_wins' => $owner->exhibition_wins,
            ];
            if (in_array('active_dogs', $pendingMetrics, true)) {
                $metrics['active_dogs'] = $owner->pets()->active()->count();
            }
            if (in_array('kennel_purchases', $pendingMetrics, true)) {
                $metrics['kennel_purchases'] = KennelPurchase::query()->where('user_id', $owner->id)->count();
            }
            if (in_array('first_dog', $pendingMetrics, true)) {
                $metrics['first_dog'] = (int) ($owner->starter_pet_claimed_at !== null
                    || ($metrics['active_dogs'] ?? 0) > 0 || ($metrics['kennel_purchases'] ?? 0) > 0
                    || $owner->pets()->exists() || KennelPurchase::query()->where('user_id', $owner->id)->exists());
            }
            $updates = [];

            foreach ($pending as $achievement) {
                $state = $states->get($achievement->id);

                $metric = $achievement->rules['metric'];
                $target = $achievement->rules['target'];

                if ($target < 1) {
                    continue;
                }

                if ($metric === 'daily_meals') {
                    $metrics['daily_meals'] ??= $this->mostMealsInOneDay($owner);
                }

                if (! array_key_exists($metric, $metrics)) {
                    continue;
                }

                $progress = min($target, max($state === null ? 0 : $state->progress, $metrics[$metric]));

                if ($progress === 0 || ($state !== null && $state->progress === $progress && $progress < $target)) {
                    continue;
                }

                $updates[] = [
                    'user_id' => $owner->id,
                    'achievement_id' => $achievement->id,
                    'progress' => $progress,
                    'unlocked_at' => $progress >= $target ? now() : null,
                ];
            }

            if ($updates !== []) {
                PlayerAchievement::query()->upsert($updates, ['user_id', 'achievement_id'], ['progress', 'unlocked_at', 'updated_at']);
            }
        }, attempts: 3);
    }

    private function mostMealsInOneDay(User $owner): int
    {
        $days = PetCareAction::query()
            ->where('user_id', $owner->id)
            ->where('group', 'feed')
            ->where('variant', 'meal')
            ->whereNotNull('pet_id')
            ->whereNotNull('completed_at')
            ->whereNull('cancelled_at')
            ->selectRaw('count(*) AS meals_count')
            ->groupBy('pet_id')
            ->groupByRaw("(completed_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date", [config('doglive.work_timezone', 'Europe/Moscow')]);

        return (int) DB::query()->fromSub($days, 'daily_feeding')->max('meals_count');
    }
}
