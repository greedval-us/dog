<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingPartner;
use App\Models\Pet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PublicPetProfileData;
use Illuminate\Database\Eloquent\Collection;

final class GetPetPedigree
{
    private const Generations = 3;

    public function __construct(private PetDecayCalculator $states) {}

    /** @return array{root: array<string, mixed>, hasAncestors: bool, generations: int} */
    public function handle(int $petId, string $locale): array
    {
        $root = Pet::query()->findOrFail($petId);
        $pets = [$root->id => $root];
        $frontier = [$root];

        for ($depth = 0; $depth < self::Generations; $depth++) {
            $parentIds = [];

            foreach ($frontier as $pet) {
                foreach ([$pet->father_id, $pet->mother_id] as $parentId) {
                    if ($parentId !== null && ! isset($pets[$parentId])) {
                        $parentIds[$parentId] = $parentId;
                    }
                }
            }

            if ($parentIds === []) {
                break;
            }

            $frontier = Pet::query()->whereIn('id', array_values($parentIds))->get()->all();

            foreach ($frontier as $parent) {
                $pets[$parent->id] = $parent;
            }
        }

        (new Collection(array_values($pets)))->load(['dog', 'titles']);
        $systemIds = BreedingPartner::query()->whereIn('pet_id', array_keys($pets))->pluck('pet_id')->all();
        $at = now();

        foreach ($pets as $pet) {
            if (! in_array($pet->id, $systemIds, true)) {
                $pet->advanceTo($at, $this->states);
            }
        }

        $tree = $this->node($root, $pets, $locale, self::Generations, [$root->id]);

        return [
            'root' => $tree,
            'hasAncestors' => $tree['father'] !== null || $tree['mother'] !== null,
            'generations' => self::Generations,
        ];
    }

    /**
     * @param  array<int, Pet>  $pets
     * @param  list<int>  $path
     * @return array{pet: array<string, mixed>, father: array<string, mixed>|null, mother: array<string, mixed>|null}
     */
    private function node(Pet $pet, array $pets, string $locale, int $remaining, array $path): array
    {
        $node = ['pet' => PublicPetProfileData::summary($pet, $locale), 'father' => null, 'mother' => null];

        if ($remaining > 0) {
            foreach (['father' => $pet->father_id, 'mother' => $pet->mother_id] as $relation => $parentId) {
                if ($parentId !== null && isset($pets[$parentId]) && ! in_array($parentId, $path, true)) {
                    $node[$relation] = $this->node($pets[$parentId], $pets, $locale, $remaining - 1, [...$path, $parentId]);
                }
            }
        }

        return $node;
    }
}
