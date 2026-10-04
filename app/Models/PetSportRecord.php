<?php

namespace App\Models;

use Database\Factories\PetSportRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $pet_id
 * @property string $discipline
 * @property int $starts
 * @property int $wins
 * @property int $experience
 * @property int $tier
 */
#[Fillable(['pet_id', 'discipline', 'starts', 'wins', 'experience', 'tier'])]
class PetSportRecord extends Model
{
    /** @use HasFactory<PetSportRecordFactory> */
    use HasFactory;

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts' => 'integer', 'wins' => 'integer', 'experience' => 'integer', 'tier' => 'integer'];
    }
}
