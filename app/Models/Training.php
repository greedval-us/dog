<?php

namespace App\Models;

use Database\Factories\TrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, int> $stat_gains
 * @property array<string, int> $state_costs
 * @property int $energy_cost
 * @property int $duration_seconds
 * @property int $cooldown_seconds
 * @property int $risk_chance
 * @property bool $is_active
 * @property StatusEffect|null $statusEffect
 */
#[Fillable(['code', 'name', 'stat_gains', 'state_costs', 'energy_cost', 'duration_seconds', 'cooldown_seconds', 'status_effect_id', 'risk_chance', 'is_active'])]
class Training extends Model
{
    /** @use HasFactory<TrainingFactory> */
    use HasFactory;

    /** @return BelongsTo<StatusEffect, $this> */
    public function statusEffect(): BelongsTo
    {
        return $this->belongsTo(StatusEffect::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'stat_gains' => 'array', 'state_costs' => 'array',
            'energy_cost' => 'integer', 'duration_seconds' => 'integer', 'cooldown_seconds' => 'integer',
            'risk_chance' => 'integer', 'is_active' => 'boolean'];
    }
}
