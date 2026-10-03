<?php

namespace App\Models;

use App\Modules\Pets\Enums\PetSex;
use Carbon\CarbonImmutable;
use Database\Factories\PuppyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $litter_id
 * @property int $dog_id
 * @property int $father_id
 * @property int $mother_id
 * @property int|null $user_id
 * @property int|null $pet_id
 * @property string $status
 * @property string $name
 * @property PetSex $sex
 * @property string $coat_color
 * @property int $generation
 * @property int $endurance_potential
 * @property int $speed_potential
 * @property int $strength_potential
 * @property int $agility_potential
 * @property int $obedience_potential
 * @property int $intelligence_potential
 * @property int|null $sale_price
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $placed_at
 * @property BreedingLitter $litter
 * @property Dog $dog
 * @property Pet $father
 * @property Pet $mother
 * @property User|null $user
 * @property Pet|null $pet
 * @property Collection<int, PuppyPlacement> $placements
 */
#[Fillable(['litter_id', 'dog_id', 'father_id', 'mother_id', 'user_id', 'pet_id', 'status', 'name', 'sex', 'coat_color', 'generation', 'endurance_potential', 'speed_potential', 'strength_potential', 'agility_potential', 'obedience_potential', 'intelligence_potential', 'sale_price', 'expires_at', 'placed_at'])]
class Puppy extends Model
{
    /** @use HasFactory<PuppyFactory> */
    use HasFactory;

    /** @return BelongsTo<BreedingLitter, $this> */
    public function litter(): BelongsTo
    {
        return $this->belongsTo(BreedingLitter::class, 'litter_id');
    }

    /** @return BelongsTo<Dog, $this> */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'dog_id');
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    /** @return HasMany<PuppyPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(PuppyPlacement::class, 'puppy_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'litter_id' => 'integer',
            'dog_id' => 'integer',
            'father_id' => 'integer',
            'mother_id' => 'integer',
            'user_id' => 'integer',
            'pet_id' => 'integer',
            'sex' => PetSex::class,
            'generation' => 'integer',
            'endurance_potential' => 'integer',
            'speed_potential' => 'integer',
            'strength_potential' => 'integer',
            'agility_potential' => 'integer',
            'obedience_potential' => 'integer',
            'intelligence_potential' => 'integer',
            'sale_price' => 'integer',
            'expires_at' => 'immutable_datetime',
            'placed_at' => 'immutable_datetime',
        ];
    }
}
