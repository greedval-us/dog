<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Models\PetTitle;
use App\Modules\Pets\Calculators\PetEventProfileRules;

/**
 * @phpstan-type Profile array{id:int,name:string,breed_id:int,breed_code:string,sex:string,size:string,height_cm:float,height_category:string,recognized_rkf:bool,recognized_fci:bool,fci_group:int|null,registered:bool,pedigree_complete:bool,working_breed:bool,working_certificate:bool,club_system:string,has_nkp:bool,championships:list<string>,agility_level:int,agility_available_levels:list<int>,nosework_levels:array{pr1:int,pr2:int},nosework_tests:array{pr1:list<string>,pr2:list<string>},nosework_nw0_qualifications:array{pr1:int,pr2:int},race_category:string}
 */
final readonly class GetPetEventProfile
{
    public function __construct(private PetEventProfileRules $rules) {}

    /** @return Profile */
    public function forPet(Pet $pet): array
    {
        $pet->loadMissing(['dog', 'sportRecords']);
        $snapshot = $pet->event_profile ?? [];
        $clubSystem = $this->rules->clubSystem($snapshot['club_system'] ?? $pet->dog->eventProfileDefaults()['club_system'], (bool) ($snapshot['recognized_rkf'] ?? false));
        $titles = $pet->relationLoaded('titles') ? $pet->titles->where('kind', 'championship') : $pet->titles()->where('kind', 'championship')->get();
        $championships = array_values($titles->map(static fn (PetTitle $title): string => $title->code)->unique()->sort()->all());
        $agility = $pet->sportRecords->firstWhere('discipline', 'agility')->regulatory_progress ?? [];
        $nosework = $pet->sportRecords->firstWhere('discipline', 'nosework')->regulatory_progress ?? [];
        $levels = [];
        $tests = [];
        $qualifications = [];
        foreach (['pr1', 'pr2'] as $program) {
            $levels[$program] = (int) data_get($nosework, 'nosework_levels.'.$program, 0);
            $tests[$program] = $this->stringList(data_get($nosework, 'nosework_tests.'.$program, []));
            $qualifications[$program] = (int) data_get($nosework, 'nosework_nw0_qualifications.'.$program, 0);
        }

        return [
            'id' => $pet->id, 'name' => $pet->name, 'breed_id' => $pet->dog_id, 'breed_code' => $pet->dog->breed,
            'sex' => $pet->sex->value, 'size' => $pet->size->value,
            'height_cm' => (float) ($snapshot['height_cm'] ?? 0), 'height_category' => $this->rules->heightCategory((float) ($snapshot['height_cm'] ?? 0)),
            'recognized_rkf' => (bool) ($snapshot['recognized_rkf'] ?? false), 'recognized_fci' => (bool) ($snapshot['recognized_fci'] ?? false),
            'fci_group' => isset($snapshot['fci_group']) ? (int) $snapshot['fci_group'] : null,
            'registered' => (bool) ($snapshot['registered'] ?? false),
            'pedigree_complete' => (bool) ($snapshot['pedigree_verified'] ?? false) || $this->hasCompletePedigree($pet),
            'working_breed' => (bool) ($snapshot['working_breed'] ?? false),
            'working_certificate' => (bool) ($snapshot['working_certificate_verified'] ?? false),
            'club_system' => $clubSystem, 'has_nkp' => $clubSystem === 'nkp',
            'championships' => $championships,
            'agility_level' => (int) ($agility['agility_level'] ?? 1), 'agility_available_levels' => $this->integerList($agility['agility_available_levels'] ?? [1]),
            'nosework_levels' => ['pr1' => $levels['pr1'], 'pr2' => $levels['pr2']],
            'nosework_tests' => ['pr1' => $tests['pr1'], 'pr2' => $tests['pr2']],
            'nosework_nw0_qualifications' => ['pr1' => $qualifications['pr1'], 'pr2' => $qualifications['pr2']],
            'race_category' => (string) ($snapshot['race_category'] ?? '4'),
        ];
    }

    private function hasCompletePedigree(Pet $pet): bool
    {
        if (! $pet->is_purebred || $pet->father_id === null || $pet->mother_id === null) {
            return false;
        }

        $ancestors = [$pet->id => $pet];
        $pending = [$pet->father_id, $pet->mother_id];
        for ($generation = 0; $generation < 3; $generation++) {
            $found = Pet::query()->whereKey(array_values(array_unique($pending)))->get(['id', 'dog_id', 'father_id', 'mother_id', 'is_purebred']);
            $pending = [];
            foreach ($found as $ancestor) {
                $ancestors[$ancestor->id] = $ancestor;
                foreach ([$ancestor->father_id, $ancestor->mother_id] as $id) {
                    if ($id !== null && ! isset($ancestors[$id])) {
                        $pending[] = $id;
                    }
                }
            }
        }

        return $this->completeBranch($pet->father_id, 3, [$pet->id], $pet->dog_id, $ancestors)
            && $this->completeBranch($pet->mother_id, 3, [$pet->id], $pet->dog_id, $ancestors);
    }

    /**
     * @param  list<int>  $path
     * @param  array<int,Pet>  $ancestors
     */
    private function completeBranch(?int $id, int $remaining, array $path, int $breedId, array $ancestors): bool
    {
        if ($id === null || in_array($id, $path, true) || ! isset($ancestors[$id])) {
            return false;
        }
        $ancestor = $ancestors[$id];
        if (! $ancestor->is_purebred || $ancestor->dog_id !== $breedId) {
            return false;
        }
        if ($remaining === 1) {
            return true;
        }

        return $this->completeBranch($ancestor->father_id, $remaining - 1, [...$path, $id], $breedId, $ancestors)
            && $this->completeBranch($ancestor->mother_id, $remaining - 1, [...$path, $id], $breedId, $ancestors);
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /** @return list<int> */
    private function integerList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map(intval(...), array_filter($value, is_numeric(...)))) : [1];
    }
}
