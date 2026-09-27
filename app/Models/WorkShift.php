<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WorkShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $work_type_id
 * @property CarbonImmutable $worked_on
 * @property int $streak_day
 * @property int $coins_reward
 * @property int $gems_reward
 */
#[Fillable(['user_id', 'work_type_id', 'worked_on', 'streak_day', 'coins_reward', 'gems_reward'])]
class WorkShift extends Model
{
    public const STREAK_LENGTH = 5;

    /** @use HasFactory<WorkShiftFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkType, $this> */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function nextStreakDay(CarbonImmutable $today): int
    {
        return $this->worked_on->toDateString() === $today->subDay()->toDateString()
            ? $this->streak_day % self::STREAK_LENGTH + 1
            : 1;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['worked_on' => 'immutable_date', 'streak_day' => 'integer', 'coins_reward' => 'integer', 'gems_reward' => 'integer'];
    }
}
