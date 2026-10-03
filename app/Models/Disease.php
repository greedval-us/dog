<?php

namespace App\Models;

use Database\Factories\DiseaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 *
 * @phpstan-type AcquisitionRules array{group: string|null, variants: list<string>, daily_min: int, daily_max: int}
 *
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 *
 * @property AcquisitionRules|null $acquisition_rules
 * @property array<string, int>|null $modifiers
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'acquisition_rules', 'modifiers', 'is_active'])]
#[Hidden(['acquisition_rules'])]
class Disease extends Model
{
    /** @use HasFactory<DiseaseFactory> */
    use HasFactory;

    /** @return HasMany<PetDisease, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(PetDisease::class);
    }

    /** @return Effect */
    public function snapshot(): array
    {
        return [
            'code' => 'disease:'.$this->code,
            'disease_id' => $this->id,
            'kind' => 'debuff',
            'name' => $this->name,
            'description' => $this->description ?? [],
            'modifiers' => $this->modifiers ?? [],
            'duration_seconds' => null,
            'condition_state' => null,
            'expires_at' => null,
            'recovery_actions' => [],
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'acquisition_rules' => 'array', 'modifiers' => 'array', 'is_active' => 'boolean'];
    }
}
