<?php

namespace App\Models;

use Database\Factories\StatusEffectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $kind
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, int> $modifiers
 * @property int|null $duration_seconds
 * @property string|null $condition_state
 * @property int|null $condition_threshold
 * @property string $condition_operator
 * @property bool $is_active
 *
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 */
#[Fillable(['code', 'kind', 'name', 'description', 'modifiers', 'duration_seconds', 'condition_state', 'condition_threshold', 'is_active', 'condition_operator'])]
class StatusEffect extends Model
{
    /** @use HasFactory<StatusEffectFactory> */
    use HasFactory;

    /** @return HasMany<ItemEffectRule, $this> */
    public function itemRules(): HasMany
    {
        return $this->hasMany(ItemEffectRule::class);
    }

    /** @return Effect */
    public function snapshot(): array
    {
        return [
            'code' => $this->code, 'kind' => $this->kind, 'name' => $this->name,
            'description' => $this->description, 'modifiers' => $this->modifiers,
            'duration_seconds' => $this->duration_seconds,
            'condition_state' => $this->condition_state, 'condition_threshold' => $this->condition_threshold,
            'condition_operator' => $this->condition_operator ?? 'lt',
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'modifiers' => 'array',
            'duration_seconds' => 'integer', 'condition_threshold' => 'integer', 'is_active' => 'boolean'];
    }
}
