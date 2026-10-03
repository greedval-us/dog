<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $achievement_id
 * @property int $progress
 * @property CarbonImmutable|null $unlocked_at
 */
#[Fillable(['user_id', 'achievement_id', 'progress', 'unlocked_at'])]
class PlayerAchievement extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'achievement_id' => 'integer',
            'progress' => 'integer',
            'unlocked_at' => 'immutable_datetime',
        ];
    }
}
