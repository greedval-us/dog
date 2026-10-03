<?php

namespace App\Models;

use Database\Factories\BreedingPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pet_id
 * @property string $code
 * @property int $price
 * @property bool $is_active
 * @property Pet $pet
 */
#[Fillable(['pet_id', 'code', 'price', 'is_active'])]
class BreedingPartner extends Model
{
    /** @use HasFactory<BreedingPartnerFactory> */
    use HasFactory;

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pet_id' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
