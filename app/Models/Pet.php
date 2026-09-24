<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $user_id
 * @property int $dog_id
 * @property int|null $father_id
 * @property int|null $mother_id
 * @property string $name
 * @property string $sex
 * @property string $coat_color
 * @property string $size
 * @property int $generation
 * @property CarbonImmutable $born_at
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable $state_updated_at
 * @property Dog $dog
 * @property User|null $user
 * @property Pet|null $father
 * @property Pet|null $mother
 * @property int $endurance
 * @property int $endurance_potential
 * @property int $speed
 * @property int $speed_potential
 * @property int $strength
 * @property int $strength_potential
 * @property int $agility
 * @property int $agility_potential
 * @property int $obedience
 * @property int $obedience_potential
 * @property int $intelligence
 * @property int $intelligence_potential
 * @property float $health
 * @property int $health_max
 * @property float $energy
 * @property int $energy_max
 * @property float $satiety
 * @property int $satiety_max
 * @property float $hydration
 * @property int $hydration_max
 * @property float $mood
 * @property int $mood_max
 * @property float $cleanliness
 * @property int $cleanliness_max
 * @property float $bond
 * @property int $bond_max
 */
#[Fillable(['user_id', 'dog_id', 'father_id', 'mother_id', 'name', 'sex', 'coat_color', 'description', 'size', 'born_at', 'generation', 'is_purebred', 'is_favorite', 'retired_at', 'image_path', 'photos', 'traits', 'activity', 'activity_started_at', 'activity_ends_at', 'state_updated_at', 'endurance', 'endurance_potential', 'speed', 'speed_potential', 'strength', 'strength_potential', 'agility', 'agility_potential', 'obedience', 'obedience_potential', 'intelligence', 'intelligence_potential', 'health', 'health_max', 'energy', 'energy_max', 'satiety', 'satiety_max', 'hydration', 'hydration_max', 'mood', 'mood_max', 'cleanliness', 'cleanliness_max', 'bond', 'bond_max', 'food_per_day', 'water_per_day'])]
class Pet extends Model
{
    /** @use HasFactory<PetFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Dog, $this> */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /** @return BelongsTo<Pet, $this> */
    public function father(): BelongsTo
    {
        return $this->belongsTo(self::class, 'father_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_id');
    }

    /** @return HasMany<Pet, $this> */
    public function paternalOffspring(): HasMany
    {
        return $this->hasMany(self::class, 'father_id');
    }

    /** @return HasMany<Pet, $this> */
    public function maternalOffspring(): HasMany
    {
        return $this->hasMany(self::class, 'mother_id');
    }

    /** @return array<string, float> */
    public function statePercentages(): array
    {
        $percentages = [];

        foreach (Dog::STATE_NAMES as $state) {
            $maximum = $this->getAttribute($state.'_max');
            $percentages[$state] = $maximum > 0
                ? round(max(0, min(100, $this->getAttribute($state) / $maximum * 100)), 1)
                : 0.0;
        }

        return $percentages;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'born_at' => 'datetime',
            'retired_at' => 'datetime',
            'state_updated_at' => 'datetime',
            'activity_started_at' => 'datetime',
            'activity_ends_at' => 'datetime',
            'photos' => 'array',
            'traits' => 'array',
            'generation' => 'integer',
            'is_purebred' => 'boolean',
            'is_favorite' => 'boolean',
            'endurance' => 'integer',
            'endurance_potential' => 'integer',
            'speed' => 'integer',
            'speed_potential' => 'integer',
            'strength' => 'integer',
            'strength_potential' => 'integer',
            'agility' => 'integer',
            'agility_potential' => 'integer',
            'obedience' => 'integer',
            'obedience_potential' => 'integer',
            'intelligence' => 'integer',
            'intelligence_potential' => 'integer',
            'health' => 'float',
            'health_max' => 'integer',
            'energy' => 'float',
            'energy_max' => 'integer',
            'satiety' => 'float',
            'satiety_max' => 'integer',
            'hydration' => 'float',
            'hydration_max' => 'integer',
            'mood' => 'float',
            'mood_max' => 'integer',
            'cleanliness' => 'float',
            'cleanliness_max' => 'integer',
            'bond' => 'float',
            'bond_max' => 'integer',
            'food_per_day' => 'integer',
            'water_per_day' => 'integer',
        ];
    }
}
