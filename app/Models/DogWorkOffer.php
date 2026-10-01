<?php

namespace App\Models;

use Database\Factories\DogWorkOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily immutable catalogue snapshot; reservations count players who started work.
 *
 * @property int $id
 * @property int $dog_work_board_id
 * @property int $dog_work_type_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property int $required_skill_id
 * @property array<string, string> $required_skill_name
 * @property int $required_skill_level
 * @property int $coins_reward
 * @property int $gems_reward
 * @property int $duration_seconds
 * @property int $energy_cost
 * @property int $daily_limit
 * @property int $reserved_count
 * @property DogWorkBoard $board
 */
#[Fillable(['dog_work_board_id', 'dog_work_type_id', 'code', 'name', 'description', 'required_skill_id', 'required_skill_name', 'required_skill_level', 'coins_reward', 'gems_reward', 'duration_seconds', 'energy_cost', 'daily_limit', 'reserved_count'])]
class DogWorkOffer extends Model
{
    /** @use HasFactory<DogWorkOfferFactory> */
    use HasFactory;

    /** @return BelongsTo<DogWorkBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(DogWorkBoard::class, 'dog_work_board_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'required_skill_name' => 'array', 'dog_work_board_id' => 'integer',
            'dog_work_type_id' => 'integer', 'required_skill_id' => 'integer', 'required_skill_level' => 'integer',
            'coins_reward' => 'integer', 'gems_reward' => 'integer', 'duration_seconds' => 'integer', 'energy_cost' => 'integer',
            'daily_limit' => 'integer', 'reserved_count' => 'integer'];
    }
}
