<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $pet_id
 * @property int $skill_id
 * @property int $level
 * @property CarbonImmutable|null $last_trained_at
 * @property CarbonImmutable|null $cooldown_until
 */
class PetSkill extends Pivot
{
    public $incrementing = true;

    protected $table = 'pet_skill';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['pet_id' => 'integer', 'skill_id' => 'integer', 'level' => 'integer',
            'last_trained_at' => 'datetime', 'cooldown_until' => 'datetime'];
    }
}
