<?php

namespace App\Data;

use App\Enums\DogSize;
use App\Enums\PetStat;
use App\Models\Dog;
use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class StarterBreedData implements Arrayable
{
    /** @param array<string, int> $potentials */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public DogSize $size,
        public ?string $illustration,
        public array $potentials,
    ) {}

    public static function fromModel(Dog $dog, string $locale): self
    {
        $potentials = [];

        foreach (PetStat::cases() as $stat) {
            $potentials[$stat->value] = $dog->getAttribute($stat->potentialColumn());
        }

        return new self(
            id: $dog->id,
            name: $dog->localizedName($locale),
            description: $dog->description[$locale] ?? $dog->description['en'] ?? '',
            size: $dog->size,
            illustration: $dog->illustration(),
            potentials: $potentials,
        );
    }

    /** @return array{id: int, name: string, description: string, size: string, illustration: string|null, potentials: array<string, int>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'size' => $this->size->value,
            'illustration' => $this->illustration,
            'potentials' => $this->potentials,
        ];
    }
}
