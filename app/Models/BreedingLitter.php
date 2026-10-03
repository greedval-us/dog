<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BreedingLitterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $operation_token
 * @property int|null $initiator_id
 * @property int $own_pet_id
 * @property int|null $listing_id
 * @property int|null $partner_id
 * @property int $father_id
 * @property int $mother_id
 * @property int $price
 * @property array<string, mixed> $snapshots
 * @property CarbonImmutable $born_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $delivered_at
 * @property User|null $initiator
 * @property Pet $ownPet
 * @property Pet $father
 * @property Pet $mother
 * @property BreedingListing|null $listing
 * @property BreedingPartner|null $partner
 * @property Collection<int, Puppy> $puppies
 */
#[Fillable(['operation_token', 'initiator_id', 'own_pet_id', 'listing_id', 'partner_id', 'father_id', 'mother_id', 'price', 'snapshots', 'born_at', 'expires_at', 'delivered_at'])]
class BreedingLitter extends Model
{
    /** @use HasFactory<BreedingLitterFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function ownPet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'own_pet_id');
    }

    /** @return BelongsTo<BreedingListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(BreedingListing::class, 'listing_id');
    }

    /** @return BelongsTo<BreedingPartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(BreedingPartner::class, 'partner_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function father(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'father_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'mother_id');
    }

    /** @return HasMany<Puppy, $this> */
    public function puppies(): HasMany
    {
        return $this->hasMany(Puppy::class, 'litter_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'initiator_id' => 'integer',
            'own_pet_id' => 'integer',
            'listing_id' => 'integer',
            'partner_id' => 'integer',
            'father_id' => 'integer',
            'mother_id' => 'integer',
            'price' => 'integer',
            'snapshots' => 'array',
            'born_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
