<?php

namespace App\Models;

use App\Modules\Pets\DTO\NewPetData;
use App\Modules\Pets\Enums\DogSize;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Enums\PetState;
use Database\Factories\DogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $breed
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property DogSize $size
 * @property bool $is_starter
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

    /** @return HasMany<Pet, $this> */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return $this->name[$locale ?? app()->getLocale()] ?? $this->name['en'] ?? $this->breed;
    }

    public function illustration(): ?string
    {
        return in_array($this->breed, config('doglive.illustrated_breeds'), true) ? $this->breed : null;
    }

    public function canBeAdopted(): bool
    {
        return $this->is_starter && $this->coat_colors !== [];
    }

    /** @param Builder<Dog> $query */
    #[Scope]
    protected function starter(Builder $query): void
    {
        $query->where('is_starter', true);
    }

    /**
     * Snapshot breed defaults so later catalogue changes do not change existing pets.
     * Food and water use game units; satiety and hydration are remaining reserves.
     *
     * @return array<string, int|string|\DateTimeInterface>
     */
    public function petDefaults(): array
    {
        $initializedAt = now();
        $attributes = [
            'size' => $this->size->value,
            'generation' => 1,
            'food_per_day' => $this->food_per_day,
            'water_per_day' => $this->water_per_day,
            'born_at' => $initializedAt,
            'state_updated_at' => $initializedAt,
            'stats_updated_at' => $initializedAt,
        ];

        foreach (PetStat::cases() as $stat) {
            $attributes[$stat->value] = 0;
            $attributes[$stat->potentialColumn()] = $this->getAttribute($stat->potentialColumn());
        }

        foreach (PetState::cases() as $state) {
            $maximum = $this->getAttribute($state->maximumColumn());
            $attributes[$state->maximumColumn()] = $maximum;
            $attributes[$state->value] = $state === PetState::Bond ? 0 : $maximum;
        }

        return $attributes;
    }

    public function newPet(NewPetData $data): Pet
    {
        $pet = new Pet([
            ...$this->petDefaults(),
            'name' => $data->name,
            'sex' => $data->sex,
            'coat_color' => $data->coatColor,
            'description' => $data->description,
        ]);
        $pet->dog()->associate($this);

        return $pet;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => DogSize::class,
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
