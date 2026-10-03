<?php

namespace App\Modules\Pets\DTO;

use App\Models\CharacterTrait;
use App\Models\Dog;
use App\Models\Pet;
use App\Modules\Pets\Enums\DogSize;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class PetProfileData implements Arrayable
{
    /**
     * @param  list<string>  $traits
     * @param  array<string, float>  $states
     * @param  array{value: float, maximum: int}  $energy
     * @param  array<string, array{value: int, potential: int}>  $stats
     * @param  array{status: string, archivedAt: string|null, canRetire: bool, retirementEligibleAt: string, automaticRetirementAt: string}  $lifecycle
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
        public string $coatColor,
        public array $states,
        public array $energy,
        public array $stats,
        public array $lifecycle,
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
            traits: array_values($pet->characterTraits->map(fn (CharacterTrait $trait): string => $trait->code)->all()),
            breed: $dog->localizedName($locale),
            coatColor: $dog->coat_colors[$pet->coat_color][$locale] ?? $dog->coat_colors[$pet->coat_color]['en'] ?? $pet->coat_color,
            states: $pet->statePercentages(),
            energy: ['value' => $pet->energy, 'maximum' => $pet->energy_max],
            stats: $stats,
            lifecycle: [
                'status' => $pet->died_at !== null ? 'deceased' : ($pet->retired_at !== null ? 'retired' : 'active'),
                'archivedAt' => $pet->archivedAt()?->toIso8601String(),
                'canRetire' => $pet->canRetire(now()),
                'retirementEligibleAt' => $pet->retirementEligibleAt()->toIso8601String(),
                'automaticRetirementAt' => $pet->automaticRetirementAt()->toIso8601String(),
            ],
        );
    }

    /** @return array{id: int, name: string, sex: string, size: string, generation: int, description: string|null, bornAt: string, isPurebred: bool, isFavorite: bool, traits: list<string>, breed: string, coatColor: string, states: array<string, float>, energy: array{value: float, maximum: int}, stats: array<string, array{value: int, potential: int}>, lifecycle: array{status: string, archivedAt: string|null, canRetire: bool, retirementEligibleAt: string, automaticRetirementAt: string}} */
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
            'coatColor' => $this->coatColor,
            'states' => $this->states,
            'energy' => $this->energy,
            'stats' => $this->stats,
            'lifecycle' => $this->lifecycle,
        ];
    }
}
