<?php

namespace App\Modules\Players\Queries;

use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkType;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;

final class GetDailyWork
{
    /**
     * @return array{completedToday: bool, canWork: bool, progress: int, streakLength: int, resetsAt: string, timezone: string, earnedCoins: int, earnedGems: int, jobs: list<array{id: int, name: string, description: string, coins: int, gems: int}>}
     */
    public function handle(User $user, string $locale): array
    {
        $timezone = config('doglive.work_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $latest = WorkShift::query()->whereBelongsTo($user)->orderByDesc('worked_on')->first();
        $completed = $latest !== null && $latest->worked_on->toDateString() >= $today->toDateString();

        return [
            'completedToday' => $completed,
            'canWork' => $user->status === PlayerStatus::Active && ! $completed,
            'progress' => $completed ? $latest->streak_day : ($latest?->nextStreakDay($today) ?? 1) - 1,
            'streakLength' => WorkShift::STREAK_LENGTH,
            'resetsAt' => $today->addDay()->toIso8601String(),
            'timezone' => $timezone,
            'earnedCoins' => $completed ? $latest->coins_reward : 0,
            'earnedGems' => $completed ? $latest->gems_reward : 0,
            'jobs' => array_values(WorkType::query()->where('is_active', true)->where('coins_reward', '>', 0)->where('gems_bonus', '>=', 0)
                ->orderBy('id')->get()->map(fn (WorkType $work): array => [
                    'id' => $work->id,
                    'name' => $work->name[$locale] ?? $work->name['en'] ?? $work->code,
                    'description' => $work->description[$locale] ?? $work->description['en'] ?? '',
                    'coins' => $work->coins_reward,
                    'gems' => $work->gems_bonus,
                ])->all()),
        ];
    }
}
