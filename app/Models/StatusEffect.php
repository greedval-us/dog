<?php

namespace App\Models;

use Database\Factories\StatusEffectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $kind
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, int> $modifiers
 * @property int|null $duration_seconds
 * @property string|null $condition_state
 * @property int|null $condition_below
 *
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 */
#[Fillable(['code', 'kind', 'name', 'description', 'modifiers', 'duration_seconds', 'condition_state', 'condition_below', 'is_active'])]
class StatusEffect extends Model
{
    /** @use HasFactory<StatusEffectFactory> */
    use HasFactory;

    /** @return Effect */
    public function snapshot(): array
    {
        return [
            'code' => $this->code, 'kind' => $this->kind, 'name' => $this->name,
            'description' => $this->description, 'modifiers' => $this->modifiers,
            'duration_seconds' => $this->duration_seconds,
            'condition_state' => $this->condition_state, 'condition_below' => $this->condition_below,
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'modifiers' => 'array',
            'duration_seconds' => 'integer', 'condition_below' => 'integer', 'is_active' => 'boolean'];
    }
}
