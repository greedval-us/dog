<?php

namespace App\Modules\Pets\Calculators;

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
     * @param  array<string, int>  $potentials
     * @param  array<string, int>  $percentages
     * @return array<string, int>
     */
    public function requiredValues(array $potentials, array $percentages): array
    {
        $requirements = [];
        foreach ($percentages as $stat => $percentage) {
            $requirements[$stat] = intdiv(max(0, $potentials[$stat] ?? 0) * $percentage + 99, 100);
        }

        return $requirements;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $potentials
     * @param  array<string, int>  $percentages
     */
    public function meets(array $stats, array $potentials, array $percentages): bool
    {
        foreach ($this->requiredValues($potentials, $percentages) as $stat => $minimum) {
            if (($potentials[$stat] ?? 0) < 1 || ($stats[$stat] ?? 0) < $minimum) {
                return false;
            }
        }

        return true;
    }
}
