<?php

namespace App\Models;

use Database\Factories\DogWorkTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property int $required_skill_id
 * @property int $required_skill_level
 * @property int $coins_reward
 * @property int $gems_reward
 * @property int $duration_seconds
 * @property int $energy_cost
 * @property int $daily_limit
 * @property bool $is_active
 * @property Skill $skill
 */
#[Fillable(['code', 'name', 'description', 'required_skill_id', 'required_skill_level', 'coins_reward', 'gems_reward', 'duration_seconds', 'energy_cost', 'daily_limit', 'is_active'])]
class DogWorkType extends Model
{
    /** @use HasFactory<DogWorkTypeFactory> */
    use HasFactory;

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'required_skill_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'required_skill_id' => 'integer', 'required_skill_level' => 'integer',
            'coins_reward' => 'integer', 'gems_reward' => 'integer', 'duration_seconds' => 'integer', 'energy_cost' => 'integer',
            'daily_limit' => 'integer', 'is_active' => 'boolean'];
    }
}
