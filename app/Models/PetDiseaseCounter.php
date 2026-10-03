<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetDiseaseCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pet_id
 * @property int $disease_id
 * @property CarbonImmutable $tracked_on
 * @property int $action_count
 * @property int $threshold
 */
#[Fillable(['pet_id', 'disease_id', 'tracked_on', 'action_count', 'threshold'])]
#[Hidden(['tracked_on', 'action_count', 'threshold'])]
class PetDiseaseCounter extends Model
{
    /** @use HasFactory<PetDiseaseCounterFactory> */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tracked_on' => 'date', 'action_count' => 'integer', 'threshold' => 'integer'];
    }
}
