<?php

namespace App\Modules\Pets\DTO;

use App\Models\Pet;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\Enums\PetStat;

final readonly class PetCompetitionSnapshot
{
    /** @return array<string, mixed> */
    public static function fromPet(Pet $pet, SkillRules $skillRules, string $discipline): array
    {
        $stats = [];
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = (int) $pet->getAttribute($stat->value);
            $potentials[$stat->value] = (int) $pet->getAttribute($stat->potentialColumn());
        }
        $skills = [];
        $statSnapshot = new PetStatSnapshot($stats, $potentials);
        foreach ($pet->skills as $skill) {
            if ($skillRules->isActive($statSnapshot, $skill->levels, $skill->is_active, $skill->pivot->level, ! $pet->isActive())) {
                $skills[$skill->code] = $skill->pivot->level;
            }
        }

        return [
            'version' => GameEventProtocol::SNAPSHOT_VERSION,
            'name' => $pet->name, 'breed' => $pet->dog->breed, 'breed_id' => $pet->dog_id, 'size' => $pet->size->value,
            'stats' => $stats, 'potentials' => $potentials, 'states' => $pet->statePercentages(null),
            'skills' => $skills, 'exterior' => $pet->exterior ?? [],
            'career_experience' => (int) ($pet->sportRecords->firstWhere('discipline', $discipline)->experience ?? 0),
            'pedigree' => ['generation' => $pet->generation, 'knownParents' => (int) ($pet->father_id !== null) + (int) ($pet->mother_id !== null)],
        ];
    }
}
