<?php

namespace App\Modules\Players\Actions;

use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkType;
use App\Modules\Players\DTO\DailyWorkQuote;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\WorkUnavailable;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CompleteDailyWork
{
    public function __construct(private PlayerWallet $wallet) {}

    public function handle(User $user, int $workTypeId, ?DailyWorkQuote $quote = null): WorkShift
    {
        return DB::transaction(function () use ($user, $workTypeId, $quote): WorkShift {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new WorkUnavailable('Work is unavailable for this account.');
            }

            $today = CarbonImmutable::now(config('doglive.work_timezone'))->startOfDay();
            if ($quote !== null && $quote->workedOn !== $today->toDateString()) {
                throw new WorkUnavailable('The daily work details changed. Refresh and confirm again.');
            }
            $latest = WorkShift::query()->whereBelongsTo($owner)->orderByDesc('worked_on')->first();

            if ($latest !== null && $latest->worked_on->toDateString() >= $today->toDateString()) {
                if ($latest->worked_on->toDateString() === $today->toDateString() && $latest->work_type_id === $workTypeId) {
                    $this->assertRewardQuote($quote, $latest->coins_reward, $latest->gems_reward);

                    return $latest;
                }

                throw new WorkUnavailable('You have already worked today. Come back tomorrow.');
            }

            $work = WorkType::query()->sharedLock()->find($workTypeId);

            if ($work === null || ! $work->is_active || $work->coins_reward < 1 || $work->gems_bonus < 0) {
                throw new WorkUnavailable('This work is currently unavailable.');
            }

            $streakDay = $latest?->nextStreakDay($today) ?? 1;
            $gems = $streakDay === WorkShift::STREAK_LENGTH ? $work->gems_bonus : 0;
            $this->assertRewardQuote($quote, $work->coins_reward, $gems);
            $shift = WorkShift::query()->create([
                'user_id' => $owner->id,
                'work_type_id' => $work->id,
                'worked_on' => $today->toDateString(),
                'streak_day' => $streakDay,
                'coins_reward' => $work->coins_reward,
                'gems_reward' => $gems,
            ]);

            $operation = 'daily-work:'.$today->toDateString();
            $this->wallet->change($owner, 'coins', $work->coins_reward, $operation.':coins', 'daily_work');

            if ($gems > 0) {
                $this->wallet->change($owner, 'gems', $gems, $operation.':gems', 'daily_work_bonus');
            }

            return $shift;
        }, attempts: 3);
    }

    private function assertRewardQuote(?DailyWorkQuote $quote, int $coins, int $gems): void
    {
        if ($quote !== null && ($quote->expectedCoins !== $coins || $quote->expectedGems !== $gems)) {
            throw new WorkUnavailable('The daily work details changed. Refresh and confirm again.');
        }
    }
}
