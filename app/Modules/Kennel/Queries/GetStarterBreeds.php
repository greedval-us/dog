<?php

namespace App\Modules\Kennel\Queries;

use App\Models\Dog;
use App\Modules\Kennel\DTO\StarterBreedData;
use Illuminate\Support\Collection;

final class GetStarterBreeds
{
    /** @return Collection<int, StarterBreedData> */
    public function handle(string $locale): Collection
    {
        return Dog::query()->starter()->orderBy('id')->get()
            ->filter(fn (Dog $dog): bool => $dog->canBeAdopted())
            ->map(fn (Dog $dog): StarterBreedData => StarterBreedData::fromModel($dog, $locale))
            ->values();
    }
}
