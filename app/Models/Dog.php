<?php

namespace App\Models;

use Database\Factories\DogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $breed
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property string $size
 * @property array<string, array<string, string>> $coat_colors
 * @property int $endurance_potential
 * @property int $speed_potential
 * @property int $strength_potential
 * @property int $agility_potential
 * @property int $obedience_potential
 * @property int $intelligence_potential
 * @property int $health_max
 * @property int $energy_max
 * @property int $satiety_max
 * @property int $hydration_max
 * @property int $mood_max
 * @property int $cleanliness_max
 * @property int $bond_max
 * @property int $food_per_day
 * @property int $water_per_day
 */
#[Fillable(['breed', 'name', 'description', 'size', 'coat_colors', 'is_starter', 'endurance_potential', 'speed_potential', 'strength_potential', 'agility_potential', 'obedience_potential', 'intelligence_potential', 'health_max', 'energy_max', 'satiety_max', 'hydration_max', 'mood_max', 'cleanliness_max', 'bond_max', 'food_per_day', 'water_per_day'])]
class Dog extends Model
{
    /** @use HasFactory<DogFactory> */
    use HasFactory;

    protected $table = 'dog';

    public const STAT_NAMES = ['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'];

    public const STATE_NAMES = ['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'];

    /** @return HasMany<Pet, $this> */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return $this->name[$locale ?? app()->getLocale()] ?? $this->name['en'] ?? $this->breed;
    }

    /**
     * Snapshot breed defaults so later catalogue changes do not change existing pets.
     * Food and water use game units; satiety and hydration are remaining reserves.
     *
     * @return array<string, int|string|\DateTimeInterface>
     */
    public function petDefaults(): array
    {
        $attributes = [
            'size' => $this->size,
            'generation' => 1,
            'food_per_day' => $this->food_per_day,
            'water_per_day' => $this->water_per_day,
            'born_at' => now(),
            'state_updated_at' => now(),
        ];

        foreach (self::STAT_NAMES as $stat) {
            $attributes[$stat] = 0;
            $attributes[$stat.'_potential'] = $this->getAttribute($stat.'_potential');
        }

        foreach (self::STATE_NAMES as $state) {
            $maximum = $this->getAttribute($state.'_max');
            $attributes[$state.'_max'] = $maximum;
            $attributes[$state] = $state === 'bond' ? 0 : $maximum;
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    public function newPet(array $attributes = []): Pet
    {
        $pet = new Pet([...$this->petDefaults(), ...$attributes]);
        $pet->dog()->associate($this);

        return $pet;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'coat_colors' => 'array',
            'is_starter' => 'boolean',
            'endurance_potential' => 'integer',
            'speed_potential' => 'integer',
            'strength_potential' => 'integer',
            'agility_potential' => 'integer',
            'obedience_potential' => 'integer',
            'intelligence_potential' => 'integer',
            'health_max' => 'integer',
            'energy_max' => 'integer',
            'satiety_max' => 'integer',
            'hydration_max' => 'integer',
            'mood_max' => 'integer',
            'cleanliness_max' => 'integer',
            'bond_max' => 'integer',
            'food_per_day' => 'integer',
            'water_per_day' => 'integer',
        ];
    }
}
