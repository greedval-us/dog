<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DogWorkShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $pet_id
 * @property string $pet_name
 * @property int $dog_work_offer_id
 * @property string $token
 * @property string $activity_token
 * @property array<string, string> $name
 * @property int $coins_reward
 * @property int $gems_reward
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['user_id', 'pet_id', 'pet_name', 'dog_work_offer_id', 'token', 'activity_token', 'name', 'coins_reward', 'gems_reward', 'started_at', 'ends_at', 'completed_at'])]
class DogWorkShift extends Model
{
    /** @use HasFactory<DogWorkShiftFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['pet_id' => 'integer', 'dog_work_offer_id' => 'integer', 'name' => 'array', 'coins_reward' => 'integer',
            'gems_reward' => 'integer', 'started_at' => 'datetime', 'ends_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
