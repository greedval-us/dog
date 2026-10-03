<?php

namespace App\Models;

use Database\Factories\PuppyPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $puppy_id
 * @property int|null $pet_id
 * @property string $operation_token
 * @property string $kind
 * @property int $price
 * @property string $name
 * @property int|null $seller_id
 * @property User|null $user
 * @property User|null $seller
 * @property Puppy $puppy
 * @property Pet|null $pet
 */
#[Fillable(['user_id', 'puppy_id', 'pet_id', 'operation_token', 'kind', 'price', 'name', 'seller_id'])]
class PuppyPlacement extends Model
{
    /** @use HasFactory<PuppyPlacementFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Puppy, $this> */
    public function puppy(): BelongsTo
    {
        return $this->belongsTo(Puppy::class, 'puppy_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'puppy_id' => 'integer',
            'pet_id' => 'integer',
            'price' => 'integer',
            'seller_id' => 'integer',
        ];
    }
}
