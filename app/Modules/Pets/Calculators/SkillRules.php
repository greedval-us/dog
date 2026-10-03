<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\PetStatSnapshot;
use App\Modules\Pets\Enums\PetStat;

final class SkillRules
{
    public const MAX_LEVEL = 5;

    public const COOLDOWN_SECONDS = 86400;

    /** @param array<mixed>|null $levels */
    public function valid(?array $levels): bool
    {
        if ($levels === null || ! array_is_list($levels) || count($levels) !== self::MAX_LEVEL) {
            return false;
        }

        $previous = [];
        foreach ($levels as $level) {
            if (! is_array($level) || ! is_int($level['price'] ?? null) || $level['price'] < 1 || $level['price'] > 1000000000
                || ! is_array($level['requirements'] ?? null) || $level['requirements'] === []) {
                return false;
            }
            $requirements = $level['requirements'];
            if ($previous !== [] && array_diff_key($previous, $requirements) !== []) {
                return false;
            }
            foreach ($requirements as $stat => $percentage) {
                if (! is_string($stat) || PetStat::tryFrom($stat) === null || ! is_int($percentage) || $percentage < 1 || $percentage > 100
                    || $percentage < ($previous[$stat] ?? 0)) {
                    return false;
                }
            }
            $previous = $requirements;
        }

        return true;
    }

    /**
     * @param  array<string, int>  $percentages
     * @return array<string, int>
     */
    public function requiredValues(PetStatSnapshot $attributes, array $percentages): array
    {
        $requirements = [];
        foreach ($percentages as $stat => $percentage) {
            $requirements[$stat] = intdiv(max(0, $attributes->potentials[$stat] ?? 0) * $percentage + 99, 100);
        }

        return $requirements;
    }

    /**
     * @param  array<string, int>  $percentages
     */
    public function meets(PetStatSnapshot $attributes, array $percentages): bool
    {
        foreach ($this->requiredValues($attributes, $percentages) as $stat => $minimum) {
            if (($attributes->potentials[$stat] ?? 0) < 1 || ($attributes->values[$stat] ?? 0) < $minimum) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed>|null $levels */
    public function hasLearnedLevel(?array $levels, bool $enabled, int $level, int $minimumLevel = 1): bool
    {
        return $enabled && $level >= 1 && $level >= $minimumLevel && $level <= self::MAX_LEVEL && $this->valid($levels);
    }

    /** @param list<array{price: int, requirements: array<string, int>}>|null $levels */
    public function isActive(PetStatSnapshot $attributes, ?array $levels, bool $enabled, int $level, bool $retired): bool
    {
        return ! $retired && $this->hasLearnedLevel($levels, $enabled, $level)
            && $this->meets($attributes, $levels[$level - 1]['requirements']);
    }
}
