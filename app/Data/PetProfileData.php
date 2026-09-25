<?php

namespace App\Data;

use App\Enums\DogSize;
use App\Enums\PetSex;
use App\Enums\PetStat;
use App\Models\Dog;
use App\Models\Pet;
use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class PetProfileData implements Arrayable
{
    /**
     * @param  list<string>  $traits
     * @param  array<string, float>  $states
     * @param  array<string, array{value: int, potential: int}>  $stats
     */
    public function __construct(
        public int $id,
        public string $name,
        public PetSex $sex,
        public DogSize $size,
        public int $generation,
        public ?string $description,
        public string $bornAt,
        public bool $isPurebred,
        public bool $isFavorite,
        public array $traits,
        public string $breed,
        public ?string $illustration,
        public string $coatColor,
        public array $states,
        public array $stats,
    ) {}

    public static function fromModel(Pet $pet, Dog $dog, string $locale): self
    {
        $stats = [];

        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = [
                'value' => $pet->getAttribute($stat->value),
                'potential' => $pet->getAttribute($stat->potentialColumn()),
            ];
        }

        return new self(
            id: $pet->id,
            name: $pet->name,
            sex: $pet->sex,
            size: $pet->size,
            generation: $pet->generation,
            description: $pet->description,
            bornAt: $pet->born_at->toIso8601String(),
            isPurebred: $pet->is_purebred,
            isFavorite: $pet->is_favorite,
            traits: $pet->traits ?? [],
            breed: $dog->localizedName($locale),
            illustration: $dog->illustration(),
            coatColor: $dog->coat_colors[$pet->coat_color][$locale] ?? $dog->coat_colors[$pet->coat_color]['en'] ?? $pet->coat_color,
            states: $pet->statePercentages(),
            stats: $stats,
        );
    }

    /** @return array{id: int, name: string, sex: string, size: string, generation: int, description: string|null, bornAt: string, isPurebred: bool, isFavorite: bool, traits: list<string>, breed: string, illustration: string|null, coatColor: string, states: array<string, float>, stats: array<string, array{value: int, potential: int}>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sex' => $this->sex->value,
            'size' => $this->size->value,
            'generation' => $this->generation,
            'description' => $this->description,
            'bornAt' => $this->bornAt,
            'isPurebred' => $this->isPurebred,
            'isFavorite' => $this->isFavorite,
            'traits' => $this->traits,
            'breed' => $this->breed,
            'illustration' => $this->illustration,
            'coatColor' => $this->coatColor,
            'states' => $this->states,
            'stats' => $this->stats,
        ];
    }
}
