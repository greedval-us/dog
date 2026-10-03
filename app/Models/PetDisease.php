<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetDiseaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 *
 * @property int $id
 * @property int $pet_id
 * @property int $disease_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property Pet $pet
 * @property Disease $disease
 * @property Effect|null $effect_snapshot
 */
#[Fillable(['pet_id', 'disease_id', 'started_at', 'ended_at', 'effect_snapshot'])]
class PetDisease extends Model
{
    /** @use HasFactory<PetDiseaseFactory> */
    use HasFactory;

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<Disease, $this> */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    /** @param Builder<PetDisease> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'effect_snapshot' => 'array'];
    }
}
