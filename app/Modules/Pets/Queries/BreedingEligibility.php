<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use Carbon\CarbonImmutable;

final class BreedingEligibility
{
    public function reason(Pet $pet, CarbonImmutable $at, bool $system = false): ?string
    {
        if ($pet->health <= 0) {
            return 'breeding.errors.unhealthy';
        }

        if (! $system && $pet->archivedAt() !== null) {
            return 'breeding.errors.archived';
        }

        if ($pet->isBusy()) {
            return 'breeding.errors.busy';
        }

        if (! $system && $at->lessThan($pet->born_at->addDays(config('doglive.breeding_minimum_age_days', 7)))) {
            return 'breeding.errors.young';
        }

        if ($pet->breeding_available_at !== null && $at->lessThan($pet->breeding_available_at)) {
            return 'breeding.errors.cooldown';
        }

        return null;
    }

    public function related(Pet $first, Pet $second): bool
    {
        if ($first->id === $second->id) {
            return true;
        }

        $firstParents = array_filter([$first->father_id, $first->mother_id]);
        $secondParents = array_filter([$second->father_id, $second->mother_id]);

        if (array_intersect($firstParents, $secondParents) !== []) {
            return true;
        }

        $firstAncestors = $this->ancestors($first);
        $secondAncestors = $this->ancestors($second);

        return isset($firstAncestors[$second->id]) || isset($secondAncestors[$first->id])
            || isset($firstAncestors[$first->id]) || isset($secondAncestors[$second->id]);
    }

    /** @return array<int, true> */
    private function ancestors(Pet $pet): array
    {
        $frontier = array_filter([$pet->father_id, $pet->mother_id]);
        $ancestors = [];

        while ($frontier !== []) {
            $frontier = array_values(array_filter($frontier, static fn (int $id): bool => ! isset($ancestors[$id])));

            if ($frontier === []) {
                break;
            }

            foreach ($frontier as $id) {
                $ancestors[$id] = true;
            }

            $parents = Pet::query()->whereIn('id', $frontier)->get(['id', 'father_id', 'mother_id']);
            $frontier = [];

            foreach ($parents as $parent) {
                foreach (array_filter([$parent->father_id, $parent->mother_id]) as $id) {
                    if (! isset($ancestors[$id])) {
                        $frontier[] = $id;
                    }
                }
            }
        }

        return $ancestors;
    }
}
