<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Models\Puppy;
use Illuminate\Database\Eloquent\Builder;

/** @phpstan-type Origin array{breeder_key:string,kennel_key:string,kennel_name:string} */
final class GetPetBreedingOrigin
{
    /** @return Origin|null */
    public function forPet(Pet $pet): ?array
    {
        return $this->forPets([$pet->id])[$pet->id] ?? null;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int,Origin>
     */
    public function forPets(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $origins = [];
        $puppies = Puppy::query()->whereIn('pet_id', $ids)->where('status', 'placed')
            ->whereHas('litter', fn (Builder $litters) => $litters->whereNotNull('delivered_at'))
            ->with(['litter:id,father_id,mother_id,snapshots', 'pet:id,dog_id,father_id,mother_id'])->orderBy('id')->get();
        foreach ($puppies as $puppy) {
            $pet = $puppy->pet;
            if ($pet === null || $pet->dog_id !== $puppy->dog_id
                || $pet->father_id !== $puppy->father_id || $pet->mother_id !== $puppy->mother_id
                || $puppy->father_id !== $puppy->litter->father_id || $puppy->mother_id !== $puppy->litter->mother_id) {
                continue;
            }
            $origin = $puppy->litter->snapshots['breeding_origin'] ?? null;
            if (! is_array($origin) || ! is_string($origin['breeder_key'] ?? null) || trim($origin['breeder_key']) === ''
                || ! is_string($origin['kennel_key'] ?? null) || trim($origin['kennel_key']) === ''
                || ! is_string($origin['kennel_name'] ?? null) || trim($origin['kennel_name']) === '') {
                continue;
            }
            $origins[$pet->id] = ['breeder_key' => $origin['breeder_key'], 'kennel_key' => $origin['kennel_key'], 'kennel_name' => $origin['kennel_name']];
        }

        return $origins;
    }
}
