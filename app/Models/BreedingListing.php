<?php

namespace App\Models;

use Database\Factories\BreedingListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pet_id
 * @property int|null $user_id
 * @property int $price
 * @property bool $is_active
 * @property Pet $pet
 * @property User|null $user
 */
#[Fillable(['pet_id', 'user_id', 'price', 'is_active'])]
class BreedingListing extends Model
{
    /** @use HasFactory<BreedingListingFactory> */
    use HasFactory;

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pet_id' => 'integer',
            'user_id' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
